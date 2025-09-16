<?php

namespace Finq\LaravelPartitionManager\ValueObjects;

class HashSubPartition extends SubPartition
{
    protected int $modulus;
    protected int $remainder;
    
    public static function create(string $name): self
    {
        return new self($name);
    }
    
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
    
    public function toArray(): array
    {
        return [
            'type' => 'HASH',
            'name' => $this->name,
            'modulus' => $this->modulus,
            'remainder' => $this->remainder,
            'schema' => $this->schema,
            'tablespace' => $this->tablespace
        ];
    }
}