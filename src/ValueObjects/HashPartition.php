<?php

namespace Finq\LaravelPartitionManager\ValueObjects;

class HashPartition extends PartitionDefinition
{
    protected int $modulus;
    protected int $remainder;
    
    public function withHash(int $modulus, int $remainder): self
    {
        $this->modulus = $modulus;
        $this->remainder = $remainder;
        return $this;
    }
    
    public function getModulus(): int
    {
        return $this->modulus;
    }
    
    public function getRemainder(): int
    {
        return $this->remainder;
    }
    
    public function toSql(): string
    {
        $sql = "PARTITION {$this->name} FOR VALUES WITH (modulus {$this->modulus}, remainder {$this->remainder})";
        
        if ($this->schema) {
            $sql .= " TABLESPACE {$this->schema}";
        }
        
        return $sql;
    }
}