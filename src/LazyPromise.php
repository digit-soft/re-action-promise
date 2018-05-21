<?php

namespace Reaction\Promise;

use React\Promise\CancellablePromiseInterface;

/**
 * Class LazyPromise
 * @package Reaction\Promise
 */
class LazyPromise implements ExtendedPromiseInterface, CancellablePromiseInterface
{
    protected $factory;
    protected $promise;

    /**
     * LazyPromise constructor.
     * @param callable $factory
     */
    public function __construct(callable $factory)
    {
        $this->factory = $factory;
    }

    /**
     * @param callable|null $onFulfilled
     * @param callable|null $onRejected
     * @param callable|null $onProgress
     * @return ExtendedPromiseInterface
     * @see ExtendedPromiseInterface::then()
     */
    public function then(callable $onFulfilled = null, callable $onRejected = null, callable $onProgress = null)
    {
        return $this->promise()->then($onFulfilled, $onRejected, $onProgress);
    }

    /**
     * @param callable|null $onFulfilled
     * @param callable|null $onRejected
     * @param callable|null $onProgress
     * @see ExtendedPromiseInterface::done()
     */
    public function done(callable $onFulfilled = null, callable $onRejected = null, callable $onProgress = null)
    {
        return $this->promise()->done($onFulfilled, $onRejected, $onProgress);
    }

    /**
     * Shortcut to ::then(null, $onRejected)
     * @param callable $onRejected
     * @return ExtendedPromiseInterface
     * @see ExtendedPromiseInterface::otherwise()
     */
    public function otherwise(callable $onRejected)
    {
        return $this->promise()->otherwise($onRejected);
    }

    /**
     * Always performed action on fulfilled or rejected
     * @param callable $onFulfilledOrRejected
     * @return ExtendedPromiseInterface
     * @see ExtendedPromiseInterface::always()
     */
    public function always(callable $onFulfilledOrRejected)
    {
        return $this->promise()->always($onFulfilledOrRejected);
    }

    /**
     * @param callable $onProgress
     * @return ExtendedPromiseInterface
     * @see ExtendedPromiseInterface::progress()
     */
    public function progress(callable $onProgress)
    {
        return $this->promise()->progress($onProgress);
    }

    /**
     * Cancel promise
     * @see CancellablePromiseInterface::cancel()
     */
    public function cancel()
    {
        return $this->promise()->cancel();
    }

    /**
     * @internal
     * @see Promise::settle()
     * @return ExtendedPromiseInterface|CancellablePromiseInterface
     */
    public function promise()
    {
        if (null === $this->promise) {
            try {
                $this->promise = resolve(call_user_func($this->factory));
            } catch (\Throwable $exception) {
                $this->promise = new RejectedPromise($exception);
            }
        }

        return $this->promise;
    }
}