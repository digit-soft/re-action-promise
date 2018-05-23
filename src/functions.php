<?php

namespace Reaction\Promise;

use React\Promise\CancellationQueue;
use React\Promise\PromiseInterface;

/**
 * @param PromiseInterface|mixed $promiseOrValue
 * @param SharedDataInterface|null $sharedData
 * @return ExtendedPromiseInterface
 */
function resolve($promiseOrValue = null, SharedDataInterface &$sharedData = null)
{
    /** @var $promiseOrValue ExtendedPromiseInterface */
    if ($promiseOrValue instanceof PromiseWithSharedDataInterface && isset($sharedData)) {
        _mergeSharedData($sharedData, $promiseOrValue->sharedData, true);
        return $promiseOrValue;
    } elseif ($promiseOrValue instanceof ExtendedPromiseInterface) {
        return $promiseOrValue;
    }

    /** @var $promiseOrValue PromiseInterface */
    if (method_exists($promiseOrValue, 'then')) {
        $canceller = null;

        if (method_exists($promiseOrValue, 'cancel')) {
            $canceller = [$promiseOrValue, 'cancel'];
        }

        return isset($sharedData) ?
            new PromiseWithSD(function($resolve, $reject, $notify) use ($promiseOrValue) {
                $promiseOrValue->then($resolve, $reject, $notify);
            }, $canceller, $sharedData)
            : new Promise(function($resolve, $reject, $notify) use ($promiseOrValue) {
                $promiseOrValue->then($resolve, $reject, $notify);
            }, $canceller);
    }

    /** @var $promiseOrValue mixed */
    return isset($sharedData)
        ? new FulfilledPromiseWithSD($promiseOrValue, $sharedData)
        : new FulfilledPromise($promiseOrValue);
}

/**
 * @param mixed $promiseOrValue
 * @return LazyPromiseInterface
 */
function resolveLazy($promiseOrValue = null) {
    return new LazyPromise(function() use($promiseOrValue) {
        return resolve($promiseOrValue);
    });
}

/**
 * @param PromiseInterface|mixed $promiseOrValue
 * @param SharedDataInterface|null $sharedData
 * @return ExtendedPromiseInterface
 */
function reject($promiseOrValue = null, SharedDataInterface $sharedData = null)
{
    if ($promiseOrValue instanceof PromiseInterface) {
        return resolve($promiseOrValue, $sharedData)->then(function ($value, $sharedData = null) {
            return isset($sharedData)
                ? new RejectedPromiseWithSD($value, $sharedData)
                : new RejectedPromise($value);
        });
    }

    return isset($sharedData)
        ? new RejectedPromiseWithSD($promiseOrValue, $sharedData)
        : new RejectedPromise($promiseOrValue);
}

/**
 * @param mixed $promiseOrValue
 * @return LazyPromiseInterface
 */
function rejectLazy($promiseOrValue = null) {
    return new LazyPromise(function() use($promiseOrValue) {
        return reject($promiseOrValue);
    });
}

/**
 * Returns promise which will be resolved when all given promises resolves
 * and rejected when at least one will reject be rejected
 * @param PromiseInterface[]|mixed[] $promisesOrValues
 * @return ExtendedPromiseInterface
 */
function all($promisesOrValues)
{
    return map($promisesOrValues, function ($val) {
        return $val;
    });
}

/**
 * Returns promise which will be resolved when all given promises resolves
 * and rejected when at least one will reject be rejected
 * Promises will be resolved in given order one by one,
 * so the giving LazyPromises array is the best way for this function
 * @param PromiseInterface[]|mixed[] $promisesOrValues
 * @return ExtendedPromiseInterface
 */
function allInOrder($promisesOrValues) {
    if (!is_array($promisesOrValues)) {
        $promisesOrValues = [$promisesOrValues];
    }
    $queue = new OrderedExecutionQueue();
    $queue->enqueueMultiple($promisesOrValues);
    return $queue();
}

/**
 * @param PromiseInterface[]|mixed[] $promisesOrValues
 * @return ExtendedPromiseInterface
 */
function race($promisesOrValues)
{
    $cancellationQueue = new CancellationQueue();
    $cancellationQueue->enqueue($promisesOrValues);

    return new Promise(function ($resolve, $reject, $notify) use ($promisesOrValues, $cancellationQueue) {
        resolve($promisesOrValues)
            ->done(function ($array) use ($cancellationQueue, $resolve, $reject, $notify) {
                if (!is_array($array) || empty($array)) {
                    $resolve();
                    return;
                }

                foreach ($array as $promiseOrValue) {
                    $cancellationQueue->enqueue($promiseOrValue);

                    resolve($promiseOrValue)
                        ->done($resolve, $reject, $notify);
                }
            }, $reject, $notify);
    }, $cancellationQueue);
}

/**
 * @param PromiseInterface[]|mixed[] $promisesOrValues
 * @return ExtendedPromiseInterface
 */
function any($promisesOrValues)
{
    return some($promisesOrValues, 1)
        ->then(function ($val) {
            return array_shift($val);
        });
}

/**
 * @param PromiseInterface[]|mixed[] $promisesOrValues
 * @param integer $howMany
 * @return ExtendedPromiseInterface
 */
