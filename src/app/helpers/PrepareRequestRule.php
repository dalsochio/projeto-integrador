<?php

namespace App\Helpers;

use Rakit\Validation\Rule;
use Rakit\Validation\Rules\Interfaces\BeforeValidate;

class PrepareRequestRule extends Rule implements BeforeValidate
{
    protected $message = ':attribute prepared successfully';

    public function check($value): bool
    {
        return true;
    }

    public function beforeValidate()
    {
        $attribute = $this->getAttribute();

        $key = $attribute->getKey();

        if (str_contains($key, '*')) {
            return;
        }

        if (!$this->validation->hasValue($key)) {
            return;
        }

        $value = $this->validation->getValue($key);

        $newValue = $this->prepareValue($value);

        if ($newValue !== $value) {
            $this->validation->setValue($key, $newValue);
        }
    }

    protected function prepareValue($value): array|bool|string|null
    {
        if (is_array($value)) {
            $result = [];
            foreach ($value as $key => $arrayValue) {
                $result[$key] = $this->prepareValue($arrayValue);
            }
            return $result;
        }

        if (is_string($value)) {
            switch ($value) {
                case 'on':
                    return 1;
                case 'off':
                    return 0;
                case '':
                case 'null':
                    return null;
                default:
                    return Utility::parseIfIsNumber($value);
            }
        }

        return $value;
    }
}
