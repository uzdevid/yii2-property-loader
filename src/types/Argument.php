<?php

namespace uzdevid\property\loader\types;

class Argument {
    public mixed $value;

    /**
     * @param mixed $value
     */
    public function __construct(mixed $value) {
        $this->value = $value;
    }
}