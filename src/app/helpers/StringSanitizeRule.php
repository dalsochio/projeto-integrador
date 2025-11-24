<?php

namespace App\Helpers;

use Rakit\Validation\Rule;
use Rakit\Validation\Rules\Interfaces\ModifyValue;

class StringSanitizeRule extends Rule implements ModifyValue
{
    protected $message = ':attribute sanitized successfully';

    public function check($value): bool
    {
        return true;
    }

    public function modifyValue($value)
    {
        if (is_null($value)) {
            return $value;
        }

        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
