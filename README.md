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
  - [PartitionSchemaManager Service](#partitionschemamanager-service)
- [Sub-Partitioning](#sub-partitioning)
  - [Multi-Level Partitioning](#multi-level-partitioning)
  - [Using Value Objects](#using-value-objects-for-complex-structures)
- [Partition Management](#partition-management)
  - [Runtime Operations](#runtime-operations)
  - [Table Operations](#table-operations)
  - [Partition Queries and Statistics](#partition-queries-and-statistics)
- [Advanced Features](#advanced-features)
  - [Default Partitions](#default-partitions)
  - [Check Constraints](#check-constraints)
  - [Partition Pruning](#partition-pruning)
  - [Custom Tablespaces](#custom-tablespaces)
  - [Custom Partition Expressions](#custom-partition-expressions)
  - [Multi-Column Partitioning](#multi-column-partitioning)
- [Configuration](#configuration)
- [Static Helper Methods](#static-helper-methods)
- [Using Facades](#using-facades)
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

**Note:** The schema definition callback uses Laravel's `Blueprint` class, giving you access to all standard Laravel column types and index methods. All operations are wrapped in database transactions for safety - if any partition fails to create, the entire operation is rolled back.

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

// Quick static methods for existing tables
Partition::monthly('logs', 'created_at', 12);  // 12 monthly partitions
Partition::yearly('reports', 'year', 5);       // 5 yearly partitions
Partition::daily('logs', 'log_date', 30);      // 30 daily partitions
Partition::weekly('events', 'event_date', 12); // 12 weekly partitions
Partition::quarterly('metrics', 'quarter', 8); // 8 quarterly partitions

// Or use the builder for more control
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

Organize partitions into different PostgreSQL schemas for better data organization and access control. Schemas are automatically created if they don't exist.

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

**Note:** PostgreSQL schemas will be automatically created if they don't exist when the partitions are created.

### Schema Registration

Register multiple schemas for different partition types to automatically organize related partitions.

```php
->registerSchemas([
    'error' => 'error_log_schema',
    'info' => 'info_log_schema',
    'debug' => 'debug_log_schema'
])
```

### PartitionSchemaManager Service

Use the dedicated schema management service for advanced schema handling and organization.

```php
use Finq\LaravelPartitionManager\Services\PartitionSchemaManager;

$schemaManager = new PartitionSchemaManager();

// Set default schema for all partitions
$schemaManager->setDefault('default_partitions');

// Register schemas for specific partition types
$schemaManager->register('error', 'error_log_schema')
              ->register('info', 'info_log_schema')
              ->register('debug', 'debug_log_schema');

// Register multiple schemas at once
$schemaManager->registerMultiple([
    'active' => 'active_data_schema',
    'archived' => 'archive_schema'
]);

// Query schema configurations
$errorSchema = $schemaManager->getSchemaFor('error');
$hasSchema = $schemaManager->hasSchemaFor('info');
$defaultSchema = $schemaManager->getDefault();
$allSchemas = $schemaManager->getAllSchemas();

// Clear all schema mappings
$schemaManager->clear();
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

### Partition Queries and Statistics

Query detailed partition information, statistics, and metadata at runtime.

```php
use Finq\LaravelPartitionManager\Services\PartitionManager;

$manager = app(PartitionManager::class);

// Get detailed partition information
$partitionInfo = $manager->getPartitionInfo('logs', 'logs_2024_01');
// Returns: object with size, row_count, schema, tablespace

// Get partition strategy/type
$strategy = $manager->getPartitionStrategy('logs');
// Returns: 'RANGE', 'LIST', or 'HASH'

// Get partition columns and their data types
$columns = $manager->getPartitionColumns('logs');
// Returns: [['column' => 'created_at', 'data_type' => 'timestamp without time zone']]

// Get total table size
$tableSize = $manager->getTableSize('logs');
// Returns: Human-readable size like '2456 MB'

// Count total partitions
$count = $manager->getPartitionCount('logs');

// Get oldest and newest partitions
$oldestPartition = $manager->getOldestPartition('logs');
$newestPartition = $manager->getNewestPartition('logs');

// Maintenance operations on specific partitions
$manager->analyzePartition('logs_2024_01');
$manager->vacuumPartition('logs_2024_01');
$manager->vacuumPartition('logs_2024_01', true); // VACUUM FULL
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

### Custom Partition Expressions

Define custom SQL expressions for partition keys when built-in methods are not sufficient.

```php
// Custom expression for complex date operations
Partition::create('events', function($table) {
    $table->id();
    $table->timestamp('event_time');
    $table->string('timezone');
})
->range()
->partitionByExpression("DATE_TRUNC('week', event_time AT TIME ZONE timezone)")
->addRangePartition('events_week_1', '2024-01-01', '2024-01-08')
->create();

// Extract specific date parts
->partitionByExpression("EXTRACT(YEAR FROM order_date)")
->addRangePartition('orders_2024', 2024, 2025)

// Custom calculations
->partitionByExpression("(amount / 100)::int")
->addRangePartition('tier_1', 0, 10)
```

### Multi-Column Partitioning

Partition tables using multiple columns for more granular data organization.

```php
// Partition by multiple columns (composite key)
Partition::create('sales', function($table) {
    $table->id();
    $table->string('region');
    $table->integer('year');
    $table->decimal('amount');
})
->range()
->partitionBy(['region', 'year'])  // Array of columns
->addRangePartition('sales_us_2024', ['US', 2024], ['US', 2025])
->addRangePartition('sales_eu_2024', ['EU', 2024], ['EU', 2025])
->create();

// Combine columns with expressions
->partitionBy(['country', 'DATE_TRUNC(\'month\', created_at)'])
```

## Configuration

Publish and customize the configuration file to set default behaviors.

```bash
php artisan vendor:publish --tag=partition-manager-config
```

```php
// config/partition-manager.php
return [
    // Default database connection to use (defaults to Laravel's default)
    'default_connection' => env('DB_CONNECTION', 'pgsql'),

    // Default behaviors for partition operations
    'defaults' => [
        'enable_partition_pruning' => true,  // Enable query optimization
        'detach_concurrently' => false,      // Use CONCURRENTLY for detach
        'analyze_after_create' => true,      // Auto-analyze after creation
        'vacuum_after_drop' => true,         // Auto-vacuum after drop
    ],

    // Partition naming conventions
    'naming' => [
        'prefix' => '',           // Prefix for partition names
        'suffix' => '',           // Suffix for partition names
        'separator' => '_',       // Separator in partition names
        'date_format' => 'Y_m',   // PHP date format for monthly partitions
        'day_format' => 'Y_m_d',  // PHP date format for daily partitions
    ],

    // Logging configuration
    'logging' => [
        'enabled' => env('PARTITION_LOGGING', true),
        'channel' => env('PARTITION_LOG_CHANNEL', 'daily'),
    ],
];
```

## Static Helper Methods

Utility methods for quick partition operations and checks.

```php
use Finq\LaravelPartitionManager\Partition;

// Create partitioned table with schema definition
$builder = Partition::create('logs', function($table) {
    $table->id();
    $table->text('message');
    $table->timestamp('created_at');
});

// Alternative alias for create()
$builder = Partition::table('events', function($table) {
    // Define schema...
});

// Quick partition generation for existing tables
Partition::generate('logs')
    ->by('created_at')
    ->monthly(12);

// One-liner partition generation methods
Partition::monthly('orders', 'created_at', 12);    // 12 monthly partitions
Partition::yearly('reports', 'year', 5);           // 5 yearly partitions
Partition::daily('logs', 'log_date', 30);          // 30 daily partitions
Partition::weekly('events', 'event_date', 12);     // 12 weekly partitions
Partition::quarterly('metrics', 'quarter', 8);     // 8 quarterly partitions

// Check if a table is partitioned
if (Partition::isPartitioned('logs')) {
    // Table is partitioned
}

// Get list of all partitions with metadata
$partitions = Partition::getPartitions('logs');
// Returns array of partition objects with names, bounds, sizes, row counts

// Check if a specific partition exists
if (Partition::partitionExists('logs', 'logs_2024_01')) {
    // Partition exists
}

// Drop a table and all its partitions (CASCADE)
Partition::dropIfExists('logs');
```

## Using Facades

Access the PartitionManager service using Laravel facades for cleaner dependency injection-free code.

```php
use Finq\LaravelPartitionManager\Facades\PartitionManager;

// All PartitionManager methods are available as static calls
$partitions = PartitionManager::getPartitions('logs');
$isPartitioned = PartitionManager::isPartitioned('logs');
$strategy = PartitionManager::getPartitionStrategy('logs');
$count = PartitionManager::getPartitionCount('logs');

// Drop old partitions
$dropped = PartitionManager::dropOldPartitions('logs', new DateTime('-6 months'));

// Maintenance operations
PartitionManager::analyzePartition('logs_2024_01');
PartitionManager::vacuumPartition('logs_2024_01', true);
```

Alternatively, use dependency injection or the service container:

```php
use Finq\LaravelPartitionManager\Services\PartitionManager;

// Via dependency injection (recommended)
public function __construct(private PartitionManager $partitionManager)
{
    // Use $this->partitionManager...
}

// Via service container
$manager = app(PartitionManager::class);
// Or
$manager = app('partition-manager');
```

## License

MIT