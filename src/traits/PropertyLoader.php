<?php

namespace uzdevid\property\loader\traits;

use ReflectionClass;
use ReflectionProperty;
use uzdevid\property\loader\types\Argument;
use uzdevid\property\loader\types\Key;
use uzdevid\property\loader\types\ObjectClass;
use uzdevid\property\loader\types\Property;
use yii\base\Arrayable;
use yii\base\InvalidArgumentException;

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
        $objects = array_diff_key($this->properties(), array_flip($this->except));

        $attributes = [];
        foreach ($objects as $propertyName => $object) {
            $attributes[$propertyName] = $this->getInstance(...$this->configure($object, $data));
        }

        return $attributes;
    }

    private function configure(array|string $object, Arrayable|array $data): array {
        if (is_string($object)) {
            return [$object, $data, []];
        }

        $objectClassName = null;
        $params = [];
        $arguments = [];

        foreach ($object as $param) {
            match (true) {
                is_null($objectClassName) && $param instanceof ObjectClass => $objectClassName = $param->name,
                is_null($objectClassName) && is_callable($param) => $objectClassName = $param,
                $param instanceof Property => $params[$param->name] = $data->{$param->name},
                $param instanceof Key => $params[$param->name] = $data[$param->name],
                $param instanceof Argument => $arguments[] = $param->value
            };
        }

        if (is_null($objectClassName)) {
            throw new InvalidArgumentException('ObjectClass name is not setted');
        }

        return [$objectClassName, $params, $arguments];
    }

    protected function arrayableObject($className, $data): array {
        $objects = [];
        foreach ($data as $datum) {
            $objects[] = $this->getInstance($className, $datum);
        }
        return $objects;
    }

    protected function getInstance(array|string|callable $className, array $params = [], array $arguments = []): mixed {
        $data = [];

        if (!empty($params)) {
            $data[] = $params;
        }

        if (!empty($arguments)) {
            $data = array_merge($data, $arguments);
        }

        return match (true) {
            is_array($className) => $this->arrayableObject(array_shift($className), ...$data),
            is_callable($className) => call_user_func($className, ...$data),
            method_exists($className, 'build') => call_user_func([$className, 'build'], ...$data),
            default => new $className(...$data)
        };
    }
}