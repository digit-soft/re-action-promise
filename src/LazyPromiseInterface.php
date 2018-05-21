<?php

namespace Reaction\Promise;

/**
 * Interface LazyPromiseInterface
 * @package Reaction\Promise
 */
interface LazyPromiseInterface
{
    /**
     * Lazy variant of ::then()
     * @param callable|null $onFulfilled
     * @param callable|null $onRejected
     * @param callable|null $onProgress
     * @return LazyPromise
     * @see ExtendedPromiseInterface::then()
     */
    public function thenLazy(callable $onFulfilled = null, callable $onRejected = null, callable $onProgress = null);

    /**
     * Lazy variant of ::always()
     * @param callable $onFulfilledOrRejected
     * @return LazyPromise
     * @see ExtendedPromiseInterface::always()
     */
    public function alwaysLazy(callable $onFulfilledOrRejected);

    /**
     * Lazy variant of ::otherwise()
     * @param callable $onRejected
     * @return LazyPromise
     * @see ExtendedPromiseInterface::otherwise()
     */
    public function otherwiseLazy(callable $onRejected);
}