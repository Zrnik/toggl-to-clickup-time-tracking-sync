<?php

namespace UploadTool;

use AJT\Toggl\TogglClient;
use Exception;
use Nette\Utils\JsonException;
use Psr\Http\Client\ClientExceptionInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class UploadCommand extends Command
{
    private ClickUpApi $clickUpApi;
    private TogglApi $togglApi;

    /**
     * @throws Exception
     */
    public function __construct()
    {
        parent::__construct("tool:upload");
        $apiKeyProvider = new ApiKeyProvider(__DIR__ . '/../config.neon');
        $this->clickUpApi = new ClickUpApi($apiKeyProvider->getClickUpApiKey());
        $this->togglApi = new TogglApi($apiKeyProvider->getTogglApiKey());
    }

    /**
     * @throws JsonException
     * @throws ClientExceptionInterface
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        ClickUpIdDetector::test();

        $clickUpTaskIdsByTeamIds = $this->clickUpApi->getAllClickUpTaskIds();

        $clickUpTaskIds = [];
        foreach ($clickUpTaskIdsByTeamIds as $taskIds) {
            foreach ($taskIds as $taskId) {
                $clickUpTaskIds[] = $taskId;
            }
        }

        $togglTimeEntries = $this->togglApi->getAllTimeEntriesForLast40Days();
        $clickUpIdDetector = new ClickUpIdDetector($clickUpTaskIds);
        $togglTimeEntries = $this->filterTimeEntriesWithoutClickUpId($clickUpIdDetector, $togglTimeEntries);

        $currentClickUpUserId = $this->clickUpApi->getCurrentAuthenticatedUserId();

        $this->clickUpApi->handleUploadOfTimeEntries(
            $currentClickUpUserId,
            $clickUpTaskIdsByTeamIds,
            $togglTimeEntries
        );

        return 0;
    }

    private function filterTimeEntriesWithoutClickUpId(
        ClickUpIdDetector $clickUpIdDetector,
        array             $togglTimeEntries
    ): array
    {
        $filteredEntries = [];

        foreach ($togglTimeEntries as $togglTimeEntry) {
            $detectedId = $clickUpIdDetector->find($togglTimeEntry['name']);

            if ($detectedId !== null) {
                $togglTimeEntry['click_up_id'] = $detectedId;
                $filteredEntries[] = $togglTimeEntry;
            }
        }

        return $filteredEntries;
    }
}
