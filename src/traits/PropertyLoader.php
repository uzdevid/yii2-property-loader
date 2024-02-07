<?php

namespace uzdevid\property\loader\traits;

use ReflectionClass;
use ReflectionProperty;
use uzdevid\property\loader\types\Argument;
use yii\base\Arrayable;
use yii\helpers\ArrayHelper;

trait PropertyLoader {
    private array $except = [];

    /**
     * @param Arrayable|array $data
     *
     * @return array
     */
    protected function loadProperties(Arrayable|array $data): array {
        $attributes = $data;

        if ($data instanceof Arrayable) {
            $attributes = $data->toArray();
        }

        $this->loadAttributes(array_diff_key($attributes, $this->properties(), array_flip($this->except)));

        return $this->loadObjects($data);
    }

    /**
     * @return array
     */
    protected function properties(): array {
        return [];
    }

    /**
     * @param array $data
     *
     * @return void
     */
    protected function loadAttributes(array $data): void {
        $reflectionClass = new ReflectionClass($this);
        $propertyNames = array_column($reflectionClass->getProperties(ReflectionProperty::IS_PUBLIC), 'name');
        $filteredConfig = array_intersect_key($data, array_flip($propertyNames));

        parent::__construct($filteredConfig);
    }

    /**
     * @param Arrayable|array $data
     *
     * @return array
     */
    protected function loadObjects(Arrayable|array $data): array {
        $objects = array_diff_key($this->properties(), array_flip($this->except));

        $attributes = [];
        foreach ($objects as $propertyName => $object) {
            $attributes[$propertyName] = $this->getInstance(...$this->configure($object, $data));
        }

        return $attributes;
    }

    /**
     * @param array|string $object
     * @param Arrayable|array $data
     *
     * @return array
     */
    private function configure(array|string $object, Arrayable|array $data): array {
        if (is_string($object)) {
            return [$object, $data];
        }
        $objectClassName = array_shift($object);
        $arguments = is_array($data) ? $this->getArgumentsFromArray($object, $data) : $this->getArgumentsFromObject($object, $data);

        return [$objectClassName, $arguments];
    }

    /**
     * @param $object
     * @param $data
     *
     * @return array
     */
    private function getArgumentsFromArray($object, $data): array {
        $arguments = [];
        foreach ($object as $param) {
            $arguments[] = $param instanceof Argument ? $param->value : $data[$param];
        }
        return $arguments;
    }

    /**
     * @param $object
     * @param $data
     *
     * @return array
     */
    private function getArgumentsFromObject($object, $data): array {
        $arguments = [];

        foreach ($object as $param) {
            $arguments[] = $param instanceof Argument ? $param->value : $data->{$param};
        }

        return $arguments;
    }

    /**
     * @param $className
     * @param $data
     *
     * @return array
     */
    protected function arrayableObject($className, $data): array {
        return array_filter(array_map(function ($datum) use ($className) {
            return $this->processDatum($className, $datum);
        }, $data));
    }

    /**
     * @param $className
     * @param $datum
     *
     * @return array|mixed
     */
    private function processDatum($className, $datum): mixed {
        if (ArrayHelper::isAssociative($datum)) {
            return $this->getInstance($className, $datum);
        }

        return array_map(fn($item) => $this->getInstance($className, [$item]), $datum);
    }

    /**
     * @param array|string|callable $className
     * @param array $arguments
     *
     * @return mixed
     */
    protected function getInstance(array|string|callable $className, array $arguments = []): mixed {
        return match (true) {
            is_array($className) => $this->arrayableObject(array_shift($className), $arguments),
            is_callable($className) => $className(...$arguments),
            method_exists($className, 'build') => call_user_func([$className, 'build'], ...$arguments),
            default => new $className(...$arguments)
        };
    }
}