<?php

namespace App\Services\Storage;

final class HeadResult
{
    public function __construct(
        public int $size,
        public ?string $contentType,
        public ?string $etag = null,
    ) {}
}
