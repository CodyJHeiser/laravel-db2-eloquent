# Laravel DB2 Eloquent

Laravel Eloquent extensions for IBM DB2 databases with column mapping, multi-column relationships, and auto-filtering.

## Table of Contents

- [Installation](#installation)
- [Basic Usage](#basic-usage)
- [Generating Models](#generating-models)
- [Database Schema](#database-schema)
  - [Testing Database](#testing-database)
- [Column Mapping](#column-mapping)
  - [Casts](#casts)
  - [IBM Date Cast](#ibm-date-cast)
- [Automatic Filtering](#automatic-filtering)
- [Multi-Column Relationships](#multi-column-relationships)
- [Extensions (Joined Tables)](#extensions-joined-tables)
- [Query Logging](#query-logging)
- [Helper Methods](#helper-methods)
- [Configuration](#configuration)
- [Development](#development)
- [License](#license)

## Installation

```bash
composer require codyjheiser/laravel-db2-eloquent
```

### Requirements

- PHP 8.4+
- Laravel 12+

## Basic Usage

```php
use CodyJHeiser\Db2Eloquent\Model;

class Customer extends Model
{
    protected $table = 'VARCUST';
    protected string $schema = 'R60FILES';

    protected array $maps = [
        'RMCUST' => 'customer_number',
        'RMNAME' => 'name',
        'RMADD1' => 'address',
        'RMDEL'  => 'delete_code',
        'RMCMP'  => 'company_number',
    ];
}

// Query with human-readable column names
Customer::where('customer_number', '123')->first();
Item::where('item_number', 'ABC123')->orderBy('manufacturer')->get();
```

## Generating Models

Use the Artisan command to quickly scaffold new DB2 models:

```bash
# Basic usage - creates app/Models/IBM/Customer.php
php artisan make:db2-model Customer VARCUST

# With schema prefix
php artisan make:db2-model Customer R60FILES.VARCUST

# Custom path - creates app/Models/Customer.php
php artisan make:db2-model Customer VARCUST --path=Models
```

The generated model includes the schema, table, casts, and maps properties ready to be filled in.

## Database Schema

Models use a `$schema` property to define the database schema prefix, keeping table definitions DRY.

```php
class Item extends Model
{
    protected string $schema = 'R60FILES';
    protected $table = 'VINITEM';    // Results in: R60FILES.VINITEM
}

class CommissionPricing extends Model
{
    protected string $schema = 'R60FSDTA';
    protected $table = 'FSCOMPBT';   // Results in: R60FSDTA.FSCOMPBT
}
```

If your table already includes the schema prefix (e.g., `R60FILES.VINITEM`), it will be used as-is.

### Testing Database

Use the `testing()` scope to query the testing database instead of production. By default, this swaps `R` to `T` in the schema prefix:

```php
// Production (default)
Customer::first();              // R60FILES.VARCUST
CommissionPricing::first();     // R60FSDTA.FSCOMPBT

// Testing database
Customer::testing()->first();              // T60FILES.VARCUST
CommissionPricing::testing()->first();     // T60FSDTA.FSCOMPBT
```

#### Custom Schema Configuration

You can customize how test schemas are determined:

```php
class MyModel extends Model
{
    // Option 1: Explicit test schema
    protected string $schema = 'PROD_SCHEMA';
    protected ?string $testSchema = 'TEST_SCHEMA';

    // Option 2: Prefix replacement (default behavior)
    protected string $schema = 'R60FILES';
    protected string $schemaPrefixProd = 'R';   // Default: 'R'
    protected string $schemaPrefixTest = 'T';   // Default: 'T'
}
```

**Important:** When using `testing()` with `withExtensions()`, you must call `testing()` FIRST:

```php
// Correct - testing() before withExtensions()
Customer::testing()->withExtensions()->first();

// WRONG - extension tables will still use production schema
Customer::withExtensions()->testing()->first();
```

## Column Mapping

Define `$maps` to translate DB columns to human-readable names:

```php
class Customer extends Model
{
    protected string $schema = 'R60FILES';
    protected $table = 'VARCUST';

    protected array $maps = [
        'RMDEL' => 'delete_code',
        'RMCMP' => 'company_number',
        'RMCUST' => 'customer_number',
        'RMNAME' => 'name',
        'RMADD1' => 'address',
    ];
}
```

- Queries use mapped names: `where('customer_number', '123')`
- Output uses mapped names: `{ "customer_number": "123", "name": "Acme" }`
- Access properties with mapped names: `$customer->customer_number`

### Auto-Select Mapped Columns

By default, queries automatically select only columns defined in `$maps`. This prevents selecting unnecessary columns.

```php
// These are equivalent - selectMapped is applied automatically
Customer::get();
Customer::selectMapped()->get();

// Also applies to eager-loaded relations automatically
Customer::with('orders')->get();
```

### Select All Columns

```php
// Bypass auto-select to get all columns
Customer::selectAll()->get();

// Or use explicit select()
Customer::select('*')->get();
Customer::select(['RMCUST', 'RMNAME', 'SOME_UNMAPPED_COL'])->get();
```

### Disable Auto-Select Per Model

```php
protected bool $autoSelectMapped = false;
```

### Disable Auto-Mapping on Output

```php
protected bool $applyMapsOnOutput = false;
```

### Casts

Laravel casts must use **raw DB column names**, not mapped names:

```php
class CommissionPricing extends Model
{
    protected array $maps = [
        'PBCMP' => 'company_number',
        'PBPSLS' => 'pricing_sales',
    ];

    // Casts use raw DB column names
    protected $casts = [
        'PBCMP' => 'integer',
        'PBPSLS' => 'decimal:2',
    ];
}
```

The `company_number` column is auto-cast to integer for all IBM models.

### IBM Date Cast

IBM stores dates as integers in `Ymd` format (e.g., `20251217`). Use the `IbmDate` cast to convert to Carbon or a formatted string:

```php
use CodyJHeiser\Db2Eloquent\Casts\IbmDate;
use CodyJHeiser\Db2Eloquent\Casts\IbmDateNullable;

protected $casts = [
    // Returns Carbon instance
    'SHSCDT' => IbmDate::class,

    // Returns formatted string
    'SHCLDT' => IbmDate::class.':date',      // "2025-12-17"
    'SHCLDT' => IbmDate::class.':Y-m-d',     // "2025-12-17"
    'SHCLDT' => IbmDate::class.':m/d/Y',     // "12/17/2025"
    'SHCLDT' => IbmDate::class.':us',        // "12/17/2025"
    'SHCLDT' => IbmDate::class.':eu',        // "17/12/2025"

    // Handles special values like 99999999 as null
    'PBTODT' => IbmDateNullable::class,
];
```

**Format shortcuts:**
- `:date` → `Y-m-d`
- `:us` → `m/d/Y`
- `:eu` → `d/m/Y`
- Or use any custom PHP date format string

**Carbon instance (no format):**

```php
$call->scheduled_date;                    // Carbon instance
$call->scheduled_date->format('m/d/Y');   // "12/17/2025"
$call->scheduled_date->diffForHumans();   // "2 days ago"

// Setting accepts Carbon, string, or integer
$call->scheduled_date = Carbon::now();
$call->scheduled_date = '2025-12-25';
$call->scheduled_date = 20251225;
```

### Duplicate Mapped Names

When multiple DB columns map to the same name, **first wins**:

```php
protected array $maps = [
    'RMCUST' => 'customer_number',  // This one wins
    'CXCUST' => 'customer_number',  // Skipped in output
];
```

## Automatic Filtering

By default, queries automatically filter by:
- `delete_code = 'A'` (active records only)
- `company_number = '1'` (default company)

These filters only apply if the model has `delete_code` and `company_number` mapped.

### Bypass Filters

```php
// Include inactive/deleted records
Item::withInactive()->get();

// Include all companies
Item::withAllCompanies()->get();

// Filter by a specific company
Item::forCompany('2')->get();

// Remove all automatic filters
Item::unfiltered()->get();

// Combine bypasses
Item::withInactive()->withAllCompanies()->get();
```

### Disable Per Model

```php
class SomeModel extends Model
{
    // Disable automatic filtering
    protected bool $filterActiveOnly = false;
    protected bool $filterByCompany = false;

    // Or change the default values
    protected string $activeDeleteCode = 'I';   // Different "active" code
    protected string $defaultCompany = '2';     // Different default company
}
```

## Multi-Column Relationships

IBM/DB2 tables often use composite keys. Use array syntax for multi-column relationships.

When both models have the same mapped column names, you only need to specify them once:

```php
class ItemBalance extends Model
{
    protected array $maps = [
        'IFCOMP' => 'company_number',
        'IFITEM' => 'item_number',
    ];

    public function item()
    {
        // If both models map to 'company_number' and 'item_number'
        return $this->belongsTo(Item::class, ['company_number', 'item_number']);

        // Single column works too
        // return $this->belongsTo(Item::class, 'item_number');

        // Or specify both sides explicitly
        // return $this->belongsTo(Item::class, ['company_number', 'item_number'], ['company_number', 'item_number']);

        // Raw DB columns still work
        // return $this->belongsTo(Item::class, ['IFCOMP', 'IFITEM'], ['ICCMP', 'ICITEM']);
    }
}

class Item extends Model
{
    public function balances()
    {
        return $this->hasMany(ItemBalance::class, ['company_number', 'item_number']);
    }

    public function primaryBalance()
    {
        return $this->hasOne(ItemBalance::class, ['company_number', 'item_number']);
    }
}
```

Supported relationship types:
- `belongsTo($related, $foreignKey)` - ownerKey defaults to same as foreignKey
- `hasMany($related, $foreignKey)` - localKey defaults to same as foreignKey
- `hasOne($related, $foreignKey)` - localKey defaults to same as foreignKey

Column names are automatically translated to DB columns using each model's `$maps`. The trait generates DB2-compatible SQL using `AND`/`OR` conditions instead of tuple `IN` syntax.

## Extensions (Joined Tables)

Define extension tables that join to the base table:

```php
class Item extends Model
{
    protected string $schema = 'R60FILES';
    protected $table = 'VINITEM';

    protected array $extensions = [
        'R60FSDTA.VINITEMX' => [
            'join' => [
                'XICMP'  => 'ICCMP',   // ext.XICMP = base.ICCMP
                'XIITEM' => 'ICITEM',
            ],
            'columns' => ['*'],        // Optional: specific columns
            'maps' => [                // Optional: extension column maps
                'XITYPE' => 'type',
                'XIZUPID' => 'zuper_id',
            ],
        ],
    ];
}
```

**Note:** Extension table names include the schema prefix (e.g., `R60FSDTA.VINITEMX`). When using `testing()` scope, extension schemas are automatically converted (R→T).

### Query with Extensions

```php
// Join all extensions
Item::withExtensions()->get();

// Join specific extension
Item::withExtension('R60FSDTA.VINITEMX')->get();

// Filter by extension record count
Item::whereHasExtension('R60FSDTA.VINITEMX')->get();           // has >= 1
Item::whereHasExtension('R60FSDTA.VINITEMX', '>', 1)->get();   // has > 1
Item::whereHasExtension('R60FSDTA.VINITEMX', '=', 0)->get();   // has none
Item::whereDoesntHaveExtension('R60FSDTA.VINITEMX')->get();    // shortcut for none

// Filter AND join in one call
Item::withWhereHasExtension('R60FSDTA.VINITEMX')->get();
Item::withWhereHasExtension('R60FSDTA.VINITEMX', '>', 1)->get();

// Combine with selectMapped - extension mapped columns auto-added
Item::selectMapped()->withExtensions()->get();
Item::withExtensions()->selectMapped()->get();
```

### Check Extension Records (Instance)

```php
$item = Item::find(123);
$item->hasExtensionRecords('R60FSDTA.VINITEMX');        // true/false
$item->countExtensionRecords('R60FSDTA.VINITEMX');      // 0, 1, 2...
$item->hasMultipleExtensionRecords('R60FSDTA.VINITEMX'); // true if > 1
```

### Load Extension Separately

```php
$item->loadExtension('R60FSDTA.VINITEMX');
$extData = $item->getExtensionData('R60FSDTA.VINITEMX');
```

## Query Logging

### Inline Logging (Recommended)

Enable logging for specific queries inline:

```php
// Log to stderr (default)
Item::logQuery()->where('item_number', 'ABC')->first();

// Log to file
Item::logQuery('default')->selectMapped()->get();

// Log to both stderr and file
Item::logQuery(['stderr', 'default'])->withExtensions()->first();
```

### Global Logging

Enable logging for all subsequent queries:

```php
use CodyJHeiser\Db2Eloquent\Model;

// Enable via base model or any child model
Model::enableQueryLog();
Customer::enableQueryLog();

// Log to app log file instead of stderr
Customer::enableQueryLog(null);
Customer::enableQueryLog('default');

// Log to multiple channels
Customer::enableQueryLog(['stderr', 'default']);

// Run queries...
Customer::where('customer_number', '123')->first();

// Manage logs
Customer::dumpQueryLog();        // Dump to output
Customer::getQueryLog();         // Get as array
Customer::clearQueryLog();       // Clear log
Customer::disableQueryLog();     // Turn off
```

**Log channels:**
- `'stderr'` (default) - outputs to console/terminal
- `null` or `'default'` - writes to Laravel's app log file
- `['stderr', 'default']` - logs to both

### Custom SQL Formatter

```php
Model::setSqlFormatter(function ($sql, $bindings) {
    // Your custom formatting logic
    return $formattedSql;
});
```

## Helper Methods

```php
// Column mapping
$model->getMaps();                 // Base table maps
$model->getAllMaps();              // Base + extension maps
$model->getReverseMaps();          // Mapped name => DB column
$model->getDbColumn('name');       // 'name' => 'RMNAME'
$model->getMappedColumn('RMNAME'); // 'RMNAME' => 'name'

// Check definitions
$model->hasMaps();
$model->hasExtensions();

// Raw attributes (unmapped)
$model->getRawAttributes();
```

## Configuration

### Connection

By default, models use a connection named `db2`. Configure this in your `config/database.php`:

```php
'connections' => [
    'db2' => [
        'driver' => 'db2',
        'host' => env('IBM_DB_HOST'),
        'port' => env('IBM_DB_PORT', 50000),
        'database' => env('IBM_DB_DATABASE'),
        'username' => env('IBM_DB_USERNAME'),
        'password' => env('IBM_DB_PASSWORD'),
        'schema' => env('IBM_DB_SCHEMA', 'QGPL'),
    ],
],
```

Override the connection in your model if needed:

```php
protected $connection = 'my_other_db2_connection';
```

### Schema Configuration

Models generated with `make:db2-model` pull their schema from config, allowing you to manage schemas centrally. Add this to your `config/database.php`:

```php
'ibm' => [
    'schema' => [
        'R60FILES' => env('IBM_SCHEMA_FILES', 'R60FILES'),
        'R60FSDTA' => env('IBM_SCHEMA_FSDTA', 'R60FSDTA'),
    ],
],
```

Generated models use the config with a fallback to the default:

```php
public function __construct(array $attributes = [])
{
    $this->schema = config('database.ibm.schema.R60FILES', 'R60FILES');

    parent::__construct($attributes);
}
```

This lets you switch schemas via environment variables without modifying model code.

### Default Company

Override the default company filter:

```php
protected string $defaultCompany = '2';
```

### Disable Features

```php
// Disable auto-filtering by delete code
protected bool $filterActiveOnly = false;

// Disable auto-filtering by company
protected bool $filterByCompany = false;

// Disable auto-select of mapped columns
protected bool $autoSelectMapped = false;

// Disable output mapping
protected bool $applyMapsOnOutput = false;
```

## Development

### Requirements

- PHP 8.4+
- Composer
- For integration tests: IBM i Access ODBC Driver + `pdo_odbc` PHP extension

### Setup

```bash
# Clone the repository
git clone https://github.com/CodyJHeiser/laravel-db2-eloquent.git
cd laravel-db2-eloquent

# Install dependencies
composer install

# Copy environment file for integration testing (optional)
cp .env.example .env
# Edit .env with your DB2 credentials
```

### Testing

The test suite has two types of tests:

**Unit Tests** - Run without a database (uses SQLite in-memory):
```bash
composer test
```

**Integration Tests** - Tests against a real DB2 connection:
```bash
# First configure .env with your DB2 credentials
composer test-integration
```

**All Tests**:
```bash
composer test-all
```

### Test Structure

| Directory | Purpose | Database Required |
|-----------|---------|-------------------|
| `tests/Unit/` | Pure logic tests (casts, mapping, traits) | No (uses SQLite mock) |
| `tests/Integration/` | DB2-specific tests | Yes (real DB2) |
| `tests/Feature/` | End-to-end tests | Varies |

Unit tests run on CI (GitHub Actions) without DB2. Integration tests are skipped unless DB2 credentials are configured.

### Environment Variables

For integration tests, create a `.env` file with:

```env
IBM_DB_HOST=your-ibm-host
IBM_DB_PORT=50000
IBM_DB_DATABASE=your-database
IBM_DB_USERNAME=your-username
IBM_DB_PASSWORD=your-password
IBM_DB_SCHEMA=QGPL
```

## License

MIT License. See [LICENSE](LICENSE) for details.
