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
     * @return ExtendedPromiseInterface
     * @see ExtendedPromiseInterface::then()
     */
    public function thenLazy(callable $onFulfilled = null, callable $onRejected = null, callable $onProgress = null);

    /**
     * Lazy variant of ::always()
     * @param callable $onFulfilledOrRejected
     * @return ExtendedPromiseInterface
     * @see ExtendedPromiseInterface::always()
     */
    public function alwaysLazy(callable $onFulfilledOrRejected);

    /**
     * Lazy variant of ::otherwise()
     * @param callable $onRejected
     * @return ExtendedPromiseInterface
     * @see ExtendedPromiseInterface::otherwise()
     */
    public function otherwiseLazy(callable $onRejected);
}