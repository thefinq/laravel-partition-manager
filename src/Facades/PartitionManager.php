<?php

namespace Finq\LaravelPartitionManager\Facades;

use Illuminate\Support\Facades\Facade;

class PartitionManager extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'partition-manager';
    }
}