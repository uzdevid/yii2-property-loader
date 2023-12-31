<?php

namespace uzdevid\property\loader\traits;

use ReflectionClass;
use ReflectionProperty;
use uzdevid\property\loader\types\Argument;
use yii\base\Arrayable;
use yii\base\UnknownPropertyException;
use yii\helpers\ArrayHelper;

trait PropertyLoader {
    private array $except = [];

    protected bool $throwUndefinedPropertyException = true;

    protected function loadProperties(Arrayable|array $data): array {
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

    /**
     * @throws UnknownPropertyException
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
     * @throws UnknownPropertyException
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
     * @throws UnknownPropertyException
     */
    private function getArgumentsFromArray($object, $data): array {
        $arguments = [];
        foreach ($object as $param) {
            if ($param instanceof Argument) {
                $arguments[] = $param->value;
                continue;
            }

            if (array_key_exists($param, $data)) {
                $arguments[] = $data[$param];
                continue;
            }

            if ($this->throwUndefinedPropertyException) {
                throw new UnknownPropertyException("Key {$param} not found");
            }
        }
        return $arguments;
    }

    /**
     * @throws UnknownPropertyException
     */
    private function getArgumentsFromObject($object, $data): array {
        $arguments = [];
        foreach ($object as $param) {
            if ($param instanceof Argument) {
                $arguments[] = $param->value;
                continue;
            }

            if (isset($data->$param)) {
                $arguments[] = $data->$param;
                continue;
            }

            if (str_ends_with($param, '()') && method_exists($data, str_replace('()', '', $param))) {
                $arguments[] = $data->$param();
                continue;
            }

            $getterName = 'get' . ucfirst($param);
            if (method_exists($data, $getterName)) {
                $arguments[] = $data->$getterName();
                continue;
            }

            if ($this->throwUndefinedPropertyException) {
                throw new UnknownPropertyException("Property/Method with name '{$param}' not found");
            }
        }

        return $arguments;
    }

    protected function findAssoc(array $arrayArgument) {
        if (ArrayHelper::isAssociative($arrayArgument)) {
            return $arrayArgument;
        }

        $current = current($arrayArgument);
        if (!is_null($current)) {
            return $this->findAssoc($current);
        }

        return null;
    }

    protected function arrayableObject($className, $data): array {
        $objects = [];
        foreach ($data as $datum) {
            if (empty($datum)) continue;
            if (ArrayHelper::isAssociative($datum)) {
                $objects[] = $this->getInstance($className, $datum);
            } else {
                $objects = array_merge($objects, array_map(function ($item) use ($className) {
                    return $this->getInstance($className, [$item]);
                }, $datum));
            }
        }
        return $objects;
    }

    protected function getInstance(array|string|callable $className, array $arguments = []): mixed {
        return match (true) {
            is_array($className) => $this->arrayableObject(array_shift($className), $arguments),
            is_callable($className) => $className(...$arguments),
            method_exists($className, 'build') => call_user_func([$className, 'build'], ...$arguments),
            default => new $className(...$arguments)
        };
    }
}