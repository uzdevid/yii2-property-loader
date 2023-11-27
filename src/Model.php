<?php

namespace uzdevid\property\loader;

use uzdevid\property\loader\traits\PropertyLoader;
use yii\base\Arrayable;

class Model extends \yii\base\Model {
    use PropertyLoader;

    public function __construct(Arrayable|array $data, string|null $formName = null, array $except = []) {
        $this->except = $except;
        $dataInForm = $this->getDataInForm($data, $formName);
        $this->load($this->loadProperties($dataInForm), '');

        parent::__construct();
    }

    protected function getDataInForm(array $data, string|null $formName = null) {
        $scope = $formName === null ? $this->formName() : $formName;
        
        if ($scope === '' && !empty($data)) {
            return $data;
        }

        return $data[$scope] ?? [];
    }
}