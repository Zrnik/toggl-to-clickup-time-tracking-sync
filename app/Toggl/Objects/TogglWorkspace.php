<?php

declare(strict_types=1);

namespace UploadTool\Toggl\Objects;

class TogglWorkspace
{
    public function __construct(
        public string $id,
        public string $name,
    ) {
    }
}
