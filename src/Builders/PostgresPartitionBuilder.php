<?php

namespace Finq\LaravelPartitionManager\Builders;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Schema\Blueprint;
use Finq\LaravelPartitionManager\Exceptions\PartitionException;
use Finq\LaravelPartitionManager\ValueObjects\ListPartition;
use Finq\LaravelPartitionManager\ValueObjects\RangePartition;
use Finq\LaravelPartitionManager\ValueObjects\HashPartition;
use Finq\LaravelPartitionManager\ValueObjects\PartitionDefinition;
use Finq\LaravelPartitionManager\Services\PartitionSchemaManager;

class PostgresPartitionBuilder
{
    protected string $table;
    protected ?Blueprint $blueprint = null;
    protected ?string $connection = null;
    protected string $partitionType = 'RANGE';
    protected $partitionColumn = null;
    protected array $partitions = [];
    protected array $indexes = [];
    protected ?string $tablespace = null;
    protected PartitionSchemaManager $schemaManager;
    protected bool $enablePartitionPruning = true;
    protected ?PartitionDefinition $defaultPartition = null;
    protected array $checkConstraints = [];
    protected bool $detachConcurrently = false;

    public function __construct(string $table)
    {
        $this->table = $table;
        $this->schemaManager = new PartitionSchemaManager();
        $this->enablePartitionPruning = config('partition-manager.defaults.enable_partition_pruning', true);
        $this->detachConcurrently = config('partition-manager.defaults.detach_concurrently', false);
    }

    public function setBlueprint(Blueprint $blueprint): self
    {
        $this->blueprint = $blueprint;
        return $this;
    }

    public function connection(string $connection): self
    {
        $this->connection = $connection;
        return $this;
    }

    public function partition(string $type): self
    {
        $this->partitionType = strtoupper($type);
        return $this;
    }

    public function range(): self
    {
        return $this->partition('RANGE');
    }

    public function list(): self
    {
        return $this->partition('LIST');
    }

    public function hash(): self
    {
        return $this->partition('HASH');
    }

    public function partitionBy($columns): self
    {
        if (is_array($columns)) {
            $this->partitionColumn = implode(', ', $columns);
        } else {
            $this->partitionColumn = $columns;
        }
        return $this;
    }

    public function partitionByExpression(string $expression): self
    {
        $this->partitionColumn = $expression;
        return $this;
    }

    public function partitionByYear(string $column): self
    {
        $this->partitionColumn = "EXTRACT(YEAR FROM {$column})";
        return $this;
    }

    public function partitionByMonth(string $column): self
    {
        $this->partitionColumn = "DATE_TRUNC('month', {$column})";
        return $this;
    }

    public function partitionByDay(string $column): self
    {
        $this->partitionColumn = "DATE_TRUNC('day', {$column})";
        return $this;
    }

    public function addPartition(PartitionDefinition $partition): self
    {
        $this->partitions[] = $partition;
        return $this;
    }

    public function addRangePartition(string $name, $from, $to, ?string $schema = null): self
    {
        $partition = RangePartition::range($name)->withRange($from, $to);
        
        if ($schema) {
            $partition->withSchema($schema);
        }
        
        $this->partitions[] = $partition;
        return $this;
    }

    public function addListPartition(string $name, array $values, ?string $schema = null): self
    {
        $partition = ListPartition::list($name)->withValues($values);
        
        if ($schema) {
            $partition->withSchema($schema);
        }
        
        $this->partitions[] = $partition;
        return $this;
    }

    public function addHashPartition(string $name, int $modulus, int $remainder, ?string $schema = null): self
    {
        $partition = HashPartition::hash($name)->withHash($modulus, $remainder);
        
        if ($schema) {
            $partition->withSchema($schema);
        }
        
        $this->partitions[] = $partition;
        return $this;
    }
    
    public function withSubPartitions(string $partitionName, SubPartitionBuilder $builder): self
    {
        foreach ($this->partitions as $partition) {
            if ($partition instanceof PartitionDefinition && $partition->getName() === $partitionName) {
                $partition->withSubPartitions($builder);
                break;
            }
        }
        return $this;
    }

