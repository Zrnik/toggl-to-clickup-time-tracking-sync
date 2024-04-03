<?php

declare(strict_types=1);

namespace UploadTool\ClickUp\Objects;

use UploadTool\ClickUp\ClickUpIdDetector;

class ClickUpTeam
{
    /**
     * @var ClickUpSpace[]
     */
    public array $spaces = [];

    public function __construct(
        public readonly string $id,
        public readonly string $name,
    ) {
    }

    private ?ClickUpIdDetector $clickUpIdDetector = null;

    public function getIdDetector(): ClickUpIdDetector
    {
        if ($this->clickUpIdDetector === null) {
            $this->clickUpIdDetector = ClickUpIdDetector::fromIds($this->getAllTaskIds());
        }

        return $this->clickUpIdDetector;
    }

    public function getTotalTasks(): int
    {
        return count($this->getAllTaskIds());
    }

    /**
     * @return string[]
     */
    private function getAllTaskIds(): array
    {
        $taskIds = [];

        foreach ($this->spaces as $space) {
            foreach ($space->lists as $list) {
                foreach ($list->tasks as $task) {
                    $taskIds[] = $task->id;
                }
            }
        }

        return array_unique($taskIds);
    }

    /**
     * @return ClickUpTask[]
     */
    public function getAllTasks(): array
    {
        $tasks = [];

        foreach ($this->spaces as $space) {
            foreach ($space->lists as $list) {
                foreach ($list->tasks as $task) {
                    $tasks[$task->id] = $task;
                }
            }
        }

        return $tasks;
    }

    public function getCreationYear(): int
    {
        $creationYear = (int) date('Y');

        foreach ($this->spaces as $space) {
            foreach ($space->lists as $list) {
                foreach ($list->tasks as $task) {
                    $creationYear = min($creationYear, $task->created->getYear());
                }
            }
        }

        return $creationYear;
    }
}
