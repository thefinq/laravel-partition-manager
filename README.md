# Laravel Partition Manager

A powerful Laravel package for managing PostgreSQL partitioned tables. Supports RANGE, LIST, and HASH partitioning strategies with multi-level sub-partitioning, automatic partition generation, and schema management.

## Table of Contents

- [Installation](#installation)
- [Basic Usage](#basic-usage)
- [Partition Types](#partition-types)
  - [Range Partitioning](#range-partitioning)
  - [List Partitioning](#list-partitioning)
  - [Hash Partitioning](#hash-partitioning)
- [Automatic Partition Generation](#automatic-partition-generation)
  - [Using DateRangeBuilder](#using-daterangebuilder)
  - [Quick Generation Methods](#quick-generation-methods)
- [Schema Management](#schema-management)
  - [Per-Partition Schemas](#per-partition-schemas)
  - [Schema Registration](#schema-registration)
- [Sub-Partitioning](#sub-partitioning)
  - [Multi-Level Partitioning](#multi-level-partitioning)
  - [Using Value Objects](#using-value-objects-for-complex-structures)
- [Partition Management](#partition-management)
  - [Runtime Operations](#runtime-operations)
  - [Table Operations](#table-operations)
- [Advanced Features](#advanced-features)
  - [Default Partitions](#default-partitions)
  - [Check Constraints](#check-constraints)
  - [Partition Pruning](#partition-pruning)
  - [Custom Tablespaces](#custom-tablespaces)
- [Configuration](#configuration)
- [Static Helper Methods](#static-helper-methods)
- [License](#license)

## Installation

Install the package via Composer:

```bash
composer require finq/laravel-partition-manager
```

## Basic Usage

Create a partitioned table using the fluent interface. The package automatically handles partition creation and management.

```php
use Finq\LaravelPartitionManager\Partition;

Partition::create('logs', function($table) {
    $table->id();
    $table->string('type');
    $table->jsonb('data');
    $table->timestamp('created_at');
    $table->index('created_at');
})
->range()
->partitionByMonth('created_at')
->generateMonthlyPartitions()
->create();
```

### Quick Partition Generation (Existing Tables)

For existing tables, you can quickly add partitions without defining the schema:

```php
// One-liner partition generation
Partition::monthly('orders', 'created_at', 12);  // 12 monthly partitions
Partition::yearly('reports', 'year', 5);         // 5 yearly partitions
Partition::daily('logs', 'log_date', 30);        // 30 daily partitions

// Or use the builder for more control
Partition::generate('events')
    ->by('created_at')
    ->schema('event_partitions')
    ->monthly(24);  // 24 monthly partitions
```

## Partition Types

### Range Partitioning

Divide data based on ranges of values, commonly used for date-based partitioning.

```php
// By date
Partition::create('orders', function($table) {
    $table->id();
    $table->decimal('amount');
    $table->date('order_date');
})
->range()
->partitionBy('order_date')
->addRangePartition('orders_2024_q1', '2024-01-01', '2024-04-01')
->addRangePartition('orders_2024_q2', '2024-04-01', '2024-07-01')
->create();
```

### List Partitioning

Partition data based on discrete values, perfect for categorizing by specific attributes.

```php
Partition::create('users', function($table) {
    $table->id();
    $table->string('country');
    $table->string('email');
})
->list()
->partitionBy('country')
->addListPartition('users_us', ['US', 'CA'])
->addListPartition('users_eu', ['DE', 'FR', 'IT', 'ES'])
->addListPartition('users_asia', ['JP', 'CN', 'KR'])
->create();
```

### Hash Partitioning

Distribute data evenly across partitions using a hash function, ideal for load balancing.

```php
Partition::create('events', function($table) {
    $table->id();
    $table->string('event_type');
    $table->jsonb('payload');
})
->hash()
->partitionBy('id')
->hashPartitions(4) // Creates 4 hash partitions
->create();
```

## Automatic Partition Generation

### Using DateRangeBuilder

Create date-based partitions with flexible configuration using the DateRangeBuilder class.

```php
use Finq\LaravelPartitionManager\Builders\DateRangeBuilder;

Partition::create('metrics', function($table) {
    $table->id();
    $table->float('value');
    $table->timestamp('recorded_at');
})
->range()
->partitionByDay('recorded_at')
->withDateRange(
    DateRangeBuilder::daily()
        ->from('2024-01-01')
        ->count(30)
        ->defaultSchema('daily_metrics')
)
->create();
```

### Quick Generation Methods

Use convenient methods to automatically generate common partition patterns without manual configuration.

```php
// For new tables (with schema definition)
->generateMonthlyPartitions()  // 12 monthly partitions from current date
->generateYearlyPartitions()   // 5 yearly partitions from current year
->generateWeeklyPartitions()   // 12 weekly partitions from current week
->generateDailyPartitions()    // 30 daily partitions from today
->generateQuarterlyPartitions() // 8 quarterly partitions from current quarter

// For existing tables (simpler syntax)
Partition::generate('logs')
    ->by('created_at')
    ->monthly(12);  // Generate 12 monthly partitions

Partition::generate('metrics')
    ->by('recorded_at')
    ->schema('metric_partitions')  // Optional schema
    ->daily(7);  // Generate 7 daily partitions

// List partitions for existing tables
Partition::generate('regions')
    ->byList('country', [
        'us' => ['US', 'CA'],
        'eu' => ['DE', 'FR', 'ES'],
        'asia' => ['JP', 'CN', 'KR']
    ]);

// Hash partitions for existing tables
Partition::generate('users')
    ->byHash('id', 8);  // 8 hash partitions
```

## Schema Management

### Per-Partition Schemas

Organize partitions into different PostgreSQL schemas for better data organization and access control.

```php
Partition::create('logs', function($table) {
    $table->id();
    $table->string('level');
    $table->text('message');
    $table->timestamp('logged_at');
})
->range()
->partitionByMonth('logged_at')
->partitionSchema('log_partitions') // Default schema for all partitions
->addRangePartition('logs_2024_01', '2024-01-01', '2024-02-01', 'archive_logs')
->addRangePartition('logs_2024_02', '2024-02-01', '2024-03-01', 'current_logs')
->create();
```

### Schema Registration

Register multiple schemas for different partition types to automatically organize related partitions.

```php
->registerSchemas([
    'error' => 'error_log_schema',
    'info' => 'info_log_schema',
    'debug' => 'debug_log_schema'
])
```

## Sub-Partitioning

### Multi-Level Partitioning

Create hierarchical partition structures with sub-partitions for complex data organization needs.

```php
use Finq\LaravelPartitionManager\Builders\SubPartitionBuilder;

Partition::create('events', function($table) {
    $table->id();
    $table->string('type');
    $table->boolean('processed');
    $table->timestamp('created_at');
})
->range()
->partitionByMonth('created_at')
->addRangePartition('events_2024_01', '2024-01-01', '2024-02-01')
->withSubPartitions('events_2024_01', 
    SubPartitionBuilder::list('type')
        ->addListPartition('events_2024_01_user', ['login', 'logout', 'signup'])
        ->addListPartition('events_2024_01_system', ['error', 'warning', 'info'])
)
->create();
```

### Using Value Objects for Complex Structures

Build complex partition hierarchies using type-safe value objects for better code organization.

```php
use Finq\LaravelPartitionManager\ValueObjects\RangePartition;
use Finq\LaravelPartitionManager\ValueObjects\ListSubPartition;

$partition = RangePartition::range('data_2024_01')
    ->withRange('2024-01-01', '2024-02-01')
    ->withSchema('monthly_data')
    ->withSubPartitions(
        SubPartitionBuilder::list('status')
            ->add(ListSubPartition::create('data_2024_01_active')
                ->withValues(['active', 'pending'])
                ->withSchema('active_data'))
            ->add(ListSubPartition::create('data_2024_01_archived')
                ->withValues(['completed', 'cancelled'])
                ->withSchema('archive_data'))
    );

$builder->addPartition($partition);
```

## Partition Management

### Runtime Operations

Manage partitions dynamically at runtime with the PartitionManager service.

```php
use Finq\LaravelPartitionManager\Services\PartitionManager;

$manager = app(PartitionManager::class);

// List all partitions for a table
$partitions = $manager->getPartitions('logs');

// Get partition sizes and statistics
$sizes = $manager->getPartitionSizes('logs');

// Drop partitions older than specified date
$dropped = $manager->dropOldPartitions('logs', new DateTime('-6 months'));

// Check if a specific partition exists
if ($manager->partitionExists('logs', 'logs_2024_01')) {
    // Partition exists
}
```

### Table Operations

Perform maintenance and management operations on partitioned tables.

```php
$builder = new PostgresPartitionBuilder('orders');

// Attach an existing table as a partition
$builder->attachPartition('old_orders', 'orders_2023', '2023-01-01', '2024-01-01');

// Detach a partition (with optional CONCURRENTLY)
$builder->detachPartition('orders_2023', true);

// Drop a specific partition
$builder->dropPartition('orders_2023');

// Maintenance operations
$builder->analyze();        // Update table statistics
$builder->vacuum();         // Reclaim storage
$builder->vacuum(true);     // VACUUM FULL for complete rebuild
```

## Advanced Features

### Default Partitions

Create a default partition to catch rows that don't match any defined partition criteria.

```php
->withDefaultPartition('others') // Catches unmatched rows
```

### Check Constraints

Add check constraints to ensure data integrity across all partitions.

```php
->check('positive_amount', 'amount > 0')
->check('valid_status', "status IN ('pending', 'completed', 'cancelled')")
```

### Partition Pruning

Control query optimization settings for better performance.

```php
->enablePartitionPruning() // Enable query optimization (default: true)
->detachConcurrently()     // Use CONCURRENTLY for non-blocking operations
```

### Custom Tablespaces

Assign partitions to specific tablespaces for storage optimization.

```php
->tablespace('fast_ssd')
->addRangePartition('hot_data', '2024-01-01', '2024-02-01')
```

## Configuration

Publish and customize the configuration file to set default behaviors.

```bash
php artisan vendor:publish --tag=partition-manager-config
```

```php
// config/partition-manager.php
return [
    'defaults' => [
        'enable_partition_pruning' => true,
        'detach_concurrently' => false,
        'analyze_after_create' => true,
        'vacuum_after_drop' => true,
    ],
    'naming' => [
        'separator' => '_',
        'date_format' => 'Y_m',
        'day_format' => 'Y_m_d',
    ],
];
```

## Static Helper Methods

Utility methods for quick partition operations and checks.

```php
use Finq\LaravelPartitionManager\Partition;

// Check if a table is partitioned
if (Partition::isPartitioned('logs')) {
    // Get list of all partitions
    $partitions = Partition::getPartitions('logs');
}

// Check if a specific partition exists
if (Partition::partitionExists('logs', 'logs_2024_01')) {
    // Partition exists
}

// Drop a table and all its partitions
Partition::dropIfExists('logs');
```

## License

MIT