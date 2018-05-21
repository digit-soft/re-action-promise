<?php

namespace Reaction\Promise;

use React\Promise\LazyPromise;
use React\Promise\PromiseInterface;

/**
 * Class Promise
 * @package Reaction\Promise
 */
class Promise implements PromiseWithSharedDataInterface
{
    /** @var callable|null */
    protected $canceller;
    /** @var FulfilledPromise|RejectedPromise */
    protected $result;

    /** @var callable[] */
    protected $handlers = [];
    /** @var callable[] */
    protected $progressHandlers = [];

    /** @var int */
    protected $requiredCancelRequests = 0;

    /** @var SharedDataInterface */
    public  $sharedData;

    /**
     * Promise constructor.
     * @param callable                 $resolver
     * @param callable                 $canceller
     * @param SharedDataInterface      $sharedData
     */
    public function __construct(callable $resolver, callable $canceller = null, SharedDataInterface $sharedData = null)
    {
        $this->canceller = $canceller;
        if (isset($sharedData)) {
            $this->sharedData = $sharedData;
        } else {
            $this->sharedData = new SharedData();
        }
        $this->call($resolver);
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

        $this->requiredCancelRequests++;

        return new static($this->resolver($onFulfilled, $onRejected, $onProgress), function () {
            $this->requiredCancelRequests--;

            if ($this->requiredCancelRequests <= 0) {
                $this->cancel();
            }
        }, $this->sharedData);
    }

    /**
     * Final promise callback
     * @param callable $onFulfilled
     * @param callable $onRejected
     * @param callable $onProgress
     * @return null
     */
    public function done(callable $onFulfilled = null, callable $onRejected = null, callable $onProgress = null)
    {
        if (null !== $this->result) {
            return $this->result->done($onFulfilled, $onRejected, $onProgress);
        }

        $this->handlers[] = function (ExtendedPromiseInterface $promise) use ($onFulfilled, $onRejected) {
            $promise
                ->done($onFulfilled, $onRejected);
        };

        if ($onProgress) {
            $this->progressHandlers[] = $onProgress;
        }
        return null;
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
                return new RejectedPromise($reason);
            }

            return $onRejected($reason);
        });
    }

    /**
     * Always callable after resolving or rejecting
     * @param callable $onFulfilledOrRejected
     * @return PromiseWithSharedDataInterface
     */
    public function always(callable $onFulfilledOrRejected)
    {
        return $this->then(function ($value) use ($onFulfilledOrRejected) {
            return resolve($onFulfilledOrRejected())->then(function () use ($value) {
                return $value;
            });
        }, function ($reason) use ($onFulfilledOrRejected) {
            return resolve($onFulfilledOrRejected())->then(function () use ($reason) {
                return new RejectedPromise($reason);
            });
        });
    }

    /**
     * Progress callback
     * @param callable $onProgress
     * @return PromiseWithSharedDataInterface
     */
    public function progress(callable $onProgress)
    {
        return $this->then(null, null, $onProgress);
    }

    /**
     * Promise canceler
     */
    public function cancel()
    {
        $canceller = $this->canceller;
        $this->canceller = null;

        $parentCanceller = null;

        if (null !== $this->result) {
            // Go up the promise chain and reach the top most promise which is
            // itself not following another promise
            $root = $this->unwrap($this->result);

            // Return if the root promise is already resolved or a
            // FulfilledPromise or RejectedPromise
            if (!$root instanceof self || null !== $root->result) {
                return;
            }

            $root->requiredCancelRequests--;

            if ($root->requiredCancelRequests <= 0) {
                $parentCanceller = [$root, 'cancel'];
            }
        }

        if (null !== $canceller) {
            $this->call($canceller);
        }

        // For BC, we call the parent canceller after our own canceller
        if ($parentCanceller) {
            $parentCanceller();
        }
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
        $self = $this;
        return function ($resolve, $reject, $notify) use ($onFulfilled, $onRejected, $onProgress, $self) {
            if ($onProgress) {
                $progressHandler = function ($update) use ($notify, $onProgress, $self) {
                    try {
                        $notify($onProgress($update, $self->sharedData));
                    } catch (\Throwable $e) {
                        $notify($e);
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
     * @param mixed|null $value
     */
    protected function resolve($value = null)
    {
        if (null !== $this->result) {
            return;
        }

        $this->settle(resolve($value, $this->sharedData));
    }

    /**
     * @param mixed|null $reason
     */
    protected function reject($reason = null)
    {
        if (null !== $this->result) {
            return;
        }

        $this->settle(reject($reason, $this->sharedData));
    }

    /**
     * @param mixed|null $update
     */
    protected function notify($update = null)
    {
        if (null !== $this->result) {
            return;
        }

        foreach ($this->progressHandlers as $handler) {
            $handler($update, $this->sharedData);
        }
    }

    /**
     * @param ExtendedPromiseInterface|PromiseWithSharedDataInterface $promise
     */
    protected function settle(ExtendedPromiseInterface $promise)
    {
        $promise = $this->unwrap($promise);

        if ($promise === $this) {
            $promise = new RejectedPromise(
                new \LogicException('Cannot resolve a promise with itself.'),
                $this->sharedData
            );
        }

        if ($promise instanceof self) {
            $promise->requiredCancelRequests++;
        }

        $handlers = $this->handlers;

        $this->progressHandlers = $this->handlers = [];
        $this->result = $promise;

        foreach ($handlers as $handler) {
            $handler($promise);
        }
    }

    /**
     * @param PromiseInterface|ExtendedPromiseInterface $promise
     * @return \React\Promise\FulfilledPromise|\React\Promise\Promise|\React\Promise\RejectedPromise
     */
    protected function unwrap($promise)
    {
        $promise = $this->extract($promise);

        while ($promise instanceof self && null !== $promise->result) {
            $promise = $this->extract($promise->result);
        }

        return $promise;
    }

    /**
     * @param PromiseInterface|ExtendedPromiseInterface $promise
     * @return \React\Promise\FulfilledPromise|\React\Promise\Promise|\React\Promise\RejectedPromise
     */
    protected function extract($promise)
    {
        if ($promise instanceof LazyPromise) {
            $promise = $promise->promise();
        }

        return $promise;
    }

    /**
     * @param callable $callback
     */
    protected function call(callable $callback)
    {
        try {
            $callback(
                function ($value = null) {
                    $this->resolve($value);
                },
                function ($reason = null) {
                    $this->reject($reason);
                },
                function ($update = null) {
                    $this->notify($update);
                }
            );
        } catch (\Throwable $e) {
            $this->reject($e);
        }
    }
}