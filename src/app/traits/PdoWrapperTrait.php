<?php

namespace App\Traits;

trait PdoWrapperTrait
{
    public function findAllToArray(): array
    {
        $regularFindAll = $this->findAll();
        $array = [];

        foreach ($regularFindAll as $item) {
            $array[] = $item->toArray();
        }

        return $array;
    }
}