    public function generateMonthlyPartitions(): self
    {
        $builder = DateRangeBuilder::monthly();
        return $this->generatePartitions($builder);
    }
    
    public function generateYearlyPartitions(): self
    {
        $builder = DateRangeBuilder::yearly();
        return $this->generatePartitions($builder);
    }
    
    public function generateDailyPartitions(): self
    {
        $builder = DateRangeBuilder::daily();
        return $this->generatePartitions($builder);
    }
    
    public function generateWeeklyPartitions(): self
    {
        $builder = DateRangeBuilder::weekly();
        return $this->generatePartitions($builder);
    }
    
    public function generateQuarterlyPartitions(): self
    {
        $builder = DateRangeBuilder::quarterly();
        return $this->generatePartitions($builder);
    }
    
    public function generatePartitions(DateRangeBuilder $builder): self
    {
        $partitions = $builder->build($this->table . '_');
        
        foreach ($partitions as $partition) {
            $this->addPartition($partition);
        }
        
        return $this;
    }
    
    public function withDateRange(DateRangeBuilder $builder): self
    {
        return $this->generatePartitions($builder);
    }

    public function hashPartitions(int $count, string $prefix = ''): self
    {
        $separator = config('partition-manager.naming.separator', '_');
        
        for ($i = 0; $i < $count; $i++) {
            $partitionName = ($prefix ?: $this->table . '_part' . $separator) . $i;
            $this->addHashPartition($partitionName, $count, $i);
        }
        return $this;
    }

    public function withDefaultPartition(string $name = 'default'): self
    {
        $this->defaultPartition = PartitionDefinition::list($name);
        return $this;
    }

    public function tablespace(string $tablespace): self
    {
        $this->tablespace = $tablespace;
        return $this;
    }

    public function partitionSchema(string $schema): self
    {
        $this->schemaManager->setDefault($schema);
        return $this;
    }
    
    public function registerSchema(string $partitionType, string $schema): self
    {
        $this->schemaManager->register($partitionType, $schema);
        return $this;
    }
    
    public function registerSchemas(array $schemas): self
    {
        $this->schemaManager->registerMultiple($schemas);
        return $this;
    }

    public function check(string $name, string $expression): self
    {
        $this->checkConstraints[$name] = $expression;
        return $this;
    }

    public function enablePartitionPruning(bool $enable = true): self
    {
        $this->enablePartitionPruning = $enable;
        return $this;
    }

    public function detachConcurrently(bool $enable = true): self
    {
        $this->detachConcurrently = $enable;
        return $this;
    }

    public function create(): void
    {
        $connection = $this->connection ? DB::connection($this->connection) : DB::connection();
        
        $connection->beginTransaction();
        
        try {
            $this->createPartitionedTable($connection);
            
            foreach ($this->partitions as $partition) {
                $this->createPartition($connection, $partition);
            }
            
            if ($this->defaultPartition) {
                $this->createDefaultPartition($connection);
            }
            
            $this->createIndexes($connection);
            $this->addCheckConstraints($connection);
            
            if (config('partition-manager.defaults.analyze_after_create', true)) {
                $this->analyze();
            }
            
            $connection->commit();
        } catch (\Exception $e) {
            $connection->rollBack();
            throw new PartitionException("Failed to create partitioned table: " . $e->getMessage(), 0, $e);
        }
    }

    public function execute(): void
    {
        $this->create();
    }

