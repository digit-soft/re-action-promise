<?php

namespace Reaction\Promise;


interface ChainDependencyInterface
{
    const SCENARIO_WRITE_ONCE   = 'write_once';
    const SCENARIO_OVERWRITE    = 'write_overwrite';
    const SCENARIO_MERGE        = 'write_merge';

    const TYPE_DEFINED_ONLY     = 'defined';
    const TYPE_ARBITRARY        = 'arbitrary';

    /**
     * Add dependency
     * @param mixed  $value
     * @param string $key
     * @return ChainDependency
     */
    public function addDependency($value, $key = null);

    /**
     * Get dependency by key
     * @param string $key
     * @param null   $defaultValue
     * @return mixed|null
     */
    public function getDependency($key, $defaultValue = null);

    /**
     * Check that dependency is set
     * @param string $key
     * @return bool
     */
    public function hasDependency($key);

    /**
     * Set dependencies map.
     * That creates mapped dependency with type static::TYPE_DEFINED_ONLY
     * @param array $map
     * @return $this
     */
    public function setMap($map = []);

    /**
     * Flush dependencies
     * @return $this
     */
    public function flush();


    /**
     * Set scenario to static::SCENARIO_OVERWRITE
     * @return ChainDependency
     */
    public function scenarioOverwrite();

    /**
     * Set scenario to static::SCENARIO_WRITE_ONCE
     * @return ChainDependency
     */
    public function scenarioWriteOnce();

    /**
     * Set scenario to static::SCENARIO_MERGE
     * @return ChainDependency
     */
    public function scenarioMerge();

    /**
     * Set scenario
     * @param string $scenario
     * @return ChainDependency
     */
    public function setScenario($scenario);

    /**
     * Set type to static::TYPE_ARBITRARY
     * @return ChainDependency
     */
    public function typeArbitrary();

    /**
     * Set type to static::TYPE_DEFINED_ONLY
     * @return ChainDependency
     */
    public function typeDefinedOnly();

    /**
     * Set type
     * @param string $type
     * @return ChainDependency
     */
    public function setType($type);


    /**
     * Get dependency from function arguments chain instance
     * @param array $arguments
     * @param string $key
     * @param mixed $defaultValue
     * @return mixed|null
     */
    public static function getDependencyFromArgs($arguments = [], $key = null, $defaultValue = null);

    /**
     * Add dependency to chain instance from function arguments
     * @param array  $arguments
     * @param mixed  $value
     * @param string $key
     * @return ChainDependencyInterface|null
     */
    public static function addDependencyToArgs($arguments = [], $value, $key = null);

    /**
     * Get instance from function arguments
     * @param array $arguments
     * @return ChainDependencyInterface|null
     */
    public static function instanceFromArguments($arguments = []);
}