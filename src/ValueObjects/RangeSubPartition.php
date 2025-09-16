<?php

namespace Finq\LaravelPartitionManager\ValueObjects;

class RangeSubPartition extends SubPartition
{
    protected $from;
    protected $to;
    
    public static function create(string $name): self
    {
        return new self($name);
    }
    
    public function withRange($from, $to): self
    {
        $this->from = $from;
        $this->to = $to;
        return $this;
    }
    
    public function getFrom()
    {
        return $this->from;
    }
    
    public function getTo()
    {
        return $this->to;
    }
    
    public function toArray(): array
    {
        return [
            'type' => 'RANGE',
            'name' => $this->name,
            'from' => $this->from,
            'to' => $this->to,
            'schema' => $this->schema,
            'tablespace' => $this->tablespace
        ];
    }
}