function some($promisesOrValues, $howMany)
{
    $cancellationQueue = new CancellationQueue();
    $cancellationQueue->enqueue($promisesOrValues);

    return new Promise(function ($resolve, $reject, $notify) use ($promisesOrValues, $howMany, $cancellationQueue) {
        resolve($promisesOrValues)
            ->done(function ($array) use ($howMany, $cancellationQueue, $resolve, $reject, $notify) {
                if (!is_array($array) || $howMany < 1) {
                    $resolve([]);
                    return;
                }

                $len = count($array);

                if ($len < $howMany) {
                    throw new \React\Promise\Exception\LengthException(
                        sprintf(
                            'Input array must contain at least %d item%s but contains only %s item%s.',
                            $howMany,
                            1 === $howMany ? '' : 's',
                            $len,
                            1 === $len ? '' : 's'
                        )
                    );
                }

                $toResolve = $howMany;
                $toReject  = ($len - $toResolve) + 1;
                $values    = [];
                $reasons   = [];

                foreach ($array as $i => $promiseOrValue) {
                    $fulfiller = function ($val) use ($i, &$values, &$toResolve, $toReject, $resolve) {
                        if ($toResolve < 1 || $toReject < 1) {
                            return;
                        }

                        $values[$i] = $val;

                        if (0 === --$toResolve) {
                            $resolve($values);
                        }
                    };

                    $rejecter = function ($reason) use ($i, &$reasons, &$toReject, $toResolve, $reject) {
                        if ($toResolve < 1 || $toReject < 1) {
                            return;
                        }

                        $reasons[$i] = $reason;

                        if (0 === --$toReject) {
                            $reject($reasons);
                        }
                    };

                    $cancellationQueue->enqueue($promiseOrValue);

                    resolve($promiseOrValue)
                        ->done($fulfiller, $rejecter, $notify);
                }
            }, $reject, $notify);
    }, $cancellationQueue);
}

/**
 * @param PromiseInterface[]|mixed[] $promisesOrValues
 * @param callable $mapFunc
 * @return ExtendedPromiseInterface
 */
function map($promisesOrValues, callable $mapFunc)
{
    $cancellationQueue = new CancellationQueue();
    $cancellationQueue->enqueue($promisesOrValues);

    return new Promise(function ($resolve, $reject, $notify) use ($promisesOrValues, $mapFunc, $cancellationQueue) {
        resolve($promisesOrValues)
            ->done(function ($array) use ($mapFunc, $cancellationQueue, $resolve, $reject, $notify) {
                if (!is_array($array) || empty($array)) {
                    $resolve([]);
                    return;
                }

                $toResolve = count($array);
                $values    = [];

                foreach ($array as $i => $promiseOrValue) {
                    $cancellationQueue->enqueue($promiseOrValue);
                    $values[$i] = null;

                    resolve($promiseOrValue)
                        ->then($mapFunc)
                        ->done(
                            function ($mapped) use ($i, &$values, &$toResolve, $resolve) {
                                $values[$i] = $mapped;

                                if (0 === --$toResolve) {
                                    $resolve($values);
                                }
                            },
                            $reject,
                            $notify
                        );
                }
            }, $reject, $notify);
    }, $cancellationQueue);
}

/**
 * @param PromiseInterface[]|mixed[] $promisesOrValues
 * @param callable $reduceFunc
 * @param mixed|null $initialValue
 * @return ExtendedPromiseInterface
 */
function reduce($promisesOrValues, callable $reduceFunc, $initialValue = null)
{
    $cancellationQueue = new CancellationQueue();
    $cancellationQueue->enqueue($promisesOrValues);

    return new Promise(function ($resolve, $reject, $notify) use ($promisesOrValues, $reduceFunc, $initialValue, $cancellationQueue) {
        resolve($promisesOrValues)
            ->done(function ($array) use ($reduceFunc, $initialValue, $cancellationQueue, $resolve, $reject, $notify) {
                if (!is_array($array)) {
                    $array = [];
                }

                $total = count($array);
                $i = 0;

                // Wrap the supplied $reduceFunc with one that handles promises and then
                // delegates to the supplied.
                $wrappedReduceFunc = function ($current, $val) use ($reduceFunc, $cancellationQueue, $total, &$i) {
                    $cancellationQueue->enqueue($val);
                    /** @var PromiseInterface $current */
                    return $current
                        ->then(function ($c) use ($reduceFunc, $total, &$i, $val) {
                            return resolve($val)
                                ->then(function ($value) use ($reduceFunc, $total, &$i, $c) {
                                    return $reduceFunc($c, $value, $i++, $total);
                                });
                        });
                };

                $cancellationQueue->enqueue($initialValue);

                array_reduce($array, $wrappedReduceFunc, resolve($initialValue))
                    ->done($resolve, $reject, $notify);
            }, $reject, $notify);
    }, $cancellationQueue);
}

// Internal functions

/**
 * @param callable $callback
 * @param mixed    $object
 * @return bool
 * @internal
 */
function _checkTypehint(callable $callback, $object)
{
    if (!is_object($object)) {
        return true;
    }

    if (is_array($callback)) {
        $callbackReflection = new \ReflectionMethod($callback[0], $callback[1]);
    } elseif (is_object($callback) && !$callback instanceof \Closure) {
        $callbackReflection = new \ReflectionMethod($callback, '__invoke');
    } else {
        $callbackReflection = new \ReflectionFunction($callback);
    }

    $parameters = $callbackReflection->getParameters();

    if (!isset($parameters[0])) {
        return true;
    }

    $expectedException = $parameters[0];

    if (!$expectedException->getClass()) {
        return true;
    }

    return $expectedException->getClass()->isInstance($object);
}

/**
 * Merge shared data
 * @param SharedDataInterface $data
 * @param SharedDataInterface $data2
 * @param bool $reassign
 * @internal
 */
function _mergeSharedData(&$data, &$data2, $reassign = false) {
    if ($data === null || $data2 === null) {
        return;
    }
    foreach ($data2 as $key => $value) {
        echo $key . "\n";
        $data->addData($value, $key);
    }
    if ($reassign) {
        $data2 = $data;
    }
}
