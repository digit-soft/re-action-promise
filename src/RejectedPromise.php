<?php

namespace Reaction\Promise;

use React\Promise\CancellablePromiseInterface;
use React\Promise\PromiseInterface;
use React\Promise\UnhandledRejectionException;

/**
 * Class RejectedPromise
 * @package Reaction\Promise
 */
class RejectedPromise implements ExtendedPromiseInterface, CancellablePromiseInterface
{
    /** @var \Throwable|mixed */
    protected $reason;

    /**
     * RejectedPromise constructor.
     * @param \Throwable|mixed $reason
     */
    public function __construct($reason = null)
    {
        if ($reason instanceof PromiseInterface) {
            throw new \InvalidArgumentException('You cannot create Reaction\Promise\RejectedPromise with a promise. Use Reaction\Promise\reject($promiseOrValue) instead.');
        }

        $this->reason = $reason;
    }

    /**
     * Transform promise
     * @param callable|null $onFulfilled
     * @param callable|null $onRejected
     * @param callable|null $onProgress
     * @return ExtendedPromiseInterface
     */
    public function then(callable $onFulfilled = null, callable $onRejected = null, callable $onProgress = null)
    {
        if (null === $onRejected) {
            return $this;
        }

        try {
            return resolve($onRejected($this->reason));
        } catch (\Throwable $exception) {
            return new static($exception);
        }
    }

    /**
     * @param callable|null $onFulfilled
     * @param callable|null $onRejected
     * @param callable|null $onProgress
     */
    public function done(callable $onFulfilled = null, callable $onRejected = null, callable $onProgress = null)
    {
        if (null === $onRejected) {
            throw UnhandledRejectionException::resolve($this->reason);
        }

        $result = $onRejected($this->reason);

        if ($result instanceof self) {
            throw UnhandledRejectionException::resolve($result->reason);
        }

        if ($result instanceof ExtendedPromiseInterface) {
            $result->done();
        }
    }

    /**
     * Shortcut to ::then(null, $onRejected)
     * @param callable $onRejected
     * @return ExtendedPromiseInterface
     */
    public function otherwise(callable $onRejected)
    {
        if (!_checkTypehint($onRejected, $this->reason)) {
            return $this;
        }

        return $this->then(null, $onRejected);
    }

    /**
     * Always executable callback
     * @param callable $onFulfilledOrRejected
     * @return ExtendedPromiseInterface
     */
    public function always(callable $onFulfilledOrRejected)
    {
        return $this->then(null, function ($reason) use ($onFulfilledOrRejected) {
            return resolve($onFulfilledOrRejected())->then(function () use ($reason) {
                return new static($reason);
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
