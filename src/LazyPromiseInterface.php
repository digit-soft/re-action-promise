<?php

namespace Reaction\Promise;

/**
 * Interface LazyPromiseInterface
 * @package Reaction\Promise
 */
interface LazyPromiseInterface extends ExtendedPromiseInterface
{
    /**
     * Lazy variant of ::then()
     * @param callable|null $onFulfilled
     * @param callable|null $onRejected
     * @param callable|null $onProgress
     * @return LazyPromiseInterface
     * @see ExtendedPromiseInterface::then()
     */
    public function thenLazy(callable $onFulfilled = null, callable $onRejected = null, callable $onProgress = null);

    /**
     * Lazy variant of ::always()
     * @param callable $onFulfilledOrRejected
     * @return LazyPromiseInterface
     * @see ExtendedPromiseInterface::always()
     */
    public function alwaysLazy(callable $onFulfilledOrRejected);

    /**
     * Lazy variant of ::otherwise()
     * @param callable $onRejected
     * @return LazyPromiseInterface
     * @see ExtendedPromiseInterface::otherwise()
     */
    public function otherwiseLazy(callable $onRejected);
}