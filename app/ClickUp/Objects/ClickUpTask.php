<?php

declare(strict_types=1);

namespace UploadTool\ClickUp\Objects;

use Brick\DateTime\LocalDateTime;
use UploadTool\ClickUp\ClickUpIdDetector;

class ClickUpTask
{
    public function __construct(
        public string $id,
        public string $name,
        public readonly ClickUpList $list,
        public LocalDateTime $created,
    ) {
    }

    public function getClickUpIdDetector(): ClickUpIdDetector
    {
        return ClickUpIdDetector::fromIds([$this->id]);
    }
}
