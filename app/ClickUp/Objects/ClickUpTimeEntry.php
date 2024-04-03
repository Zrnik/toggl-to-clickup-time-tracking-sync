<?php

declare(strict_types=1);

namespace UploadTool\ClickUp\Objects;

use Brick\DateTime\ZonedDateTime;

class ClickUpTimeEntry
{
    public function __construct(
        public string $id,
        public ClickUpTask $task,
        public ZonedDateTime $start,
        public int $duration,
    ) {
    }

    public function equals(ClickUpTimeEntry $clickUpTimeEntry): bool
    {
        return $clickUpTimeEntry->start->isEqualTo($this->start)
            && $this->duration === $clickUpTimeEntry->duration;
    }
}
