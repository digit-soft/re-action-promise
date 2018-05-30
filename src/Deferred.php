<?php

namespace Reaction\Promise;

use React\Promise\PromisorInterface;

/**
 * Class Deferred. Created to work with \Reaction\Promise\Promise
 * @package Reaction\Promise
 */
class Deferred implements PromisorInterface
{
    protected $promise;
    protected $resolveCallback;
    protected $rejectCallback;
    protected $notifyCallback;
    protected $canceller;

    /**
     * Deferred constructor.
     * @param callable|null $canceller
     */
    public function __construct(callable $canceller = null)
    {
        $this->canceller = $canceller;
    }

    /**
     * Create promise if needed
     * @return ExtendedPromiseInterface
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

    /**
     * Resolve deferred promise
     * @param mixed $value
     */
    public function resolve($value = null)
    {
        $this->promise();

        call_user_func($this->resolveCallback, $value);
    }

    /**
     * Reject deferred promise
     * @param mixed $reason
     */
    public function reject($reason = null)
    {
        $this->promise();

        call_user_func($this->rejectCallback, $reason);
    }

    /**
     * Notify callback.
     * Warning! Deprecated.
     * @param mixed $update
     */
    public function notify($update = null)
    {
        $this->promise();

        call_user_func($this->notifyCallback, $update);
    }

    /**
     * Progress callback.
     * Warning! Deprecated.
     * @see Deferred::notify()
     * @param mixed $update
     */
    public function progress($update = null)
    {
        $this->notify($update);
    }
}