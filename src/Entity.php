<?php

namespace uzdevid\property\loader;

use uzdevid\property\loader\traits\PropertyLoader;
use yii\base\Arrayable;
use yii\base\ArrayableTrait;
use yii\base\BaseObject;

abstract class Entity extends BaseObject implements Arrayable {
    use ArrayableTrait;
    use PropertyLoader;

    public function __construct(Arrayable|array|null $data = null, array $except = []) {
        $this->except = $except;

        if (is_null($data)) {
            return;
        }

        parent::__construct($this->load($data));
    }

    abstract public static function name(): string;
}