    protected function createPartitionedTable($connection): void
    {
        if (!$this->blueprint) {
            throw new PartitionException("Blueprint not set. Use Partition::create() or Partition::table() to define the table structure.");
        }

        if (!$this->partitionColumn) {
            throw new PartitionException("Partition column not specified. Use partitionBy() to set the partition column.");
        }

        $statements = $this->blueprint->toSql($connection, $connection->getSchemaGrammar());
        
        if (!empty($statements)) {
            $createStatement = $statements[0];
            
            $createStatement = rtrim($createStatement, ';');
            $createStatement = rtrim($createStatement, ')');
            
            $createStatement .= ") PARTITION BY {$this->partitionType} ({$this->partitionColumn})";
            
            if ($this->tablespace) {
                $createStatement .= " TABLESPACE {$this->tablespace}";
            }
            
            $connection->statement($createStatement);
            
            for ($i = 1; $i < count($statements); $i++) {
                if (!str_contains(strtolower($statements[$i]), 'index')) {
                    $connection->statement($statements[$i]);
                }
            }
        }
    }

    protected function createPartition($connection, PartitionDefinition $partition): void
    {
        $partitionTable = $partition->getName();
        $separator = config('partition-manager.naming.separator', '_');
        
        if (!str_starts_with($partitionTable, $this->table)) {
            $partitionTable = $this->table . $separator . $partition->getName();
        }
        
        $schema = $partition->getSchema() ?? $this->schemaManager->getDefault();
        
        if ($schema) {
            $connection->statement("CREATE SCHEMA IF NOT EXISTS {$schema}");
            $partitionTable = $schema . '.' . $partitionTable;
        }
        
        $sql = "CREATE TABLE IF NOT EXISTS {$partitionTable} PARTITION OF {$this->table} ";
        
        if ($partition instanceof RangePartition) {
            $from = $partition->getFrom();
            $to = $partition->getTo();
            
            if (is_numeric($from)) {
                $sql .= "FOR VALUES FROM ({$from}) TO ({$to})";
            } else {
                $sql .= "FOR VALUES FROM ('{$from}') TO ('{$to}')";
            }
        } elseif ($partition instanceof ListPartition) {
            $values = array_map(function($v) {
                return is_numeric($v) ? $v : "'$v'";
            }, $partition->getValues());
            $sql .= "FOR VALUES IN (" . implode(', ', $values) . ")";
        } elseif ($partition instanceof HashPartition) {
            $sql .= "FOR VALUES WITH (modulus {$partition->getModulus()}, remainder {$partition->getRemainder()})";
        }
        
        if ($partition->hasSubPartitions()) {
            $subPartitions = $partition->getSubPartitions()->toArray();
            $subPartitionType = strtoupper($subPartitions['partition_by']['type']);
            $subPartitionColumn = $subPartitions['partition_by']['column'];
            $sql .= " PARTITION BY {$subPartitionType} ({$subPartitionColumn})";
        }
        
        $connection->statement($sql);
        
        if ($partition->hasSubPartitions()) {
            $subPartitions = $partition->getSubPartitions()->toArray();
            foreach ($subPartitions['partitions'] as $subPartition) {
                $this->createSubPartition($connection, $partitionTable, $subPartition);
            }
        }
    }
    
    protected function createSubPartition($connection, string $parentTable, array $subPartition): void
    {
        $subPartitionTable = $subPartition['name'];
        
        if (!empty($subPartition['schema'])) {
            $connection->statement("CREATE SCHEMA IF NOT EXISTS {$subPartition['schema']}");
            $subPartitionTable = $subPartition['schema'] . '.' . $subPartitionTable;
        }
        
        switch ($subPartition['type']) {
            case 'RANGE':
                $sql = "CREATE TABLE IF NOT EXISTS {$subPartitionTable} PARTITION OF {$parentTable} ";
                if (is_numeric($subPartition['from'])) {
                    $sql .= "FOR VALUES FROM ({$subPartition['from']}) TO ({$subPartition['to']})";
                } else {
                    $sql .= "FOR VALUES FROM ('{$subPartition['from']}') TO ('{$subPartition['to']}')";
                }
                break;
                
            case 'LIST':
                $sql = "CREATE TABLE IF NOT EXISTS {$subPartitionTable} PARTITION OF {$parentTable} ";
                $values = array_map(function($v) {
                    return is_numeric($v) ? $v : "'$v'";
                }, $subPartition['values']);
                $sql .= "FOR VALUES IN (" . implode(', ', $values) . ")";
                break;
                
            default:
                throw new PartitionException("Unknown sub-partition type: {$subPartition['type']}");
        }
        
        if (!empty($subPartition['tablespace'])) {
            $sql .= " TABLESPACE {$subPartition['tablespace']}";
        }
        
        $connection->statement($sql);
    }

