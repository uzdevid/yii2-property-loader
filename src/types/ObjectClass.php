<?php

namespace uzdevid\property\loader\types;

class ObjectClass {
    public array|string $name;

    public function __construct(array|string $name) {
        $this->name = $name;
    }
}