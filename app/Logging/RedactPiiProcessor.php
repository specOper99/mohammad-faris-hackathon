<?php

namespace App\Logging;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

final class RedactPiiProcessor implements ProcessorInterface
{
    public function __invoke(LogRecord $record): LogRecord
    {
        $context = $record->context;
        foreach (['password', 'token', 'authorization', 'cookie', 'xsrf', 'rawToken'] as $key) {
            foreach ($context as $k => $v) {
                if (stripos((string) $k, $key) !== false) {
                    $context[$k] = '[redacted]';
                }
            }
        }

        return $record->with(context: $context);
    }
}
