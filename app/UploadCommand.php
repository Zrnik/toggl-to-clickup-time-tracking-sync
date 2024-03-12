<?php

namespace UploadTool;

use Exception;
use Nette\Utils\JsonException;
use Psr\Http\Client\ClientExceptionInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @phpstan-import-type TogglCustomEntryFormatArrayType from TogglApi
 * @phpstan-type TogglCustomEntryFormatEnhancedWithClickUpIdArrayType array{
 *     id: int, name: string, start: int, end: int, duration: int, click_up_id: string, click_up_description: string,
 * }
 */
class UploadCommand extends Command
{
    private ClickUpApi $clickUpApi;
    private TogglApi $togglApi;

    public static ?OutputInterface $output = null;

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
        self::$output = $output;

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

    /**
     * @param ClickUpIdDetector $clickUpIdDetector
     * @param array $togglTimeEntries
     * @phpstan-param TogglCustomEntryFormatArrayType[] $togglTimeEntries
     * @return TogglCustomEntryFormatEnhancedWithClickUpIdArrayType[]
     */
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

                $togglTimeEntry['click_up_description'] = trim(
                    str_replace('#' . $togglTimeEntry['click_up_id'], '', $togglTimeEntry['name'])
                );

                $filteredEntries[] = $togglTimeEntry;
            }
        }

        return $filteredEntries;
    }
}
