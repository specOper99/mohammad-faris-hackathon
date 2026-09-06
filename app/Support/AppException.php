<?php

namespace App\Support;

use RuntimeException;

final class AppException extends RuntimeException
{
    /**
     * @param  array<string, list<string>>  $errors
     */
    public function __construct(
        public string $errorCode,
        public int $status = 400,
        ?string $message = null,
        public array $errors = [],
    ) {
        parent::__construct($message ?? $errorCode, $status);
    }

    /**
     * @param  array<string, list<string>>  $errors
     */
    public static function code(string $errorCode, int $status = 400, array $errors = [], ?string $message = null): self
    {
        return new self(
            $errorCode,
            $status,
            $message ?? trans('messages.'.$errorCode, [], app()->getLocale()),
            $errors,
        );
    }
}
