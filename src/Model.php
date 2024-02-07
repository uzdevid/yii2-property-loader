<?php

namespace uzdevid\property\loader;

use uzdevid\property\loader\traits\PropertyLoader;
use yii\base\Arrayable;
use yii\base\InvalidConfigException;

class Model extends \yii\base\Model {
    use PropertyLoader;

    /**
     * @param Arrayable|array $data
     * @param string|null $formName
     * @param array $except
     *
     * @throws InvalidConfigException
     */
    public function __construct(Arrayable|array $data, string|null $formName = null, array $except = []) {
        $this->except = $except;
        $dataInForm = $this->getDataInForm($data, $formName);
        $this->load($this->loadProperties($dataInForm), '');

        parent::__construct();
    }

    /**
     * @param array $data
     * @param string|null $formName
     *
     * @return array|mixed
     * @throws InvalidConfigException
     */
    protected function getDataInForm(array $data, string|null $formName = null): mixed {
        $scope = $formName ?? $this->formName();

        if ($scope === '' && !empty($data)) {
            return $data;
        }

        return $data[$scope] ?? [];
    }
}