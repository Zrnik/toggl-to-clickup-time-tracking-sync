<?php

namespace UploadTool;

class Utils
{

    public static function clickUpEntryEqualsTogglEntry(array $clickUpEntry, array $togglEntry): bool
    {
        return (int)$clickUpEntry['start'] === (int)$togglEntry['start']
            && (int)$clickUpEntry['duration'] === (int)$togglEntry['duration'];
    }
}
