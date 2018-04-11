<?php

namespace Reaction\Promise;

use React\Promise\CancellablePromiseInterface;
use React\Promise\ExtendedPromiseInterface;
use React\Promise\PromiseInterface;
use React\Promise\UnhandledRejectionException;

class RejectedPromise implements ExtendedPromiseInterface, CancellablePromiseInterface, PromiseWithDependenciesInterface
{
    private $reason;

    /** @var ChainDependencyInterface */
    public  $chainDependency;

    public function __construct($reason = null, ChainDependencyInterface $chainDependency = null)
    {
        if ($reason instanceof PromiseInterface) {
            throw new \InvalidArgumentException('You cannot create React\Promise\RejectedPromise with a promise. Use React\Promise\reject($promiseOrValue) instead.');
        }

        if(isset($chainDependency)) $this->chainDependency = $chainDependency;

        $this->reason = $reason;
    }

    public function then(callable $onFulfilled = null, callable $onRejected = null, callable $onProgress = null)
    {
        if (null === $onRejected) {
            return $this;
        }

        try {
            return resolve($onRejected($this->reason, $this->chainDependency));
        } catch (\Throwable $exception) {
            return new RejectedPromise($exception, $this->chainDependency);
        } catch (\Exception $exception) {
            return new RejectedPromise($exception, $this->chainDependency);
        }
    }

    public function done(callable $onFulfilled = null, callable $onRejected = null, callable $onProgress = null)
    {
        if (null === $onRejected) {
            throw UnhandledRejectionException::resolve($this->reason);
        }

        $result = $onRejected($this->reason, $this->chainDependency);

        if ($result instanceof self) {
            throw UnhandledRejectionException::resolve($result->reason);
        }

        if ($result instanceof ExtendedPromiseInterface) {
            $result->done();
        }
    }

    public function otherwise(callable $onRejected)
    {
        if (!_checkTypehint($onRejected, $this->reason)) {
            return $this;
        }

        return $this->then(null, $onRejected);
    }

    public function always(callable $onFulfilledOrRejected)
    {
        $self = $this;
        return $this->then(null, function ($reason) use ($onFulfilledOrRejected, $self) {
            return resolve($onFulfilledOrRejected($self->chainDependency))->then(function () use ($reason, $self) {
                return new RejectedPromise($reason, $self->chainDependency);
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
