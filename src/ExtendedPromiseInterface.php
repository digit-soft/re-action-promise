<?php

namespace Reaction\Promise;

/**
 * Interface ExtendedPromiseInterface
 * @package Reaction\Promise
 */
interface ExtendedPromiseInterface extends \React\Promise\ExtendedPromiseInterface
{
    /**
     * @param callable|null $onFulfilled
     * @param callable|null $onRejected
     * @param callable|null $onProgress
     * @return ExtendedPromiseInterface
     */
    public function then(callable $onFulfilled = null, callable $onRejected = null, callable $onProgress = null);

    /**
     * @param callable $onRejected
     * @return ExtendedPromiseInterface
     */
    public function otherwise(callable $onRejected);

    /**
     * @param callable $onFulfilledOrRejected
     * @return ExtendedPromiseInterface
     */
    public function always(callable $onFulfilledOrRejected);

    /**
     * @param callable $onProgress
     * @return ExtendedPromiseInterface
     * @deprecated 2.6.0 Progress support is deprecated and should not be used anymore.
     */
    public function progress(callable $onProgress);
}