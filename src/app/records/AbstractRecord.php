<?php

namespace App\Records;

use App\Helpers\Utility;
use Flight;
use Tracy\Debugger;

abstract class AbstractRecord extends \flight\ActiveRecord
{
    protected string $database;

    protected array $relations = [];


    public function __construct($databaseConnection = null)
    {
        $this->database = $_ENV['DB_DATABASE'];
        $connection = Flight::db();
        parent::__construct($connection, $this->table);
    }


    public function search(array $columns, string $search, bool $order = false): static
    {
        foreach ($columns as $index => $column) {
            if ($index === 0) {
                $this->startWrap();
            }

            $this->or()->like($column, '%' . $search . '%');

            if ($index === count($columns) - 1) {
                $this->endWrap('OR');
            }
        }

        if ($order) {
            $orderBy = [];

            foreach ($columns as $column) {
                $orderBy[] = <<<SQL
                  CASE
                    WHEN `$column` LIKE '$search%' THEN 1
                    WHEN `$column` LIKE '%$search' THEN 3
                    ELSE 2
                  END
                SQL;
            }

            $this->orderBy(...$orderBy);
        }

        return $this;
    }


    public function findAllToArray($flatten = false): array
    {
        $rows = $this->findAll();
        $rowsAsArrays = [];

        foreach ($rows as $row) {
            $rowsAsArrays[] = $row->toArray();
        }

        if ($flatten) {
            $rowsAsArrays = Utility::flatten($rowsAsArrays);
        }

        return $rowsAsArrays;
    }

    public function softDelete()
    {
        $this->deletedAt = date('Y-m-d H:i:s');
        $this->save();
    }


    public function paginate(int $page = 1, int $perPage = 15, $totalColumn = null): array
    {
        $baseUrl = Flight::request()->getBaseUrl();
        $fullUrl = Flight::request()->getFullUrl();

        $pathUrl = str_replace($baseUrl, '', $fullUrl);

        $page = max(1, $page);
        $perPage = max(1, $perPage);

        $offset = ($page - 1) * $perPage;

        $countQuery = clone $this;
        if (!is_null($totalColumn)) {
            $escapeColumn = Flight::db()->quote($totalColumn);
            $total = $countQuery->select('COUNT(DISTINCT ' . $escapeColumn . ') as total')->find()->total;
        } else {
            $total = $countQuery->select('COUNT(DISTINCT ' . $this->table . '.' . $this->primaryKey . ') as total')->find()->total;
        }
        $items = $this
            ->limit($perPage)
            ->offset($offset)
            ->findAll();

        $lastPage = (int)ceil($total / $perPage);
        $from = $total > 0 ? $offset + 1 : 0;
        $to = min($offset + $perPage, $total);

        return [
            'data' => $items,
            'currentPage' => $page,
            'from' => $from,
            'to' => $to,
            'lastPage' => $lastPage,
            'perPage' => $perPage,
            'total' => $total,
            'hasMorePages' => $page < $lastPage,
            'url' => $pathUrl
        ];
    }


    public function paginateToArray(int $page = 1, int $perPage = 15, $totalColumn = null): array
    {
        $result = $this->paginate($page, $perPage, $totalColumn);

        $dataAsArray = [];
        foreach ($result['data'] as $item) {
            $dataAsArray[] = $item->toArray();
        }
        $result['data'] = $dataAsArray;

        return $result;
    }


    public function paginateFromRequest(int $defaultPerPage = 15, bool $toArray = false, $totalColumn = null): array
    {
        $request = Flight::request();
        $page = (int)($request->data->page ?? 1);
        $perPage = (int)($request->data->perPage ?? $defaultPerPage);

        $perPage = min($perPage, 100);

        if ($toArray) {
            return $this->paginateToArray($page, $perPage, $totalColumn);
        }

        return $this->paginate($page, $perPage, $totalColumn);
    }


    public function sortFromRequest(): static
    {
        $request = Flight::request();
        $sortColumn = $request->data->sortColumn;
        $sortDirection = $request->data->sortDirection;

        if (empty($sortColumn) || empty($sortDirection)) {
            return $this;
        }

        $this->orderBy($sortColumn . ' ' . strtoupper($sortDirection));

        return $this;
    }

    public function bindColumnValueArray(array $columnValueArray)
    {

        foreach ($columnValueArray as $column => $value) {
            $this->$column = $value;
        }

        return $this;

    }

    public function getPrimaryKey()
    {
        return $this->primaryKey;
    }

    private function generateKeyString(string|array $data, array $uniqueColumns): string
    {
        if (!is_array($data)) {
            return $data;
        }

        if (count($uniqueColumns) === 1) {
            return $data[$uniqueColumns[0]];
        }

        $keyString = '';
        foreach ($uniqueColumns as $index => $column) {
            if ($index > 0) {
                $keyString .= '|';
            }
            $keyString .= $data[$column];
        }

        return $keyString;
    }


    public function syncData(array $newData, array $fixedValues, array $uniqueColumns = [], int $batchSize = 500): void
    {
        if (empty($uniqueColumns)) {
            $uniqueColumns = [$this->primaryKey];
        }

        $existingData = $this->select($this->primaryKey, ...$uniqueColumns)->findAllToArray();

        $formattedExistingData = [];
        foreach ($existingData as $row) {
            $keyString = $this->generateKeyString($row, $uniqueColumns);
            $formattedExistingData[$keyString] = $row[$this->primaryKey];
        }

        $insertArray = [];
        $formattedNewData = [];
        foreach ($newData as $value) {
            $keyString = $this->generateKeyString($value, $uniqueColumns);
            $formattedNewData[$keyString] = true;

            if (!isset($formattedExistingData[$keyString])) {
                $insertArray[] = $value;
            }
        }

        $deleteArray = [];
        foreach ($formattedExistingData as $keyString => $id) {
            if (!isset($formattedNewData[$keyString])) {
                $deleteArray[] = $id;
            }
        }

        if (!empty($deleteArray)) {
            foreach ($deleteArray as $id) {
                $record = new static();
                $record->equal($this->primaryKey, $id)->find();
                if ($record->isHydrated()) {
                    $record->delete();
                }
            }
        }

        if (!empty($insertArray)) {
            $batches = array_chunk($insertArray, $batchSize);

            foreach ($batches as $batch) {
                foreach ($batch as $recordData) {
                    $record = new static();

                    foreach ($fixedValues as $column => $value) {
                        $record->$column = $value;
                    }

                    if (is_array($recordData)) {
                        foreach ($recordData as $column => $value) {
                            $record->$column = $value;
                        }
                    } else {
                        $record->{$uniqueColumns[0]} = $recordData;
                    }

                    try {
                        $record->insert();
                    } catch (\Exception $e) {
                        Debugger::log("Failed to insert record in table {$this->table}: " . $e->getMessage(), Debugger::CRITICAL);
                    }
                }
            }
        }
    }
}
