<?php

namespace CodyJHeiser\Db2Eloquent\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Trait for query logging functionality.
 * Provides formatted SQL logging to stderr or log files.
 */
trait HasQueryLogging
{
    /**
     * Whether query logging is enabled.
     */
    protected static bool $logQueries = false;

    /**
     * Query log storage.
     */
    protected static array $queryLog = [];

    /**
     * Log channels for query logging.
     */
    protected static array $logChannels = ['stderr'];

    /**
     * Whether the query listener has been registered.
     */
    protected static bool $listenerRegistered = false;

    /**
     * The SQL formatter callback.
     * Set this via setSqlFormatter() to customize SQL formatting.
     */
    protected static $sqlFormatter = null;

    /**
     * Set the SQL formatter callback.
     * The callback receives (string $sql, array $bindings) and should return the formatted SQL string.
     * Pass null to reset to the default formatter.
     *
     * @param callable|null $formatter
     */
    public static function setSqlFormatter(?callable $formatter): void
    {
        static::$sqlFormatter = $formatter;
    }

    /**
     * Format SQL with bindings using the configured formatter or default implementation.
     */
    protected static function formatSqlWithBindings(string $sql, array $bindings): string
    {
        if (static::$sqlFormatter !== null) {
            return call_user_func(static::$sqlFormatter, $sql, $bindings);
        }

        // Default: simple binding replacement
        $formattedSql = $sql;
        foreach ($bindings as $binding) {
            $value = is_numeric($binding) ? $binding : "'" . addslashes($binding) . "'";
            $formattedSql = preg_replace('/\?/', $value, $formattedSql, 1);
        }

        return $formattedSql;
    }

    /**
     * Initialize query logging listener.
     * Should be called from the model's booted() method.
     */
    protected static function bootHasQueryLogging(): void
    {
        // Set up query listener for logging (only once across all IBM models)
        if (!self::$listenerRegistered) {
            self::$listenerRegistered = true;

            // Get the connection name from the model
            $model = new static;
            $connectionName = $model->getConnectionName();

            DB::connection($connectionName)->listen(function ($query) {
                if (self::$logQueries) {
                    $formattedSql = static::formatSqlWithBindings($query->sql, $query->bindings);

                    $log = [
                        'sql' => $formattedSql,
                        'bindings' => $query->bindings,
                        'time_ms' => $query->time,
                    ];

                    self::$queryLog[] = $log;

                    foreach (self::$logChannels as $channel) {
                        if ($channel === 'stderr') {
                            fwrite(STDERR, "\n" . str_repeat('─', 80) . "\n");
                            fwrite(STDERR, "SQL ({$query->time}ms):\n");
                            fwrite(STDERR, $formattedSql . "\n");
                            fwrite(STDERR, str_repeat('─', 80) . "\n");
                        } else {
                            Log::debug("SQL ({$query->time}ms): {$formattedSql}");
                        }
                    }
                }
            });
        }
    }

    /**
     * Enable query logging.
     *
     * @param string|array|null $channels 'stderr', 'default', null, or array of channels
     *                                    e.g. ['stderr', 'default'] to log to both
     */
    public static function enableQueryLog(string|array|null $channels = 'stderr')
    {
        static::$logQueries = true;
        static::$queryLog = [];

        if ($channels === null) {
            static::$logChannels = ['default'];
        } elseif (is_array($channels)) {
            static::$logChannels = $channels;
        } else {
            static::$logChannels = [$channels];
        }

        return 'Logging to: ' . implode(', ', static::$logChannels);
    }

    /**
     * Disable query logging.
     */
    public static function disableQueryLog(): void
    {
        static::$logQueries = false;
    }

    /**
     * Scope to enable query logging inline with the query.
     *
     * Usage: Item::logQuery()->where(...)->first()
     *        Item::logQuery('default')->where(...)->get()
     *        Item::logQuery(['stderr', 'default'])->where(...)->get()
     */
    #[Scope]
    protected function logQuery(Builder $query, string|array|null $channels = 'stderr'): void
    {
        static::enableQueryLog($channels);
    }

    /**
     * Get the query log.
     */
    public static function getQueryLog(): array
    {
        return static::$queryLog;
    }

    /**
     * Clear the query log.
     */
    public static function clearQueryLog(): void
    {
        static::$queryLog = [];
    }

    /**
     * Dump the query log to output.
     */
    public static function dumpQueryLog(): void
    {
        dump(static::$queryLog);
    }
}
