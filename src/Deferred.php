<?php

namespace Reaction\Promise;

/**
 * Class Deferred. Created to work with \Reaction\Promise\Promise
 * @package Reaction\Promise
 */
class Deferred extends \React\Promise\Deferred
{
    private $promise;
    private $resolveCallback;
    private $rejectCallback;
    private $notifyCallback;
    private $canceller;

    /**
     * Create promise if needed
     * @return PromiseWithSharedDataInterface
     */
    public function promise()
    {
        if (null === $this->promise) {
            $this->promise = new Promise(function ($resolve, $reject, $notify) {
                $this->resolveCallback = $resolve;
                $this->rejectCallback  = $reject;
                $this->notifyCallback  = $notify;
            }, $this->canceller);
        }

        return $this->promise;
    }
}