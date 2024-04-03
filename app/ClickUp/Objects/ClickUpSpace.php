<?php

declare(strict_types=1);

namespace UploadTool\ClickUp\Objects;

class ClickUpSpace
{
    /** @var ClickUpList[] */
    public array $lists = [];

    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly ClickUpTeam $team,
    ) {
    }
}
