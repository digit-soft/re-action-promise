<?php

namespace Reaction\Promise;

/**
 * Class ChainDependency. Fully sync package.
 * @package Reaction\Promise
 */
class SharedData implements SharedDataInterface, \IteratorAggregate, \Countable
{
    public $scenario            = self::SCENARIO_OVERWRITE;
    public $type                = self::TYPE_ARBITRARY;

    /** @var array Shared data storage */
    protected $shared = [];

    /**
     * Add data
     * @param mixed  $value
     * @param string $key
     * @return SharedDataInterface
     */
    public function addData($value, $key = null) {
        if (!isset($key) && is_object($value)) {
            $key = get_class($value);
        }
        return $this->addDataInternal($value, $key);
    }

    /**
     * Get data by key
     * @param string $key
     * @param mixed  $defaultValue
     * @return mixed|null
     */
    public function getData($key, $defaultValue = null) {
        $key = $this->processKey($key);
        if (!$this->hasData($key)) {
            return $defaultValue;
        }
        if (is_array($this->shared[$key]) && isset($this->shared[$key]['#value'])) {
            return $this->shared[$key]['#value'];
        }
        return $this->shared[$key];
    }

    /**
     * Check that data is set
     * @param string $key
     * @return bool
     */
    public function hasData($key) {
        $key = $this->processKey($key);
        return isset($this->shared[$key]);
    }

    /**
     * Set data map.
     * That creates mapped data storage with type static::TYPE_DEFINED_ONLY
     * @param array $map
     * @return SharedDataInterface
     */
    public function setMap($map = []) {
        $this->flush();
        $this->shared = array_fill_keys($map, null);
        $this->type = static::TYPE_DEFINED_ONLY;
        return $this;
    }

    /**
     * Flush data
     * @return SharedDataInterface
     */
    public function flush() {
        $this->shared = [];
        return $this;
    }

    /**
     * Set scenario to static::SCENARIO_OVERWRITE
     * @return SharedDataInterface
     */
    public function scenarioOverwrite() {
        return $this->setScenario(static::SCENARIO_OVERWRITE);
    }

    /**
     * Set scenario to static::SCENARIO_WRITE_ONCE
     * @return SharedDataInterface
     */
    public function scenarioWriteOnce() {
        return $this->setScenario(static::SCENARIO_WRITE_ONCE);
    }

    /**
     * Set scenario to static::SCENARIO_MERGE
     * @return SharedDataInterface
     */
    public function scenarioMerge() {
        return $this->setScenario(static::SCENARIO_MERGE);
    }

    /**
     * Set scenario
     * @param string $scenario
     * @return SharedDataInterface
     */
    public function setScenario($scenario) {
        $scenarios = [static::SCENARIO_OVERWRITE, static::SCENARIO_WRITE_ONCE, static::SCENARIO_MERGE];
        if (in_array($scenario, $scenarios)) {
            $this->scenario = $scenario;
        }
        return $this;
    }

    /**
     * Set type to static::TYPE_ARBITRARY
     * @return SharedDataInterface
     */
    public function typeArbitrary() {
        return $this->setType(static::TYPE_ARBITRARY);
    }

    /**
     * Set type to static::TYPE_DEFINED_ONLY
     * @return SharedDataInterface
     */
    public function typeDefinedOnly() {
        return $this->setType(static::TYPE_DEFINED_ONLY);
    }

    /**
     * Set type
     * @param string $type
     * @return SharedDataInterface
     */
    public function setType($type) {
        $types = [static::TYPE_DEFINED_ONLY, static::TYPE_ARBITRARY];
        if (in_array($type, $types)) {
            $this->type = $type;
        }
        return $this;
    }

    /**
     * Stringify data key
     * @param mixed $key
     * @return mixed|string
     */
    protected function processKey($key) {
        if (is_string($key)) {
            return $key;
        }
        return md5(json_encode($key));
    }

    /**
     * Add data (internal function)
     * @param mixed  $value
     * @param string $key
     * @return SharedDataInterface
     * @internal
     */
    protected function addDataInternal($value, $key) {
        if (null === $value || null === $key) {
            return $this;
        }
        $key = $this->processKey($key);
        if ($this->type === static::TYPE_DEFINED_ONLY && !array_key_exists($key, $this->shared)) {
            return $this;
        }
        switch ($this->scenario) {
            case static::SCENARIO_OVERWRITE:
                $this->shared[$key] = $value;
                break;
            case static::SCENARIO_WRITE_ONCE:
                if (!isset($this->shared[$key])) {
                    $this->shared[$key] = $value;
                }
                break;
            case static::SCENARIO_MERGE:
                if (!isset($this->shared[$key])) {
                    $this->shared[$key] = $value;
                } elseif (isset($this->shared[$key]['#merged'])) {
                    $this->shared[$key]['#value'][] = $value;
                } else {
                    $oldValue = $this->shared[$key];
                    $this->shared[$key] = [
                        '#merged' => true,
                        '#value' => [$oldValue, $value]
                    ];
                }
                break;
        }
        return $this;
    }

    /**
     * Get data from function arguments shared instance
     * @param array  $arguments
     * @param string $key
     * @param mixed  $defaultValue
     * @return mixed|null
     */
    public static function getSharedFromArgs($arguments = [], $key = null, $defaultValue = null) {
        if ($key === null) {
            return null;
        }
        /** @var SharedDataInterface $instance */
        if (($instance = static::instanceFromArguments($arguments)) === null) {
            return null;
        }
        return $instance->getData($key, $defaultValue);
    }

    /**
     * Add data to chain instance from function arguments
     * @param array  $arguments
     * @param mixed  $value
     * @param string $key
     * @return SharedDataInterface|null
     */
    public static function addSharedToArgs($arguments = [], $value, $key = null) {
        /** @var SharedDataInterface $instance */
        if (($instance = static::instanceFromArguments($arguments)) === null) {
            return null;
        }
        return $instance->addData($value, $key);
    }

    /**
     * Get instance from function arguments
     * @param array $arguments
     * @return SharedDataInterface|null
     */
    public static function instanceFromArguments($arguments = []) {
        $arguments = array_values($arguments);
        $argumentsCount = count($arguments);
        for ($i = 0; $i < $argumentsCount; $i++) {
            if (is_object($arguments[$i]) && $arguments[$i] instanceof SharedDataInterface) {
                return $arguments[$i];
            }
        }
        return null;
    }

    /**
     * Retrieve an external iterator
     * @link http://php.net/manual/en/iteratoraggregate.getiterator.php
     * @return \Traversable An instance of an object implementing Iterator or Traversable
     * @since 5.0.0
     */
    public function getIterator()
    {
        $iterator = new \ArrayIterator($this->shared);
        return $iterator;
    }

    /**
     * Count elements of an object
     * @link http://php.net/manual/en/countable.count.php
     * @return int The custom count as an integer.
     * The return value is cast to an integer.
     * @since 5.1.0
     */
    public function count()
    {
        return count($this->shared);
    }
}