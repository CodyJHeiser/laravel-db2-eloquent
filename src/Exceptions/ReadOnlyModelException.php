<?php

namespace CodyJHeiser\Db2Eloquent\Exceptions;

use RuntimeException;

class ReadOnlyModelException extends RuntimeException
{
    public static function forModel(string $model): static
    {
        return new static("Model [{$model}] is read-only. Write operations are not permitted on union source models.");
    }
}
