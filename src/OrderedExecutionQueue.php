<?php

namespace Reaction\Promise;

use React\Promise\CancellablePromiseInterface;
use React\Promise\CancellationQueue;
use React\Promise\PromiseInterface;

/**
 * Class OrderedExecutionQueue
 * @package Reaction\Promise
 */
class OrderedExecutionQueue
{
    /** @var PromiseInterface[] */
    protected $queue = [];
    /** @var mixed[] */
    protected $results = [];
    /** @var int Iterator for result keys */
    protected $iterator = 0;
    /** @var CancellationQueue to cancel promise */
    protected $cancellationQueue;

    /** @var ExtendedPromiseInterface|CancellablePromiseInterface */
    protected $promise;
    /** @var callable|null */
    protected $resolve;
    /** @var callable|null */
    protected $reject;
    /** @var callable|null */
    protected $notify;

    /**
     * Invocation callback
     * @return ExtendedPromiseInterface
     */
    public function __invoke()
    {
        if (isset($this->promise)) {
            return $this->promise;
        }
        $this->createCancellationQueue();
        return $this->process();
    }

    /**
     * Add promise to queue
     * @param PromiseInterface $promise
     */
    public function enqueue($promise) {
        $this->queue[] = $promise;
    }

    /**
     * Add multiple promises to queue
     * @param PromiseInterface[] $promises
     */
    public function enqueueMultiple($promises = []) {
        foreach ($promises as $promise) {
            $this->enqueue($promise);
        }
    }

    /**
     * Process queue
     * @return ExtendedPromiseInterface
     */
    public function process() {
        $this->promise = new Promise(function($r, $c, $n) {
            $this->resolve = $r;
            $this->createRejectFunction($c);
            $this->notify = $n;
            $this->executionCallback(null, true);
        });

        return $this->promise;
    }

    /**
     * Promise ::then() resolve callback
     * @param mixed $value
     * @param bool  $firstRun
     */
    protected function executionCallback($value, $firstRun = false) {
        if (!$firstRun) {
            $this->results[$this->iterator] = $value;
            $this->iterator++;
        }
        if (empty($this->queue)) {
            $resolve = $this->resolve;
            $resolve($this->results);
            return;
        }
        $promise = $this->next();
        $thenFunc = function($value) {
            return call_user_func([$this, 'executionCallback'], $value);
        };
        $promise->done($thenFunc, $this->reject, $this->notify);
    }

    /**
     * Get next promise from queue
     * @return ExtendedPromiseInterface
     */
    protected function next()
    {
        $promiseOrValue = array_shift($this->queue);
        $promise = resolve($promiseOrValue);
        $this->cancellationQueue->enqueue($promise);
        return $promise;
    }

    /**
     * Create cancellation queue
     */
    protected function createCancellationQueue()
    {
        $this->cancellationQueue = new CancellationQueue();
    }

    /**
     * Create reject function with cancellation queue
     * @param callable|null $reject
     */
    protected function createRejectFunction($reject)
    {
        $this->reject = function($reason) use (&$reject) {
            $cancelQueue = $this->cancellationQueue;
            $cancelQueue();
            return isset($reject) ? $reject($reason) : null;
        };
    }
}