<?php

namespace Reaction\Promise;

use React\Promise\UnhandledRejectionException;

/**
 * Class RejectedPromiseWithSD
 * @package Reaction\Promise
 */
class RejectedPromiseWithSD extends RejectedPromise implements PromiseWithSharedDataInterface
{
    /** @var SharedDataInterface */
    public  $sharedData;

    /**
     * RejectedPromiseWithSD constructor.
     * @param \Throwable|mixed         $reason
     * @param SharedDataInterface|null $sharedData
     */
    public function __construct($reason = null, SharedDataInterface $sharedData = null)
    {
        if (isset($sharedData)) {
            $this->sharedData = $sharedData;
        }
        parent::__construct($reason);
    }

    /**
     * @inheritdoc
     */
    public function then(callable $onFulfilled = null, callable $onRejected = null, callable $onProgress = null)
    {
        if (null === $onRejected) {
            return $this;
        }

        try {
            return resolve($onRejected($this->reason, $this->sharedData));
        } catch (\Throwable $exception) {
            return new static($exception, $this->sharedData);
        }
    }

    /**
     * @inheritdoc
     */
    public function done(callable $onFulfilled = null, callable $onRejected = null, callable $onProgress = null)
    {
        if (null === $onRejected) {
            throw UnhandledRejectionException::resolve($this->reason);
        }

        $result = $onRejected($this->reason, $this->sharedData);

        if ($result instanceof self) {
            throw UnhandledRejectionException::resolve($result->reason);
        }

        if ($result instanceof ExtendedPromiseInterface) {
            $result->done();
        }
    }

    /**
     * @inheritdoc
     */
    public function always(callable $onFulfilledOrRejected)
    {
        $self = $this;
        return $this->then(null, function ($reason) use ($onFulfilledOrRejected, $self) {
            return resolve($onFulfilledOrRejected($self->sharedData))->then(function () use ($reason, $self) {
                return new static($reason, $self->sharedData);
            });
        });
    }
}