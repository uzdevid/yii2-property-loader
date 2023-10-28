<?php
namespace uzdevid\property\loader\types;

class Key {
    public string $name;

    public function __construct(string $name) {
        $this->name = $name;
    }
}