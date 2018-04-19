<?php

namespace Reaction\Promise;

/**
 * Class ChainDependency. Fully sync package.
 * @package Reaction\Promise
 */
class ChainDependency implements ChainDependencyInterface, \IteratorAggregate, \Countable
{
    public $scenario            = self::SCENARIO_OVERWRITE;
    public $type                = self::TYPE_ARBITRARY;

    protected $dependencies = [];

    /**
     * Add dependency
     * @param mixed  $value
     * @param string $key
     * @return ChainDependency
     */
    public function addDependency($value, $key = null) {
        if(!isset($key) && is_object($value)) {
            $key = get_class($value);
        }
        return $this->addDependencyInternal($value, $key);
    }

    /**
     * Get dependency by key
     * @param string $key
     * @param mixed  $defaultValue
     * @return mixed|null
     */
    public function getDependency($key, $defaultValue = null) {
        $key = $this->processKey($key);
        if(!$this->hasDependency($key)) return $defaultValue;
        if(is_array($this->dependencies[$key]) && isset($this->dependencies[$key]['#value'])) {
            return $this->dependencies[$key]['#value'];
        }
        return $this->dependencies[$key];
    }

    /**
     * Check that dependency is set
     * @param string $key
     * @return bool
     */
    public function hasDependency($key) {
        $key = $this->processKey($key);
        return isset($this->dependencies[$key]);
    }

    /**
     * Set dependencies map.
     * That creates mapped dependency with type static::TYPE_DEFINED_ONLY
     * @param array $map
     * @return ChainDependency
     */
    public function setMap($map = []) {
        $this->flush();
        $this->dependencies = array_fill_keys($map, null);
        $this->type = static::TYPE_DEFINED_ONLY;
        return $this;
    }

    /**
     * Flush dependencies
     * @return ChainDependency
     */
    public function flush() {
        $this->dependencies = [];
        return $this;
    }

    /**
     * Set scenario to static::SCENARIO_OVERWRITE
     * @return ChainDependency
     */
    public function scenarioOverwrite() {
        return $this->setScenario(static::SCENARIO_OVERWRITE);
    }

    /**
     * Set scenario to static::SCENARIO_WRITE_ONCE
     * @return ChainDependency
     */
    public function scenarioWriteOnce() {
        return $this->setScenario(static::SCENARIO_WRITE_ONCE);
    }

    /**
     * Set scenario to static::SCENARIO_MERGE
     * @return ChainDependency
     */
    public function scenarioMerge() {
        return $this->setScenario(static::SCENARIO_MERGE);
    }

    /**
     * Set scenario
     * @param string $scenario
     * @return ChainDependency
     */
    public function setScenario($scenario) {
        $scenarios = [static::SCENARIO_OVERWRITE, static::SCENARIO_WRITE_ONCE, static::SCENARIO_MERGE];
        if(in_array($scenario, $scenarios)) $this->scenario = $scenario;
        return $this;
    }

    /**
     * Set type to static::TYPE_ARBITRARY
     * @return ChainDependency
     */
    public function typeArbitrary() {
        return $this->setType(static::TYPE_ARBITRARY);
    }

    /**
     * Set type to static::TYPE_DEFINED_ONLY
     * @return ChainDependency
     */
    public function typeDefinedOnly() {
        return $this->setType(static::TYPE_DEFINED_ONLY);
    }

    /**
     * Set type
     * @param string $type
     * @return ChainDependency
     */
    public function setType($type) {
        $types = [static::TYPE_DEFINED_ONLY, static::TYPE_ARBITRARY];
        if(in_array($type, $types)) $this->type = $type;
        return $this;
    }

    /**
     * Stringify dependency key
     * @param mixed $key
     * @return mixed|string
     */
    protected function processKey($key) {
        if(is_string($key)) return $key;
        return md5(json_encode($key));
    }

    /**
     * Add dependency
     * @param mixed  $value
     * @param string $key
     * @return ChainDependency
     * @internal
     */
    protected function addDependencyInternal($value, $key) {
        if(null === $value || null === $key) return $this;
        $key = $this->processKey($key);
        if($this->type === static::TYPE_DEFINED_ONLY && !array_key_exists($key, $this->dependencies)) return $this;
        switch ($this->scenario) {
            case static::SCENARIO_OVERWRITE:
                $this->dependencies[$key] = $value;
                break;
            case static::SCENARIO_WRITE_ONCE:
                if(!isset($this->dependencies[$key])) $this->dependencies[$key] = $value;
                break;
            case static::SCENARIO_MERGE:
                if(!isset($this->dependencies[$key])) {
                    $this->dependencies[$key] = $value;
                } elseif(isset($this->dependencies[$key]['#merged'])) {
                    $this->dependencies[$key]['#value'][] = $value;
                } else {
                    $oldValue = $this->dependencies[$key];
                    $this->dependencies[$key] = [
                        '#merged' => true,
                        '#value' => [$oldValue, $value]
                    ];
                }
                break;
        }
        return $this;
    }

    /**
     * Get dependency from function arguments chain instance
     * @param array $arguments
     * @param string $key
     * @param mixed $defaultValue
     * @return mixed|null
     */
    public static function getDependencyFromArgs($arguments = [], $key = null, $defaultValue = null) {
        if($key === null) return null;
        /** @var ChainDependencyInterface $instance */
        if(($instance = static::instanceFromArguments($arguments)) === null) return null;
        return $instance->getDependency($key, $defaultValue);
    }

    /**
     * Add dependency to chain instance from function arguments
     * @param array  $arguments
     * @param mixed  $value
     * @param string $key
     * @return ChainDependencyInterface|null
     */
    public static function addDependencyToArgs($arguments = [], $value, $key = null) {
        /** @var ChainDependencyInterface $instance */
        if(($instance = static::instanceFromArguments($arguments)) === null) return null;
        return $instance->addDependency($value, $key);
    }

    /**
     * Get instance from function arguments
     * @param array $arguments
     * @return ChainDependencyInterface|null
     */
    public static function instanceFromArguments($arguments = []) {
        $arguments = array_values($arguments);
        $argumentsCount = count($arguments);
        for ($i = 0; $i < $argumentsCount; $i++) {
            if(is_object($arguments[$i]) && $arguments[$i] instanceof ChainDependencyInterface) return $arguments[$i];
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
        $iterator = new \ArrayIterator($this->dependencies);
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
        return count($this->dependencies);
    }
}