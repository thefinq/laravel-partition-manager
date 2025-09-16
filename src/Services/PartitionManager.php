<?php

namespace Finq\LaravelPartitionManager\Services;

use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\DB;
use Finq\LaravelPartitionManager\Exceptions\PartitionException;

class PartitionManager
{
    protected DatabaseManager $db;

    public function __construct(DatabaseManager $db)
    {
        $this->db = $db;
    }

    public function getPartitions(string $table, ?string $connection = null): array
    {
        $connection = $connection ?: config('partition-manager.default_connection');
        
        $result = $this->db->connection($connection)->select("
            SELECT 
                inhrelid::regclass AS partition_name,
                pg_get_expr(relpartbound, inhrelid) AS partition_expression,
                pg_size_pretty(pg_relation_size(inhrelid)) AS size,
                pg_stat_get_live_tuples(inhrelid) AS row_count
            FROM pg_inherits
            JOIN pg_class ON pg_inherits.inhrelid = pg_class.oid
            WHERE inhparent = ?::regclass
            ORDER BY inhrelid::regclass::text
        ", [$table]);
        
        return $result;
    }

    public function getPartitionInfo(string $table, string $partitionName, ?string $connection = null): ?object
    {
        $connection = $connection ?: config('partition-manager.default_connection');
        
        $result = $this->db->connection($connection)->selectOne("
            SELECT 
                c.relname AS partition_name,
                pg_get_expr(c.relpartbound, c.oid) AS partition_expression,
                pg_size_pretty(pg_relation_size(c.oid)) AS size,
                pg_stat_get_live_tuples(c.oid) AS row_count,
                n.nspname AS schema_name,
                t.spcname AS tablespace
            FROM pg_class c
            JOIN pg_namespace n ON n.oid = c.relnamespace
            LEFT JOIN pg_tablespace t ON t.oid = c.reltablespace
            WHERE c.relname = ?
        ", [$partitionName]);
        
        return $result;
    }

    public function isPartitioned(string $table, ?string $connection = null): bool
    {
        $connection = $connection ?: config('partition-manager.default_connection');
        
        $result = $this->db->connection($connection)->select("
            SELECT relkind 
            FROM pg_class 
            WHERE relname = ? 
            AND relkind = 'p'
        ", [$table]);
        
        return !empty($result);
    }

    public function getPartitionStrategy(string $table, ?string $connection = null): ?string
    {
        $connection = $connection ?: config('partition-manager.default_connection');
        
        $result = $this->db->connection($connection)->selectOne("
            SELECT partstrat
            FROM pg_partitioned_table pt
            JOIN pg_class c ON c.oid = pt.partrelid
            WHERE c.relname = ?
        ", [$table]);
        
        if (!$result) {
            return null;
        }
        
        return match($result->partstrat) {
            'r' => 'RANGE',
            'l' => 'LIST',
            'h' => 'HASH',
            default => null
        };
    }

    public function getPartitionColumns(string $table, ?string $connection = null): array
    {
        $connection = $connection ?: config('partition-manager.default_connection');
        
        $result = $this->db->connection($connection)->select("
            SELECT 
                a.attname AS column_name,
                pg_catalog.format_type(a.atttypid, a.atttypmod) AS data_type
            FROM pg_partitioned_table pt
            JOIN pg_class c ON c.oid = pt.partrelid
            JOIN pg_attribute a ON a.attrelid = c.oid
            WHERE c.relname = ?
            AND a.attnum = ANY(pt.partattrs)
            ORDER BY a.attnum
        ", [$table]);
        
        return $result;
    }

    public function analyzePartition(string $partitionName, ?string $connection = null): void
    {
        $connection = $connection ?: config('partition-manager.default_connection');
        $this->db->connection($connection)->statement("ANALYZE {$partitionName}");
    }

    public function vacuumPartition(string $partitionName, bool $full = false, ?string $connection = null): void
    {
        $connection = $connection ?: config('partition-manager.default_connection');
        $sql = $full ? "VACUUM FULL {$partitionName}" : "VACUUM {$partitionName}";
        $this->db->connection($connection)->statement($sql);
    }

    public function dropOldPartitions(string $table, \DateTime $before, ?string $connection = null): array
    {
        $connection = $connection ?: config('partition-manager.default_connection');
        $dropped = [];
        
        $partitions = $this->getPartitions($table, $connection);
        
        foreach ($partitions as $partition) {
            if ($this->shouldDropPartition($partition, $before)) {
                try {
                    $this->db->connection($connection)->statement("DROP TABLE IF EXISTS {$partition->partition_name} CASCADE");
                    $dropped[] = $partition->partition_name;
                    
                    if (config('partition-manager.defaults.vacuum_after_drop', true)) {
                        $this->vacuumPartition($table, false, $connection);
                    }
                } catch (\Exception $e) {
                    throw new PartitionException("Failed to drop partition {$partition->partition_name}: " . $e->getMessage());
                }
            }
        }
        
        return $dropped;
    }

    protected function shouldDropPartition(object $partition, \DateTime $before): bool
    {
        if (preg_match('/FROM \(\'(\d{4}-\d{2}-\d{2})\'\)/', $partition->partition_expression, $matches)) {
            $partitionDate = new \DateTime($matches[1]);
            return $partitionDate < $before;
        }
        
        return false;
    }

    public function getTableSize(string $table, ?string $connection = null): string
    {
        $connection = $connection ?: config('partition-manager.default_connection');
        
        $result = $this->db->connection($connection)->selectOne("
            SELECT pg_size_pretty(pg_total_relation_size(?::regclass)) AS size
        ", [$table]);
        
        return $result->size ?? '0 bytes';
    }

    public function getPartitionCount(string $table, ?string $connection = null): int
    {
        $connection = $connection ?: config('partition-manager.default_connection');
        
        $result = $this->db->connection($connection)->selectOne("
            SELECT COUNT(*) as count
            FROM pg_inherits
            WHERE inhparent = ?::regclass
        ", [$table]);
        
        return (int) ($result->count ?? 0);
    }

    public function getOldestPartition(string $table, ?string $connection = null): ?object
    {
        $connection = $connection ?: config('partition-manager.default_connection');
        
        $partitions = $this->getPartitions($table, $connection);
        return $partitions[0] ?? null;
    }

    public function getNewestPartition(string $table, ?string $connection = null): ?object
    {
        $connection = $connection ?: config('partition-manager.default_connection');
        
        $partitions = $this->getPartitions($table, $connection);
        return end($partitions) ?: null;
    }
}