<?php

namespace Finq\LaravelPartitionManager\Builders;

use Finq\LaravelPartitionManager\ValueObjects\SubPartition;
use Finq\LaravelPartitionManager\ValueObjects\ListSubPartition;
use Finq\LaravelPartitionManager\ValueObjects\RangeSubPartition;
use Finq\LaravelPartitionManager\ValueObjects\HashSubPartition;

class SubPartitionBuilder
{
    protected array $partitions = [];
    protected string $partitionType = 'LIST';
    protected string $partitionColumn;
    protected ?string $defaultSchema = null;

    public function __construct(string $type, string $column)
    {
        $this->partitionType = strtoupper($type);
        $this->partitionColumn = $column;
    }

    public static function list(string $column): self
    {
        return new self('LIST', $column);
    }

    public static function range(string $column): self
    {
        return new self('RANGE', $column);
    }

    public static function hash(string $column): self
    {
        return new self('HASH', $column);
    }

    public function defaultSchema(string $schema): self
    {
        $this->defaultSchema = $schema;
        return $this;
    }

    public function add(SubPartition $partition): self
    {
        if ($this->defaultSchema && !$partition->getSchema()) {
            $partition->withSchema($this->defaultSchema);
        }
        
        $this->partitions[] = $partition;
        return $this;
    }

    public function addListPartition(string $name, array $values, ?string $schema = null): self
    {
        $partition = ListSubPartition::create($name)->withValues($values);
        
        if ($schema) {
            $partition->withSchema($schema);
        } elseif ($this->defaultSchema) {
            $partition->withSchema($this->defaultSchema);
        }
        
        $this->partitions[] = $partition;
        return $this;
    }

    public function addRangePartition(string $name, $from, $to, ?string $schema = null): self
    {
        $partition = RangeSubPartition::create($name)->withRange($from, $to);
        
        if ($schema) {
            $partition->withSchema($schema);
        } elseif ($this->defaultSchema) {
            $partition->withSchema($this->defaultSchema);
        }
        
        $this->partitions[] = $partition;
        return $this;
    }
    
    public function addHashPartition(string $name, int $modulus, int $remainder, ?string $schema = null): self
    {
        $partition = HashSubPartition::create($name)->withHash($modulus, $remainder);
        
        if ($schema) {
            $partition->withSchema($schema);
        } elseif ($this->defaultSchema) {
            $partition->withSchema($this->defaultSchema);
        }
        
        $this->partitions[] = $partition;
        return $this;
    }

    public function getPartitions(): array
    {
        return $this->partitions;
    }

    public function toArray(): array
    {
        return [
            'partition_by' => [
                'type' => $this->partitionType,
                'column' => $this->partitionColumn
            ],
            'partitions' => array_map(function(SubPartition $partition) {
                return $partition->toArray();
            }, $this->partitions)
        ];
    }
}