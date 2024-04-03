<?php

declare(strict_types=1);

namespace UploadTool\Toggl\Objects;

use Brick\DateTime\ZonedDateTime;
use UploadTool\ClickUp\Objects\ClickUpTimeEntry;

class TogglTimeEntry
{
    public function __construct(
        public string $id,
        public string $name,
        public ZonedDateTime $start,
        public int $duration,
    ) {
    }

    public function equals(ClickUpTimeEntry $clickUpTimeEntry): bool
    {
        return $clickUpTimeEntry->start->isEqualTo($this->start)
            && $clickUpTimeEntry->duration === $this->duration;
    }
}
