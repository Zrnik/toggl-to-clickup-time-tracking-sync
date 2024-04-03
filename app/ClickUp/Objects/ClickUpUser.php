<?php

declare(strict_types=1);

namespace UploadTool\ClickUp\Objects;

class ClickUpUser
{
    public function __construct(
        public int $id,
        public string $name,
    ) {
    }
}
