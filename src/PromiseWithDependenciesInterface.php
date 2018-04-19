<?php

namespace Reaction\Promise;

use React\Promise\ExtendedPromiseInterface;

/**
 * Interface PromiseWithDependencies
 * @package Reaction\Promise
 * @property ChainDependencyInterface $chainDependency
 */
interface PromiseWithDependenciesInterface extends ExtendedPromiseInterface
{

}