<?php

namespace Reaction\Promise;

use React\Promise\ExtendedPromiseInterface;

/**
 * Interface PromiseWithDependencies. Created just to mark Promises with shared data.
 * @package Reaction\Promise
 * @property ChainDependencyInterface $chainDependency
 */
interface PromiseWithSharedDataInterface extends ExtendedPromiseInterface
{

}