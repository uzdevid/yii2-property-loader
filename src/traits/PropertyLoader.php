<?php

namespace uzdevid\property\loader\traits;

use ReflectionClass;
use ReflectionProperty;
use uzdevid\property\loader\types\Argument;
use uzdevid\property\loader\types\Key;
use uzdevid\property\loader\types\ObjectClass;
use uzdevid\property\loader\types\Property;
use yii\base\Arrayable;

trait PropertyLoader {
    private array $except = [];

    protected function load(Arrayable|array $data): array {
        $attributes = $data;

        if ($data instanceof Arrayable) {
            $attributes = $data->toArray();
        }

        $this->loadAttributes(array_diff_key($attributes, $this->properties(), $this->except));

        return $this->loadObjects($data);
    }

    protected function properties(): array {
        return [];
    }

    protected function loadAttributes(array $data): void {
        $reflectionClass = new ReflectionClass($this);
        $propertyNames = array_column($reflectionClass->getProperties(ReflectionProperty::IS_PUBLIC), 'name');
        $filteredConfig = array_intersect_key($data, array_flip($propertyNames));

        parent::__construct($filteredConfig);
    }

    protected function loadObjects(Arrayable|array $data): array {
        $objects = array_diff_key($this->objects(), array_flip($this->except));

        $attributes = [];
        foreach ($objects as $propertyName => $object) {
            if (is_array($object)) {
                list($className, $arguments) = $this->configure($object, $data);
            } else {
                $className = $object;
                $arguments = [$data];
            }

            $attributes[$propertyName] = $this->build($className, $arguments);
        }

        return $attributes;
    }

    private function configure(array $object, Arrayable|array $data): array {
        $objectClassName = null;
        $arguments = [];

        foreach ($object as $param) {
            if (is_null($objectClassName) && $param instanceof ObjectClass) $objectClassName = $param;
            if (is_null($objectClassName) && is_callable($param)) $objectClassName = $param;
            if ($param instanceof Property) $arguments[] = $this->getProperty($data, $param->name);
            if ($param instanceof Key) $arguments[] = $this->getKey($data, $param->name);
            if ($param instanceof Argument) $arguments[] = $param->value;
        }

        return [$objectClassName, $arguments];
    }

    private function getKey(array $data, string $keyName) {
        return array_key_exists($keyName, $data) ? $data[$keyName] : null;
    }

    private function getProperty(object $data, string $propertyName) {
        return property_exists($data, $propertyName) ? $data->$propertyName : null;
    }

    protected function build(array|string|callable $className, $data): mixed {
        if (is_array($className)) {
            return $this->arrayableObject(array_shift($className), $data);
        } elseif (is_string($className)) {
            return new $className($data);
        } else {
            return $this->getInstance($className, $data);
        }
    }

    protected function arrayableObject($className, $data): array {
        $objects = [];
        foreach ($data as $datum) {
            $objects[] = $this->build($className, $datum);
        }
        return $objects;
    }

    protected function getInstance(string $className, $data): mixed {
        return match (true) {
            is_callable($className) => call_user_func($className, $data),
            method_exists($className, 'build') => call_user_func([$className, 'build'], $data),
            default => new $className(...$data)
        };
    }
}