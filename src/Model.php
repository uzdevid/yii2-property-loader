<?php

namespace uzdevid\property\loader;

use uzdevid\property\loader\traits\PropertyLoader;

class Model extends \yii\base\Model {
    use PropertyLoader;

    public function __construct(Arrayable|array $data, string|null $formName = '', array $except = []) {
        $this->except = $except;
        $this->load($this->loadProperties($data), $formName);
    }
}