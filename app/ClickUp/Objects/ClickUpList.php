<?php

declare(strict_types=1);

namespace UploadTool\ClickUp\Objects;

class ClickUpList
{
    /** @var ClickUpTask[] */
    public array $tasks = [];

    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly ClickUpSpace $space,
        public readonly ?ClickUpFolder $folder,
    ) {
    }
}
