<?php

namespace CodyJHeiser\Db2Eloquent\Console\Commands;

use Illuminate\Console\Command;

class MakeDb2ModelCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:db2-model
                            {name : The name of the model class}
                            {table : The database table (e.g., R60FILES.MYTABLE or just MYTABLE)}
                            {--path= : Custom path relative to app/ (default: Models/IBM)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new DB2 model extending the base Model';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $name = $this->argument('name');
        $tableInput = strtoupper($this->argument('table'));
        $customPath = $this->option('path');

        // Parse schema.table format
        if (str_contains($tableInput, '.')) {
            [$schema, $table] = explode('.', $tableInput, 2);
        } else {
            $schema = 'R60FILES';
            $table = $tableInput;
        }

        // Determine relative path and namespace
        $basePath = $customPath ? trim($customPath, '/') : 'Models/IBM';
        $relativePath = "app/{$basePath}/{$name}.php";
        $absolutePath = base_path($relativePath);

        // Build namespace from path
        $namespace = 'App\\' . str_replace('/', '\\', $basePath);

        // Check if file already exists
        if (file_exists($absolutePath)) {
            $this->error("Model {$name} already exists at {$relativePath}");
            return self::FAILURE;
        }

        $stub = $this->getStub($name, $namespace, $table, $schema);

        // Ensure directory exists
        $directory = dirname($absolutePath);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents($absolutePath, $stub);

        $this->info("Model created: {$relativePath}");
        $this->newLine();
        $this->line("Next steps:");
        $this->line("  1. Add column mappings to the \$maps array");
        $this->line("  2. Add any necessary \$casts (use raw DB column names)");
        $this->line("  3. Define relationships if needed");

        return self::SUCCESS;
    }

    /**
     * Get the stub content for the model.
     */
    protected function getStub(string $name, string $namespace, string $table, string $schema): string
    {
        // Build the config key (e.g., R60FILES -> database.ibm.schema.R60FILES)
        $configKey = "database.ibm.schema.{$schema}";

        return <<<PHP
<?php

namespace {$namespace};

use CodyJHeiser\Db2Eloquent\Model;

/**
 * {$name} Model
 *
 * Table: {$schema}.{$table}
 */
class {$name} extends Model
{
    /**
     * The database schema for this model.
     */
    protected string \$schema = '{$schema}';

    /**
     * The database table used by the model.
     */
    protected \$table = '{$table}';

    /**
     * The attributes that should be cast.
     * Use raw DB column names for casts.
     */
    protected \$casts = [
        // 'COLUMN' => 'integer',
    ];

    /**
     * Column mappings from DB columns to human-readable names.
     */
    protected array \$maps = [
        // 'DBCOL' => 'human_readable_name',
    ];

    /**
     * Create a new model instance.
     */
    public function __construct(array \$attributes = [])
    {
        \$this->schema = config('{$configKey}', '{$schema}');

        parent::__construct(\$attributes);
    }
}

PHP;
    }
}
