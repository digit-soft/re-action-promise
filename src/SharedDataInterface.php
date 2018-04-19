<?php

namespace Reaction\Promise;

/**
 * Interface SharedDataInterface
 * @package Reaction\Promise
 */
interface SharedDataInterface
{
    const SCENARIO_WRITE_ONCE   = 'write_once';
    const SCENARIO_OVERWRITE    = 'write_overwrite';
    const SCENARIO_MERGE        = 'write_merge';

    const TYPE_DEFINED_ONLY     = 'defined';
    const TYPE_ARBITRARY        = 'arbitrary';

    /**
     * Add data
     * @param mixed  $value
     * @param string $key
     * @return SharedDataInterface
     */
    public function addData($value, $key = null);

    /**
     * Get data by key
     * @param string $key
     * @param mixed  $defaultValue
     * @return mixed|null
     */
    public function getData($key, $defaultValue = null);

    /**
     * Check that data is set
     * @param string $key
     * @return bool
     */
    public function hasData($key);

    /**
     * Set data map.
     * That creates mapped data storage with type static::TYPE_DEFINED_ONLY
     * @param array $map
     * @return $this
     */
    public function setMap($map = []);

    /**
     * Flush data
     * @return $this
     */
    public function flush();

    /**
     * Set scenario to static::SCENARIO_OVERWRITE
     * @return SharedDataInterface
     */
    public function scenarioOverwrite();

    /**
     * Set scenario to static::SCENARIO_WRITE_ONCE
     * @return SharedDataInterface
     */
    public function scenarioWriteOnce();

    /**
     * Set scenario to static::SCENARIO_MERGE
     * @return SharedDataInterface
     */
    public function scenarioMerge();

    /**
     * Set scenario
     * @param string $scenario
     * @return SharedDataInterface
     */
    public function setScenario($scenario);

    /**
     * Set type to static::TYPE_ARBITRARY
     * @return SharedDataInterface
     */
    public function typeArbitrary();

    /**
     * Set type to static::TYPE_DEFINED_ONLY
     * @return SharedDataInterface
     */
    public function typeDefinedOnly();

    /**
     * Set type
     * @param string $type
     * @return SharedDataInterface
     */
    public function setType($type);


    /**
     * Get data from function arguments shared instance
     * @param array $arguments
     * @param string $key
     * @param mixed $defaultValue
     * @return mixed|null
     */
    public static function getSharedFromArgs($arguments = [], $key = null, $defaultValue = null);

    /**
     * Add data to chain instance from function arguments
     * @param array  $arguments
     * @param mixed  $value
     * @param string $key
     * @return SharedDataInterface|null
     */
    public static function addSharedToArgs($arguments = [], $value, $key = null);

    /**
     * Get instance from function arguments
     * @param array $arguments
     * @return SharedDataInterface|null
     */
    public static function instanceFromArguments($arguments = []);
}