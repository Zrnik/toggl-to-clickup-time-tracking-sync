<?php

namespace UploadTool;

/**
 * @phpstan-import-type TogglCustomEntryFormatArrayType from TogglApi
 * @phpstan-import-type TogglCustomEntryFormatEnhancedWithClickUpIdArrayType from UploadCommand
 * @phpstan-import-type ClickUpEntryArrayType from ClickUpApi
 */
class Utils
{
    /**
     * @param ClickUpEntryArrayType $clickUpEntry
     * @param TogglCustomEntryFormatEnhancedWithClickUpIdArrayType $togglEntry
     * @return bool
     */
    public static function clickUpEntryEqualsTogglEntry(array $clickUpEntry, array $togglEntry): bool
    {
        return (int)$clickUpEntry['start'] === (int)$togglEntry['start']
            && (int)$clickUpEntry['duration'] === (int)$togglEntry['duration']
            && (string)$clickUpEntry['description'] === (string)$togglEntry['click_up_description'];
    }
}
