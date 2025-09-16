<?php

namespace Finq\LaravelPartitionManager\ValueObjects;

class ListPartition extends PartitionDefinition
{
    protected array $values = [];
    
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
    
    public function toSql(): string
    {
        $valueList = implode(', ', array_map(function($value) {
            return is_numeric($value) ? $value : "'{$value}'";
        }, $this->values));
        
        $sql = "PARTITION {$this->name} FOR VALUES IN ({$valueList})";
        
        if ($this->schema) {
            $sql .= " TABLESPACE {$this->schema}";
        }
        
        return $sql;
    }
}