<?php

namespace App\Helpers;

use Flight;
use Rakit\Validation\Validator;

class Utility
{

    static public function renderErrors(array $errors): void
    {
        foreach ($errors as $inputName => $error) {
            if (is_array($error)) {
                $flattenArray = self::flatten($error);
                $error = $flattenArray[0];
            }

            Flight::render('component/input-error.latte', [
                'block' => false,
                'oob' => true,
                'name' => $inputName,
                'error' => $error
            ]);
        }

    }

    static public function prepareAllFields(array $rules): array
    {
        $finalRules = [];

        foreach ($rules as $field => $fieldRules) {
            if (is_string($fieldRules)) {
                if (str_contains($fieldRules, 'array')) continue;
                $finalRules[$field] = 'prepare|' . $fieldRules;
            } else {
                $finalRules[$field] = array_merge(['prepare'], $fieldRules);
            }
        }

        return $finalRules;
    }

    static public function validateData(array $data, array $rules, array $aliases = [], array $messages = []): \Rakit\Validation\Validation
    {
        $genericAliases = [
            'crm' => 'CRM',
            'note' => 'additional comments',
            'other' => 'name of the other CRM',
            'email' => 'email',
            'emails' => 'email',
            'password' => 'password',
            'firstName' => 'first name',
            'lastName' => 'last name',
            'themeMode' => 'theme mode',
            'locations' => 'selected locations',
            'type' => 'location type',
            'selectedLeads' => 'selected leads',
            'unselectedLeads' => 'unselected leads',
            'isAllRowsSelected' => 'selection status for all rows',
            'group' => 'group identifier',
            'search' => 'search term',
            'lastLocationType' => 'last location type',
            'lastLocationId' => 'last location ID',
            'addedLocations' => 'previously added locations',
            'searchTerm' => 'search keyword',
            'bbox' => 'bounding box coordinates',
            'userAmemberId' => 'AMember user ID',
            'location' => 'location details',
            'page' => 'page number',
            'sortColumn' => 'column to sort by',
            'sortDirection' => 'sorting direction',
            'price' => 'property price',
            'sqft' => 'SQFT',
            'zipCode' => 'ZIP code',
            'city' => 'city name',
            'county' => 'county name',
            'perPage' => 'items per page',
            'minSqft' => 'minimum SQFT',
            'maxSqft' => 'maximum SQFT',
            'minPrice' => 'minimum price',
            'maxPrice' => 'maximum price',
            'listingStatus' => 'property listing status',
            'redirect' => 'redirect URL',
            'houseType' => 'house type',
            'name' => 'campaign name',
            'sendPdf' => 'file type',
            'sendCsv' => 'file type',
            'column' => 'file'
        ];

        $aliases = array_merge($genericAliases, $aliases);

        $validator = new Validator();
        $validator->addValidator('prepare', new PrepareRequestRule());
        $validator->addValidator('string', new StringSanitizeRule());

        $preparedRules = self::prepareAllFields($rules);
        $validation = $validator->make($data, $preparedRules, $messages);

        $validation->setAliases($aliases);
        $validation->validate();

        return $validation;
    }

    static public function validateRequest(array $data, array $rules, array $aliases = [], array $messages = []): bool|array
    {
        $validation = self::validateData($data, $rules, $aliases, $messages);

        if ($validation->fails()) {
            $errorsObject = $validation->errors();
            $errors = $errorsObject->firstOfAll();

            Utility::renderErrors($errors);
            return false;
        }

        return $validation->getValidData();

    }

    static public function parseIfIsNumber($value, $preserveLeadingZeros = true)
    {
        $value = trim($value);

        $hasLeadingZeros = $preserveLeadingZeros && preg_match('/^0+\d/', $value);

        if (str_starts_with($value, '$')) {
            $value = filter_var($value, FILTER_SANITIZE_NUMBER_INT);
            return $hasLeadingZeros ? $value : (int)$value;
        }

        if (preg_match('/^\d+$/', $value)) {
            if ($hasLeadingZeros) {
                return $value;
            }
            return (int)$value;
        }

        if (preg_match('/^[+-]?\d+,\d{1,2}$/', $value)) {
            return (float)str_replace(',', '.', $value);
        }

        if (preg_match('/^[+-]?\d{1,3}(,\d{3})*(\.\d+)?$/', $value)) {
            return (float)str_replace(',', '', $value);
        }

        if (is_numeric(str_replace(',', '.', $value))) {
            return (float)str_replace(',', '.', $value);
        }

        return $value;
    }

    static public function flatten($data, array $result = []): array
    {
        foreach ($data as $flat) {
            if (is_array($flat)) {
                $result = self::flatten($flat, $result);
            } else {
                $result[] = $flat;
            }
        }

        return $result;
    }

    static public function userCan($userId, $table, $action)
    {
        if (!$userId) return false;

        $enforcer = Flight::casbin();

        if ($enforcer->enforce("user:$userId", $table, $action)) {
            return true;
        }

        $roles = $enforcer->getRolesForUser("user:$userId");
        foreach ($roles as $role) {
            if ($enforcer->enforce($role, $table, $action)) {
                return true;
            }
        }

        return false;
    }
}
