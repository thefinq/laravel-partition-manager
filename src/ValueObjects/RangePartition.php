<?php

namespace Finq\LaravelPartitionManager\ValueObjects;

class RangePartition extends PartitionDefinition
{
    protected $from;
    protected $to;
    
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
    
    public function toSql(): string
    {
        $fromValue = $this->formatValue($this->from);
        $toValue = $this->formatValue($this->to);
        
        $sql = "PARTITION {$this->name} FOR VALUES FROM ({$fromValue}) TO ({$toValue})";
        
        if ($this->schema) {
            $sql .= " TABLESPACE {$this->schema}";
        }
        
        return $sql;
    }
    
    private function formatValue($value): string
    {
        if ($value === 'MINVALUE' || $value === 'MAXVALUE') {
            return $value;
        }
        
        if ($value instanceof \DateTime) {
            return "'" . $value->format('Y-m-d') . "'";
        }
        
        return is_numeric($value) ? $value : "'{$value}'";
    }
}