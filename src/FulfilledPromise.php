<?php

namespace Reaction\Promise;

use React\Promise\CancellablePromiseInterface;
use React\Promise\ExtendedPromiseInterface;
use React\Promise\PromiseInterface;

/**
 * Class FulfilledPromise
 * @package Reaction\Promise
 */
class FulfilledPromise implements ExtendedPromiseInterface, CancellablePromiseInterface, PromiseWithSharedDataInterface
{
    private $value;

    /** @var SharedDataInterface */
    public  $sharedData;

    public function __construct($value = null, SharedDataInterface $sharedData = null)
    {
        if ($value instanceof PromiseInterface) {
            throw new \InvalidArgumentException('You cannot create React\Promise\FulfilledPromise with a promise. Use React\Promise\resolve($promiseOrValue) instead.');
        }

        if (isset($sharedData)) {
            $this->sharedData = $sharedData;
        }

        $this->value = $value;
    }

    public function then(callable $onFulfilled = null, callable $onRejected = null, callable $onProgress = null)
    {
        if (null === $onFulfilled) {
            return $this;
        }

        try {
            return resolve($onFulfilled($this->value, $this->sharedData), $this->sharedData);
        } catch (\Throwable $exception) {
            return new RejectedPromise($exception, $this->sharedData);
        }
    }

    public function done(callable $onFulfilled = null, callable $onRejected = null, callable $onProgress = null)
    {
        if (null === $onFulfilled) {
            return;
        }

        $result = $onFulfilled($this->value, $this->sharedData);

        if ($result instanceof ExtendedPromiseInterface) {
            $result->done();
        }
    }

    public function otherwise(callable $onRejected)
    {
        return $this;
    }

    public function always(callable $onFulfilledOrRejected)
    {
        $self = $this;
        return $this->then(function ($value) use ($onFulfilledOrRejected, $self) {
            return resolve($onFulfilledOrRejected($self->sharedData))->then(function () use ($value) {
                return $value;
            });
        });
    }

    public function progress(callable $onProgress)
    {
        return $this;
    }

    public function cancel()
    {
    }
}
