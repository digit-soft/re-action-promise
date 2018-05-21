<?php

namespace Reaction\Promise;

use React\Promise\CancellablePromiseInterface;
use React\Promise\PromiseInterface;

/**
 * Class FulfilledPromise
 * @package Reaction\Promise
 */
class FulfilledPromise implements CancellablePromiseInterface, PromiseWithSharedDataInterface
{
    /** @var mixed resolved value */
    protected $value;

    /**
     * FulfilledPromise constructor.
     * @param mixed $value Resolved value
     */
    public function __construct($value = null)
    {
        if ($value instanceof PromiseInterface) {
            throw new \InvalidArgumentException('You cannot create Reaction\Promise\FulfilledPromise with a promise. Use Reaction\Promise\resolve($promiseOrValue) instead.');
        }

        $this->value = $value;
    }

    /**
     * @param callable|null $onFulfilled
     * @param callable|null $onRejected
     * @param callable|null $onProgress
     * @return ExtendedPromiseInterface
     */
    public function then(callable $onFulfilled = null, callable $onRejected = null, callable $onProgress = null)
    {
        if (null === $onFulfilled) {
            return $this;
        }

        try {
            return resolve($onFulfilled($this->value));
        } catch (\Throwable $exception) {
            return new RejectedPromise($exception);
        }
    }

    /**
     * @param callable|null $onFulfilled
     * @param callable|null $onRejected
     * @param callable|null $onProgress
     */
    public function done(callable $onFulfilled = null, callable $onRejected = null, callable $onProgress = null)
    {
        if (null === $onFulfilled) {
            return;
        }

        $result = $onFulfilled($this->value);

        if ($result instanceof ExtendedPromiseInterface) {
            $result->done();
        }
    }

    /**
     * Dummy method for compatibility
     * @param callable $onRejected
     * @return ExtendedPromiseInterface
     */
    public function otherwise(callable $onRejected)
    {
        return $this;
    }

    /**
     * @param callable $onFulfilledOrRejected
     * @return ExtendedPromiseInterface
     */
    public function always(callable $onFulfilledOrRejected)
    {
        return $this->then(function ($value) use ($onFulfilledOrRejected) {
            return resolve($onFulfilledOrRejected())->then(function () use ($value) {
                return $value;
            });
        });
    }

    /**
     * Dummy method for compatibility
     * @param callable $onProgress
     * @return ExtendedPromiseInterface
     */
    public function progress(callable $onProgress)
    {
        return $this;
    }

    /**
     * Dummy method for compatibility
     */
    public function cancel()
    {
    }
}
