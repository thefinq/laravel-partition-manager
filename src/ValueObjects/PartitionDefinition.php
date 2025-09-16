<?php

namespace Finq\LaravelPartitionManager\ValueObjects;

use Finq\LaravelPartitionManager\Builders\SubPartitionBuilder;

class PartitionDefinition
{
    protected string $name;
    protected string $type;
    protected ?string $schema = null;
    protected ?SubPartitionBuilder $subPartitions = null;
    
    private function __construct(string $name, string $type)
    {
        $this->name = $name;
        $this->type = strtoupper($type);
    }
    
    public static function list(string $name): self
    {
        return new self($name, 'LIST');
    }
    
    public static function range(string $name): self
    {
        return new self($name, 'RANGE');
    }
    
    public static function hash(string $name): self
    {
        return new self($name, 'HASH');
    }
    
    public function withSchema(string $schema): self
    {
        $this->schema = $schema;
        return $this;
    }
    
    public function withSubPartitions(SubPartitionBuilder $builder): self
    {
        $this->subPartitions = $builder;
        return $this;
    }
    
    public function getName(): string
    {
        return $this->name;
    }
    
    public function getType(): string
    {
        return $this->type;
    }
    
    public function getSchema(): ?string
    {
        return $this->schema;
    }
    
    public function getSubPartitions(): ?SubPartitionBuilder
    {
        return $this->subPartitions;
    }
    
    public function hasSubPartitions(): bool
    {
        return $this->subPartitions !== null;
    }
}