<?php

declare(strict_types=1);

namespace UploadTool\Toggl;

use Brick\DateTime\LocalDate;
use Brick\DateTime\TimeZone;
use Brick\DateTime\ZonedDateTime;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Nette\Utils\Json;
use Nette\Utils\JsonException;
use RuntimeException;
use Symfony\Component\Console\Output\OutputInterface;
use UploadTool\Command\SyncCommand;
use UploadTool\Toggl\Objects\TogglTimeEntry;
use UploadTool\Toggl\Objects\TogglWorkspace;

class TogglConnector
{
    private Client $client;

    public function __construct(
        private readonly OutputInterface $output,
        string $apiKey,
    ) {
        $this->client = new Client([
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => sprintf(
                    'Basic %s',
                    base64_encode(sprintf('%s:api_token', $apiKey))
                ),
            ],
        ]);
    }

    /**
     * @return TogglWorkspace[]
     * @throws GuzzleException
     * @throws JsonException
     */
    public function getWorkspaces(): array
    {
        // Getting `me` with `?with_related_data=true` will return a list of workspaces...
        $response = $this->client->request(
            'GET',
            'https://api.track.toggl.com/api/v9/me?with_related_data=true'
        );

        $result = Json::decode($response->getBody()->getContents(), forceArrays: true);

        if (! array_key_exists('workspaces', $result)) {
            throw new RuntimeException('No workspaces?');
        }

        $workspaces = [];

        foreach ($result['workspaces'] as $workspace) {
            $workspaces[] = new TogglWorkspace(
                (string) $workspace['id'],
                $workspace['name'],
            );
        }

        return $workspaces;
    }

    /**
     * @return TogglTimeEntry[]
     * @throws GuzzleException
     * @throws JsonException
     */
    public function getWorkspaceTimeEntries(
        int $startingYear,
        TogglWorkspace $togglWorkspace,
    ): array {
        $timeEntries = [];

        $this->output->writeln(SyncCommand::DASH_SEPARATOR);
        $this->output->writeln(sprintf('Fetching all toggl entries for "%s" workspace ...', $togglWorkspace->name));
        $this->output->writeln(SyncCommand::DASH_SEPARATOR);

        $now = LocalDate::now(TimeZone::parse(date_default_timezone_get()));

        // "Maximum allowed date range is 366 days"
        // "start_date should be within 2006-01-01 to 2030-01-01"
        for (
            $localDateStart = LocalDate::of(max(2006, $startingYear), 01, 01);
            $localDateStart->isBefore($now);
            $localDateStart = $localDateStart->plusYears(1)
        ) {
            $localDateEnd = $localDateStart->withMonth(12)->withDay(31);

            $this->output->write('.');

            $firstId = null;
            $deadLockProtection = 50;

            do {
                $response = $this->client->request(
                    'POST',
                    sprintf(
                        'https://api.track.toggl.com/reports/api/v3/workspace/%s/search/time_entries',
                        $togglWorkspace->id,
                    ),
                    [
                        'json' => [
                            // start_date should be within 2006-01-01 to 2030-01-01
                            'start_date' => $localDateStart->toNativeDateTime()->format('Y-m-d'),
                            'end_date' => $localDateEnd->toNativeDateTime()->format('Y-m-d'),
                            'first_id' => $firstId,
                        ],
                    ]
                );

                $firstId = $response->getHeader('X-Next-Id')[0] ?? null;

                if ($firstId !== null) {
                    $firstId = (int) $firstId;
                }

                $result = Json::decode((string) $response->getBody(), forceArrays: true);

                foreach ($result as $timeEntryDescription) {
                    foreach ($timeEntryDescription['time_entries'] as $timeEntry) {
                        $timeEntries[] = new TogglTimeEntry(
                            (string) $timeEntry['id'],
                            $timeEntryDescription['description'],
                            ZonedDateTime::parse($timeEntry['start']),
                            $timeEntry['seconds'],
                        );
                    }
                }
            } while ($firstId !== null && --$deadLockProtection > 0);
        }

        $this->output->writeln('.');

        $this->output->writeln(SyncCommand::EQUALS_SEPARATOR);

        $this->output->writeln(sprintf('Fetched %d Toggl time entries.', count($timeEntries)));

        $this->output->writeln(SyncCommand::DASH_SEPARATOR);
        $this->output->writeln('');

        return $timeEntries;
    }
}
