<?php

namespace Reaction\Promise;

/**
 * Class PromiseWithSD
 * @package Reaction\Promise
 */
class PromiseWithSD extends Promise implements PromiseWithSharedDataInterface
{
    /** @var SharedData */
    protected $sharedData;

    /**
     * PromiseWithSD constructor.
     * @param callable                 $resolver
     * @param callable                 $canceller
     * @param SharedDataInterface      $sharedData
     */
    public function __construct(callable $resolver, callable $canceller = null, SharedDataInterface $sharedData = null)
    {
        if (isset($sharedData)) {
            $this->sharedData = $sharedData;
        } else {
            $this->sharedData = new SharedData();
        }
        parent::__construct($resolver, $canceller);
    }

    /**
     * Transform promise
     * @param callable|null $onFulfilled
     * @param callable|null $onRejected
     * @param callable|null $onProgress
     * @return PromiseWithSharedDataInterface
     */
    public function then(callable $onFulfilled = null, callable $onRejected = null, callable $onProgress = null)
    {
        if (null !== $this->result) {
            if ($this->result instanceof PromiseWithSharedDataInterface) {
                _mergeSharedData($this->sharedData, $this->result->sharedData, true);
            }
            return $this->result->then($onFulfilled, $onRejected, $onProgress);
        }

        if (null === $this->canceller) {
            return new static($this->resolver($onFulfilled, $onRejected, $onProgress), null, $this->sharedData);
        }

        // keep a reference to this promise instance for the static canceller function.
        // see also parentCancellerFunction() for more details.
        $parent = $this;
        ++$parent->requiredCancelRequests;

        return new static(
            $this->resolver($onFulfilled, $onRejected, $onProgress),
            self::parentCancellerFunction($parent),
            $this->sharedData
        );
    }

    /**
     * Shortcut to ->then(null, $onRejected)
     * @param callable $onRejected
     * @return PromiseWithSharedDataInterface
     */
    public function otherwise(callable $onRejected)
    {
        return $this->then(null, function ($reason) use ($onRejected) {
            if (!_checkTypehint($onRejected, $reason)) {
                return new RejectedPromiseWithSD($reason, $this->sharedData);
            }

            return $onRejected($reason);
        });
    }


    /**
     * Always callable after resolving or rejecting
     * @param callable $onFulfilledOrRejected
     * @return ExtendedPromiseInterface
     */
    public function always(callable $onFulfilledOrRejected)
    {
        return $this->then(function ($value) use ($onFulfilledOrRejected) {
            return resolve($onFulfilledOrRejected($this->sharedData))->then(function () use ($value) {
                return $value;
            });
        }, function ($reason) use ($onFulfilledOrRejected) {
            return resolve($onFulfilledOrRejected($this->sharedData))->then(function () use ($reason) {
                return new RejectedPromiseWithSD($reason, $this->sharedData);
            });
        });
    }

    /**
     * Promise resolver callback generator
     * @param callable|null $onFulfilled
     * @param callable|null $onRejected
     * @param callable|null $onProgress
     * @return \Closure
     */
    protected function resolver(callable $onFulfilled = null, callable $onRejected = null, callable $onProgress = null)
    {
        return function ($resolve, $reject, $notify) use ($onFulfilled, $onRejected, $onProgress) {
            if ($onProgress) {
                $progressHandler = function ($update) use ($notify, $onProgress) {
                    try {
                        $notify($onProgress($update), $this->sharedData);
                    } catch (\Throwable $e) {
                        $notify($e, $this->sharedData);
                    }
                };
            } else {
                $progressHandler = $notify;
            }

            $this->handlers[] = function (ExtendedPromiseInterface $promise) use ($onFulfilled, $onRejected, $resolve, $reject, $progressHandler) {
                $promise
                    ->then($onFulfilled, $onRejected)
                    ->done($resolve, $reject, $progressHandler);
            };

            $this->progressHandlers[] = $progressHandler;
        };
    }

    /**
     * @param mixed $reason
     */
    protected function reject($reason = null)
    {
        if (null !== $this->result) {
            return;
        }

        $this->settle(reject($reason, $this->sharedData));
    }

    /**
     * @param ExtendedPromiseInterface $promise
     */
    protected function settle(ExtendedPromiseInterface $promise)
    {
        $promise = $this->unwrap($promise);

        if ($promise === $this) {
            $promise = new RejectedPromiseWithSD(
                new \LogicException('Cannot resolve a promise with itself.'),
                $this->sharedData
            );
        }

        if ($promise instanceof self) {
            $promise->requiredCancelRequests++;
        } else {
            $this->canceller = null;
        }

        $handlers = $this->handlers;

        $this->progressHandlers = $this->handlers = [];
        $this->result = $promise;

        foreach ($handlers as $handler) {
            $handler($promise);
        }
    }


    /**
     * @param callable $cb
     */
    protected function call(callable $cb)
    {
        // Explicitly overwrite argument with null value. This ensure that this
        // argument does not show up in the stack trace in PHP 7+ only.
        $callback = $cb;
        $cb = null;

        // Use reflection to inspect number of arguments expected by this callback.
        // We did some careful benchmarking here: Using reflection to avoid unneeded
        // function arguments is actually faster than blindly passing them.
        // Also, this helps avoiding unnecessary function arguments in the call stack
        // if the callback creates an Exception (creating garbage cycles).
        if (is_array($callback)) {
            $ref = new \ReflectionMethod($callback[0], $callback[1]);
        } elseif (is_object($callback) && !$callback instanceof \Closure) {
            $ref = new \ReflectionMethod($callback, '__invoke');
        } else {
            $ref = new \ReflectionFunction($callback);
        }
        $args = $ref->getNumberOfParameters();

        try {
            if ($args === 0) {
                $callback();
            } else {
                // keep a reference to this promise instance for the static resolve/reject functions.
                // see also resolveFunction() and rejectFunction() for more details.
                $target =& $this;

                $callback(
                    self::resolveFunction($target),
                    self::rejectFunction($target),
                    self::notifyFunction($this->progressHandlers)
                );
            }
        } catch (\Throwable $e) {
            $target = null;
            $this->reject($e);
        }
    }

    /**
     * Creates a static resolver callback that is not bound to a promise instance.
     *
     * Moving the closure creation to a static method allows us to create a
     * callback that is not bound to a promise instance. By passing the target
     * promise instance by reference, we can still execute its resolving logic
     * and still clear this reference when settling the promise. This helps
     * avoiding garbage cycles if any callback creates an Exception.
     *
     * These assumptions are covered by the test suite, so if you ever feel like
     * refactoring this, go ahead, any alternative suggestions are welcome!
     *
     * @param ExtendedPromiseInterface $target
     * @return callable
     */
    protected static function resolveFunction(self &$target)
    {
        return function ($value = null) use (&$target) {
            if ($target !== null) {
                $target->settle(resolve($value, $target->sharedData));
                $target = null;
            }
        };
    }
}