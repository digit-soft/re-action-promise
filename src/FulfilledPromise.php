<?php

namespace Reaction\Promise;

use React\Promise\CancellablePromiseInterface;
use React\Promise\ExtendedPromiseInterface;
use React\Promise\PromiseInterface;

/**
 * Class FulfilledPromise
 * @package Reaction\Promise
 */
class FulfilledPromise implements ExtendedPromiseInterface, CancellablePromiseInterface, PromiseWithDependenciesInterface
{
    private $value;

    /** @var ChainDependencyInterface */
    public  $chainDependency;

    public function __construct($value = null, ChainDependencyInterface $chainDependency = null)
    {
        if ($value instanceof PromiseInterface) {
            throw new \InvalidArgumentException('You cannot create React\Promise\FulfilledPromise with a promise. Use React\Promise\resolve($promiseOrValue) instead.');
        }

        if (isset($chainDependency)) {
            $this->chainDependency = $chainDependency;
        }

        $this->value = $value;
    }

    public function then(callable $onFulfilled = null, callable $onRejected = null, callable $onProgress = null)
    {
        if (null === $onFulfilled) {
            return $this;
        }

        try {
            return resolve($onFulfilled($this->value, $this->chainDependency), $this->chainDependency);
        } catch (\Throwable $exception) {
            return new RejectedPromise($exception, $this->chainDependency);
        }
    }

    public function done(callable $onFulfilled = null, callable $onRejected = null, callable $onProgress = null)
    {
        if (null === $onFulfilled) {
            return;
        }

        $result = $onFulfilled($this->value, $this->chainDependency);

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
            return resolve($onFulfilledOrRejected($self->chainDependency))->then(function () use ($value) {
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
