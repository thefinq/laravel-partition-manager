<?php

namespace Finq\LaravelPartitionManager\ValueObjects;

class ListSubPartition extends SubPartition
{
    protected array $values = [];
    
    public static function create(string $name): self
    {
        return new self($name);
    }
    
    public function withValues(array $values): self
    {
        $this->values = $values;
        return $this;
    }
    
    public function withValue($value): self
    {
        $this->values[] = $value;
        return $this;
    }
    
    public function getValues(): array
    {
        return $this->values;
    }
    
    public function toArray(): array
    {
        return [
            'type' => 'LIST',
            'name' => $this->name,
            'values' => $this->values,
            'schema' => $this->schema,
            'tablespace' => $this->tablespace
        ];
    }
}