    protected function createDefaultPartition($connection): void
    {
        $separator = config('partition-manager.naming.separator', '_');
        $partitionTable = $this->table . $separator . $this->defaultPartition->getName();
        
        $schema = $this->defaultPartition->getSchema() ?? $this->schemaManager->getDefault();
        if ($schema) {
            $connection->statement("CREATE SCHEMA IF NOT EXISTS {$schema}");
            $partitionTable = $schema . '.' . $partitionTable;
        }
        
        $sql = "CREATE TABLE IF NOT EXISTS {$partitionTable} PARTITION OF {$this->table} DEFAULT";
        
        if ($this->tablespace) {
            $sql .= " TABLESPACE {$this->tablespace}";
        }
        
        $connection->statement($sql);
    }

    protected function createIndexes($connection): void
    {
        $commands = $this->blueprint->getCommands();
        
        foreach ($commands as $command) {
            if (in_array($command->name, ['index', 'unique'])) {
                $indexName = $command->index ?? $this->table . '_' . implode('_', $command->columns) . '_index';
                $columns = implode(', ', $command->columns);
                
                $sql = "CREATE ";
                if ($command->name === 'unique') {
                    $sql .= "UNIQUE ";
                }
                $sql .= "INDEX IF NOT EXISTS {$indexName} ON {$this->table} ({$columns})";
                
                $connection->statement($sql);
            }
        }
    }

    protected function addCheckConstraints($connection): void
    {
        foreach ($this->checkConstraints as $name => $expression) {
            $sql = "ALTER TABLE {$this->table} ADD CONSTRAINT {$name} CHECK ({$expression})";
            $connection->statement($sql);
        }
    }

    public function attachPartition(string $tableName, string $partitionName, $from, $to): self
    {
        $connection = $this->connection ? DB::connection($this->connection) : DB::connection();
        
        if (is_numeric($from)) {
            $sql = "ALTER TABLE {$this->table} ATTACH PARTITION {$tableName} FOR VALUES FROM ({$from}) TO ({$to})";
        } else {
            $sql = "ALTER TABLE {$this->table} ATTACH PARTITION {$tableName} FOR VALUES FROM ('{$from}') TO ('{$to}')";
        }
        
        $connection->statement($sql);
        return $this;
    }

    public function detachPartition(string $partitionName, bool $concurrently = null): self
    {
        $connection = $this->connection ? DB::connection($this->connection) : DB::connection();
        $concurrently = $concurrently ?? $this->detachConcurrently;
        
        $sql = "ALTER TABLE {$this->table} DETACH PARTITION {$partitionName}";
        if ($concurrently) {
            $sql .= " CONCURRENTLY";
        }
        
        $connection->statement($sql);
        return $this;
    }

    public function dropPartition(string $partitionName): self
    {
        $connection = $this->connection ? DB::connection($this->connection) : DB::connection();
        $connection->statement("DROP TABLE IF EXISTS {$partitionName} CASCADE");
        
        if (config('partition-manager.defaults.vacuum_after_drop', true)) {
            $this->vacuum();
        }
        
        return $this;
    }

    public function analyze(): self
    {
        $connection = $this->connection ? DB::connection($this->connection) : DB::connection();
        $connection->statement("ANALYZE {$this->table}");
        return $this;
    }

    public function vacuum(bool $full = false): self
    {
        $connection = $this->connection ? DB::connection($this->connection) : DB::connection();
        $sql = $full ? "VACUUM FULL {$this->table}" : "VACUUM {$this->table}";
        $connection->statement($sql);
        return $this;
    }
}