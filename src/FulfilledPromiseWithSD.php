<?php

namespace Reaction\Promise;

/**
 * Class FulfilledPromiseWithSD
 * @package Reaction\Promise
 */
class FulfilledPromiseWithSD extends FulfilledPromise
{
    /** @var SharedDataInterface */
    public  $sharedData;

    /**
     * FulfilledPromiseWithSD constructor.
     * @param mixed                    $value
     * @param SharedDataInterface|null $sharedData
     */
    public function __construct($value = null, SharedDataInterface $sharedData = null)
    {
        if (isset($sharedData)) {
            $this->sharedData = $sharedData;
        }

        parent::__construct($value);
    }


    /**
     * @inheritdoc
     */
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

    /**
     * @inheritdoc
     */
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

    /**
     * @inheritdoc
     */
    public function always(callable $onFulfilledOrRejected)
    {
        return $this->then(function ($value) use ($onFulfilledOrRejected) {
            return resolve($onFulfilledOrRejected($this->sharedData))->then(function () use ($value) {
                return $value;
            });
        });
    }
}
