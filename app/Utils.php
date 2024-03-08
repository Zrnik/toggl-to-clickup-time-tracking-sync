<?php

namespace UploadTool;

/**
 * @phpstan-import-type TogglCustomEntryFormatArrayType from TogglApi
 * @phpstan-import-type ClickUpEntryArrayType from ClickUpApi
 */
class Utils
{
    /**
     * @param ClickUpEntryArrayType $clickUpEntry
     * @param TogglCustomEntryFormatArrayType $togglEntry
     * @return bool
     */
    public static function clickUpEntryEqualsTogglEntry(array $clickUpEntry, array $togglEntry): bool
    {
        return (int)$clickUpEntry['start'] === (int)$togglEntry['start']
            && (int)$clickUpEntry['duration'] === (int)$togglEntry['duration'];
    }
}
