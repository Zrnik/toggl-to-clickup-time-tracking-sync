<?php

declare(strict_types=1);

namespace UploadTool\ClickUp;

use Brick\DateTime\LocalDateTime;
use Brick\DateTime\ZonedDateTime;
use DateTimeImmutable;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Nette\Utils\Json;
use Nette\Utils\JsonException;
use RuntimeException;
use Symfony\Component\Console\Output\OutputInterface;
use UploadTool\ClickUp\Objects\ClickUpFolder;
use UploadTool\ClickUp\Objects\ClickUpList;
use UploadTool\ClickUp\Objects\ClickUpSpace;
use UploadTool\ClickUp\Objects\ClickUpTask;
use UploadTool\ClickUp\Objects\ClickUpTeam;
use UploadTool\ClickUp\Objects\ClickUpTimeEntry;
use UploadTool\ClickUp\Objects\ClickUpUser;
use UploadTool\Command\SyncCommand;
use UploadTool\Toggl\Objects\TogglTimeEntry;

class ClickUpConnector
{
    private Client $client;

    public function __construct(
        private readonly ClickUpRateLimiter $rateLimiter,
        private readonly OutputInterface $output,
        private readonly string $apiKey,
    ) {
        $this->client = new Client([
            'headers' => $this->clientHeaders(),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function clientHeaders(): array
    {
        return [
            'Content-Type' => 'application/json',
            'Authorization' => $this->apiKey,
        ];
    }

    /**
     * @throws GuzzleException
     * @throws JsonException
     */
    public function getMe(): ClickUpUser
    {
        $this->output->writeln(SyncCommand::DASH_SEPARATOR);
        $this->output->writeln('Fetching current ClickUp user ...');

        $response = $this->client->request('GET', 'https://api.clickup.com/api/v2/user');
        $result = Json::decode($response->getBody()->getContents(), forceArrays: true);

        $this->output->writeln(SyncCommand::EQUALS_SEPARATOR);

        $clickUpUser = new ClickUpUser(
            $result['user']['id'],
            $result['user']['username'],
        );

        $this->output->writeln(sprintf('Authenticated as: %s', $clickUpUser->name));

        $this->output->writeln(SyncCommand::DASH_SEPARATOR);
        $this->output->writeln('');

        return $clickUpUser;
    }

    /**
     * @return ClickUpTeam[]
     * @throws JsonException
     * @throws GuzzleException
     */
    public function getTeams(): array
    {
        $this->output->writeln(SyncCommand::DASH_SEPARATOR);
        $this->output->writeln('Fetching ClickUp teams ...');

        $result = $this->rateLimiter->limitedRequest(
            fn () => $this->client->request('GET', 'https://api.clickup.com/api/v2/team')
        );

        if (! array_key_exists('teams', $result)) {
            throw new RuntimeException('No teams?');
        }

        $teams = [];

        $totalTasks = 0;

        foreach ($result['teams'] as $team) {
            $clickUpTeam = new ClickUpTeam(
                $team['id'],
                $team['name'],
            );

            $clickUpTeam->spaces = $this->getSpaces($clickUpTeam);

            $teams[] = $clickUpTeam;
            $totalTasks += $clickUpTeam->getTotalTasks();
        }

        $this->output->writeln(SyncCommand::EQUALS_SEPARATOR);
        $this->output->writeln(
            sprintf(
                'Fetched %d team(s) with the total of %d task(s)...',
                count($teams),
                $totalTasks,
            )
        );
        $this->output->writeln(SyncCommand::DASH_SEPARATOR);

        $this->output->writeln('');

        return $teams;
    }

    /**
     * @return ClickUpSpace[]
     * @throws JsonException
     * @throws GuzzleException
     */
    public function getSpaces(ClickUpTeam $clickUpTeam): array
    {
        $this->output->writeln(sprintf('[%s] Fetching spaces ...', $clickUpTeam->name));

        $result = $this->rateLimiter->limitedRequest(
            fn () => $this->client->request(
                'GET',
                sprintf('https://api.clickup.com/api/v2/team/%d/space', $clickUpTeam->id)
            )
        );

        if (! array_key_exists('spaces', $result)) {
            throw new RuntimeException('No teams?');
        }

        $spaces = [];

        foreach ($result['spaces'] as $space) {
            if ($space['archived']) {
                continue;
            }

            $clickUpSpace = new ClickUpSpace(
                $space['id'],
                $space['name'],
                $clickUpTeam
            );

            $clickUpSpace->lists = $this->getListsBySpace($clickUpSpace);

            $spaces[] = $clickUpSpace;
        }

        return $spaces;
    }

    /**
     * @param ClickUpSpace $clickUpSpace
     * @return ClickUpFolder[]
     * @throws JsonException
     * @throws GuzzleException
     */
    private function getFolders(ClickUpSpace $clickUpSpace): array
    {
        $result = $this->rateLimiter->limitedRequest(
            fn () => $this->client->request(
                'GET',
                sprintf(
                    'https://api.clickup.com/api/v2/space/%s/folder',
                    $clickUpSpace->id
                )
            )
        );

        if (! array_key_exists('folders', $result)) {
            throw new RuntimeException('No folders?');
        }

        if (count($result['folders']) > 0) {
            throw new RuntimeException('UnImplemented folders! Please implement!');
        }

        return [];
    }

    /**
     * @param ClickUpSpace $clickUpSpace
     * @return ClickUpList[]
     * @throws JsonException
     * @throws GuzzleException
     */
    private function getListsBySpace(ClickUpSpace $clickUpSpace): array
    {
        $this->output->writeln(
            sprintf(
                '[%s][%s] Fetching lists ...',
                $clickUpSpace->team->name,
                $clickUpSpace->name,
            )
        );

        $result = $this->rateLimiter->limitedRequest(
            fn () => $this->client->request(
                'GET',
                sprintf(
                    'https://api.clickup.com/api/v2/space/%s/list',
                    $clickUpSpace->id
                )
            )
        );

        if (! array_key_exists('lists', $result)) {
            throw new RuntimeException('No lists?');
        }

        $lists = [];

        foreach ($result['lists'] as $list) {
            $clickUpList = new ClickUpList(
                $list['id'],
                $list['name'],
                $clickUpSpace,
                null,
            );

            $clickUpList->tasks = $this->getTasksByList($clickUpList);

            $lists[] = $clickUpList;
        }

        // Add folder stuff...
        foreach ($this->getFolders($clickUpSpace) as $folder) {
            foreach ($this->getListsByFolder($folder) as $clickUpList) {
                $lists[] = $clickUpList;
            }
        }

        return $lists;
    }

    /**
     * @param ClickUpFolder $clickUpFolder
     * @return ClickUpList[]
     */
    private function getListsByFolder(ClickUpFolder $clickUpFolder): array
    {
        throw new RuntimeException('UnImplemented folders! Please implement!');
    }

    /**
     * @param ClickUpList $clickUpList
     * @return ClickUpTask[]
     * @throws GuzzleException
     * @throws JsonException
     */
    private function getTasksByList(ClickUpList $clickUpList): array
    {
        $query = [
            'include_closed' => 'true',
            'subtasks' => 'true',
            'page' => 0,
        ];

        $tasks = [];

        do {
            $this->output->writeln(
                sprintf(
                    '[%s][%s][%s] Fetching tasks on page %d ...',
                    $clickUpList->space->team->name,
                    $clickUpList->space->name,
                    $clickUpList->name,
                    $query['page'],
                )
            );

            $result = $this->rateLimiter->limitedRequest(
                fn () => $this->client->request(
                    'GET',
                    sprintf(
                        'https://api.clickup.com/api/v2/list/%d/task?%s',
                        $clickUpList->id,
                        http_build_query($query)
                    )
                )
            );

            if (! array_key_exists('tasks', $result)) {
                throw new RuntimeException('No tasks?');
            }

            foreach ($result['tasks'] as $task) {
                $clickUpTask = new ClickUpTask(
                    $task['id'],
                    $task['name'],
                    $clickUpList,
                    LocalDateTime::fromNativeDateTime(
                        (new DateTimeImmutable())->setTimestamp((int) ($task['date_created'] / 1000))
                    )
                );

                $tasks[] = $clickUpTask;
            }

            // Increase Page:
            ++$query['page'];
        } while ($result['last_page'] !== true);

        return $tasks;
    }

    /**
     * @return ClickUpTimeEntry[]
     * @throws JsonException
     * @throws GuzzleException
     */
    public function getTimeEntriesByTaskAndUser(ClickUpTask $clickUpTask, ClickUpUser $clickUpUser): array
    {
        $clickUpTimeEntries = [];

        $query = [
            'task_id' => $clickUpTask->id,
            'assignee' => $clickUpUser->id,
            'start_date' => 0,
            'end_date' => (new DateTimeImmutable())->format('Uv'),
        ];

        $result = $this->rateLimiter->limitedRequest(
            fn () => $this->client->request(
                'GET',
                sprintf(
                    'https://api.clickup.com/api/v2/team/%d/time_entries?%s',
                    $clickUpTask->list->space->team->id,
                    http_build_query($query),
                )
            )
        );

        if (! array_key_exists('data', $result)) {
            throw new RuntimeException('No data?');
        }

        foreach ($result['data'] as $timeEntry) {
            $clickUpTimeEntries[] = new ClickUpTimeEntry(
                $timeEntry['id'],
                $clickUpTask,
                ZonedDateTime::fromNativeDateTime(
                    (new DateTimeImmutable())->setTimestamp((int) ($timeEntry['start'] / 1000))
                ),
                (int) ($timeEntry['duration'] / 1000)
            );
        }

        return $clickUpTimeEntries;
    }

    /**
     * @throws GuzzleException
     * @throws JsonException
     */
    public function createTimeEntry(
        ClickUpTask $clickUpTask,
        ClickUpUser $clickUpUser,
        TogglTimeEntry $togglTimeEntry
    ): void {
        $this->rateLimiter->limitedRequest(
            fn () => $this->client->request(
                'POST',
                sprintf(
                    'https://api.clickup.com/api/v2/team/%s/time_entries',
                    $clickUpTask->list->space->team->id
                ),
                [
                    'headers' => $this->clientHeaders(),
                    'json' => [
                        'tid' => $clickUpTask->id,
                        'start' => $togglTimeEntry->start->toNativeDateTime()->getTimestamp() * 1000,
                        'duration' => $togglTimeEntry->duration * 1000,
                        // TODO: Check if `Business` with unlimited TimeTracking and then put description.
                        //  'description' => $togglEntryForThisTask['click_up_description'],
                        'assignee' => $clickUpUser->id,
                    ],
                ]
            )
        );
    }

    /**
     * @throws GuzzleException
     * @throws JsonException
     */
    public function deleteTimeEntry(ClickUpTimeEntry $clickUpTimeEntry): void
    {
        try {
            $this->rateLimiter->limitedRequest(
                fn () => $this->client->request(
                    'DELETE',
                    sprintf(
                        'https://api.clickup.com/api/v2/team/%s/time_entries/%s',
                        $clickUpTimeEntry->task->list->space->team->id,
                        $clickUpTimeEntry->id
                    ),
                )
            );
        } catch (GuzzleException $guzzleException) {
            if (
                $guzzleException instanceof RequestException
                && $guzzleException->getResponse()?->getStatusCode() === 404
            ) {
                return; // It's OK, we want to delete it, and it does not exist anymore.
            }

            throw $guzzleException;
        }
    }
}
