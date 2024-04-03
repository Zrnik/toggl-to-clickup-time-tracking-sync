<?php

declare(strict_types=1);

namespace UploadTool\Misc;

use UploadTool\ClickUp\Objects\ClickUpTimeEntry;
use UploadTool\Toggl\Objects\TogglTimeEntry;

class SyncResult
{
    /**
     * @var TogglTimeEntry[]
     */
    public array $created = [];

    /**
     * @var ClickUpTimeEntry[]
     */
    public array $deleted = [];

    public int $upToDate = 0;

    public int $duplicates = 0;

    public function merge(SyncResult $syncResult): SyncResult
    {
        foreach ($syncResult->deleted as $deleted) {
            $this->deleted[] = $deleted;
        }

        foreach ($syncResult->created as $created) {
            $this->created[] = $created;
        }

        $this->upToDate += $syncResult->upToDate;
        $this->duplicates += $syncResult->duplicates;

        return $this;
    }
}
