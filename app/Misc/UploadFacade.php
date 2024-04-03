<?php

declare(strict_types=1);

namespace UploadTool\Misc;

use GuzzleHttp\Exception\GuzzleException;
use Nette\Utils\JsonException;
use Symfony\Component\Console\Output\OutputInterface;
use UploadTool\ClickUp\ClickUpConnector;
use UploadTool\ClickUp\Objects\ClickUpTask;
use UploadTool\ClickUp\Objects\ClickUpUser;
use UploadTool\Command\SyncCommand;
use UploadTool\Toggl\Objects\TogglTimeEntry;
use UploadTool\Toggl\TogglConnector;

class UploadFacade
{
    public const int LIMIT_UPLOAD_DAYS = 365;

    public function __construct(
        private readonly OutputInterface $output,
        private readonly TogglConnector $togglConnector,
        private readonly ClickUpConnector $clickUpConnector,
    ) {
    }

    /**
     * @throws GuzzleException
     * @throws JsonException
     */
    public function run(): void
    {
        $this->output->writeln('---------------------------');
        $this->output->writeln('-- Toggl to ClickUp Sync --');
        $this->output->writeln('---------------------------');
        $this->output->writeln('');

        $togglWorkspaces = $this->togglConnector->getWorkspaces();
        $clickUpTeams = $this->clickUpConnector->getTeams();

        $startingYear = (int) date('Y');
        foreach ($clickUpTeams as $clickUpTeam) {
            $startingYear = min($startingYear, $clickUpTeam->getCreationYear());
        }

        $clickUpUser = $this->clickUpConnector->getMe();

        $togglTimeEntries = [];
        foreach ($togglWorkspaces as $togglWorkspace) {
            $togglTimeEntries += $this->togglConnector->getWorkspaceTimeEntries(
                $startingYear - 1,
                $togglWorkspace,
            );
        }

        $this->output->writeln(SyncCommand::DASH_SEPARATOR);
        $this->output->writeln('Synchronizing tasks ...');
        $this->output->writeln(SyncCommand::DASH_SEPARATOR);
        $syncResult = new SyncResult();

        foreach ($clickUpTeams as $clickUpTeam) {
            foreach ($clickUpTeam->getAllTasks() as $clickUpTask) {
                $taskClickUpIdDetector = $clickUpTask->getClickUpIdDetector();
                $syncResult->merge(
                    $this->syncTogglTimeEntriesToClickUpTaskByClickUpUser(
                        array_filter(
                            $togglTimeEntries,
                            static fn (TogglTimeEntry $togglTimeEntry) => $taskClickUpIdDetector->find($togglTimeEntry->name) !== null
                        ),
                        $clickUpTask,
                        $clickUpUser,
                    )
                );
            }
        }

        $this->output->writeln('');

        $this->output->writeln(SyncCommand::DASH_SEPARATOR);

        foreach ($syncResult->created as $togglTimeEntry) {
            $this->output->writeln(
                sprintf(
                    'Created: %s',
                    $togglTimeEntry->name,
                )
            );
        }

        foreach ($syncResult->deleted as $clickUpTimeEntry) {
            $this->output->writeln(
                sprintf(
                    'Deleted: %s #%s',
                    $clickUpTimeEntry->task->name,
                    $clickUpTimeEntry->task->id
                )
            );
        }

        $this->output->writeln(SyncCommand::EQUALS_SEPARATOR);

        $this->output->writeln(
            sprintf(
                'Created %d time entries, deleted %d time entries and %d duplicates.',
                count($syncResult->created),
                count($syncResult->deleted),
                $syncResult->duplicates,
            )
        );

        $this->output->writeln(
            sprintf(
                '%d time entries were up to date.',
                $syncResult->upToDate,
            )
        );

        $this->output->writeln(SyncCommand::EQUALS_SEPARATOR);
        $this->output->writeln('');
    }

    /**
     * @param TogglTimeEntry[] $togglTimeEntries
     * @throws GuzzleException
     * @throws JsonException
     */
    private function syncTogglTimeEntriesToClickUpTaskByClickUpUser(
        array $togglTimeEntries,
        ClickUpTask $clickUpTask,
        ClickUpUser $clickUpUser,
    ): SyncResult {
        $syncResult = new SyncResult();

        $timeEntriesOnTask = $this->clickUpConnector->getTimeEntriesByTaskAndUser($clickUpTask, $clickUpUser);

        $actionsDone = 0;

        // 0. Remove Duplicate ClickUp Tasks.

        $duplicationBuffer = [];

        foreach ($timeEntriesOnTask as $clickUpTimeEntry) {
            $clickUpTimeEntryDuplicationHash = sprintf(
                '%s+%d',
                $clickUpTimeEntry->start->toNativeDateTime()->getTimestamp(),
                $clickUpTimeEntry->duration,
            );

            if (in_array($clickUpTimeEntryDuplicationHash, $duplicationBuffer, true)) {
                //Duplicate! Remove!
                $this->output->write('d');
                $actionsDone++;
                $this->clickUpConnector->deleteTimeEntry($clickUpTimeEntry);
                $syncResult->duplicates++;
            }

            $duplicationBuffer[] = $clickUpTimeEntryDuplicationHash;
        }

        // 1. Check Toggl Entries, Create ClickUp when missing.

        foreach ($togglTimeEntries as $togglTimeEntry) {
            $paired = false;

            foreach ($timeEntriesOnTask as $clickUpTimeEntry) {
                if ($togglTimeEntry->equals($clickUpTimeEntry)) {
                    $paired = true;
                    break;
                }
            }

            if (! $paired) {
                // No ClickUp entry found, create!
                $this->output->write('c');
                $actionsDone++;

                $this->clickUpConnector->createTimeEntry(
                    $clickUpTask,
                    $clickUpUser,
                    $togglTimeEntry,
                );

                $syncResult->created[] = $togglTimeEntry;
            }
        }

        // 2. Check ClickUp Entries, Delete when not found in toggl.

        foreach ($timeEntriesOnTask as $clickUpTimeEntry) {
            $paired = false;
            foreach ($togglTimeEntries as $togglTimeEntry) {
                if ($togglTimeEntry->equals($clickUpTimeEntry)) {
                    $paired = true;
                    break;
                }
            }

            if (! $paired) {
                // not paired with any toggl entries, deleting!
                $this->output->write('r');
                $actionsDone++;

                $this->clickUpConnector->deleteTimeEntry($clickUpTimeEntry);

                $syncResult->deleted[] = $clickUpTimeEntry;
            }
        }

        $upToDate = max(0, count($togglTimeEntries) - $actionsDone);
        $syncResult->upToDate += $upToDate;
        $this->output->write(str_repeat('.', $upToDate));

        return $syncResult;
    }
}
