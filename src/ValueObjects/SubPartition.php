<?php

namespace Finq\LaravelPartitionManager\ValueObjects;

abstract class SubPartition
{
    protected string $name;
    protected ?string $schema = null;
    protected ?string $tablespace = null;
    
    public function __construct(string $name)
    {
        $this->name = $name;
    }
    
    public function withSchema(string $schema): self
    {
        $this->schema = $schema;
        return $this;
    }
    
    public function withTablespace(string $tablespace): self
    {
        $this->tablespace = $tablespace;
        return $this;
    }
    
    public function getName(): string
    {
        return $this->name;
    }
    
    public function getSchema(): ?string
    {
        return $this->schema;
    }
    
    public function getTablespace(): ?string
    {
        return $this->tablespace;
    }
    
    abstract public function toArray(): array;
}