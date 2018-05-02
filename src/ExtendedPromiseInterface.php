<?php
/**
 * Created by coder1.
 * Date: 02.05.18
 * Time: 14:28
 */

namespace Reaction\Promise;


interface ExtendedPromiseInterface extends \React\Promise\ExtendedPromiseInterface
{
    /**
     * @param callable|null $onFulfilled
     * @param callable|null $onRejected
     * @param callable|null $onProgress
     * @return ExtendedPromiseInterface
     */
    public function then(callable $onFulfilled = null, callable $onRejected = null, callable $onProgress = null);
}