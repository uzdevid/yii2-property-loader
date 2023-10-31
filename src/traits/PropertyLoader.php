<?php

namespace uzdevid\property\loader\traits;

use ReflectionClass;
use ReflectionProperty;
use uzdevid\property\loader\types\Argument;
use yii\base\Arrayable;

trait PropertyLoader {
    private array $except = [];

    protected function load(Arrayable|array $data): array {
        $attributes = $data;

        if ($data instanceof Arrayable) {
            $attributes = $data->toArray();
        }

        $this->loadAttributes(array_diff_key($attributes, $this->properties(), array_flip($this->except)));

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
            return [$object, $data];
        }

        $objectClassName = array_shift($object);
        $arguments = is_array($data) ? $this->getArgumentsFromArray($object, $data) : $this->getArgumentsFromObject($object, $data);

        return [$objectClassName, $arguments];
    }

    private function getArgumentsFromArray($object, $data): array {
        $arguments = [];
        foreach ($object as $param) {
            $arguments[] = $param instanceof Argument ? $param->value : $data[$param];
        }
        return $arguments;
    }

    private function getArgumentsFromObject($object, $data): array {
        $arguments = [];
        foreach ($object as $param) {
            $arguments[] = $param instanceof Argument ? $param->value : $data->{$param};
        }
        return $arguments;
    }

    protected function arrayableObject($className, $data): array {
        $objects = [];
        foreach ($data as $datum) {
            if (empty($datum)) continue;
            $objects[] = $this->getInstance($className, $datum);
        }
        return $objects;
    }

    protected function getInstance(array|string|callable $className, array $arguments = []): mixed {
        return match (true) {
            is_array($className) => $this->arrayableObject(array_shift($className), $arguments),
            is_callable($className) => call_user_func($className, ...$arguments),
            method_exists($className, 'build') => call_user_func([$className, 'build'], ...$arguments),
            default => new $className(...$arguments)
        };
    }
}