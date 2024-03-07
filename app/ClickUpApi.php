<?php

namespace UploadTool;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use Nette\Utils\Json;
use Nette\Utils\JsonException;
use Psr\Http\Client\ClientExceptionInterface;
use RuntimeException;

class ClickUpApi
{
    private Client $client;

    public function __construct(private readonly string $apiKey)
    {
        $this->client = new Client();

    }

    private function authorizedRequest(string $method, string $url, ?string $body = null): Request
    {
        return new Request(
            $method,
            $url,
            [
                'Content-Type' => 'application/json',
                'Authorization' => $this->apiKey,
            ],
            $body
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws JsonException
     */
    public function getCurrentAuthenticatedUserId(): int
    {
        $request = $this->authorizedRequest('GET', 'https://api.clickup.com/api/v2/user');
        $response = $this->client->sendRequest($request);
        $result = Json::decode($response->getBody()->getContents(), forceArrays: true);
        return $result['user']['id'] ?? throw new RuntimeException('no user id found in clickup!');
    }

    /**
     * @return array<int, string[]>
     * @throws ClientExceptionInterface
     * @throws JsonException
     */
    public function getAllClickUpTaskIds(): array
    {
        $request = $this->authorizedRequest('GET', 'https://api.clickup.com/api/v2/team');
        $response = $this->client->sendRequest($request);
        $result = Json::decode($response->getBody()->getContents(), forceArrays: true);

        $teamIds = [];
        foreach ($result['teams'] as $team) {
            $teamIds[] = $team['id'];
        }

        $spaceIds = [];
        foreach ($teamIds as $teamId) {
            $request = $this->authorizedRequest(
                'GET',
                sprintf('https://api.clickup.com/api/v2/team/%d/space', $teamId)
            );
            $response = $this->client->sendRequest($request);
            $result = Json::decode($response->getBody()->getContents(), forceArrays: true);

            foreach ($result['spaces'] as $space) {
                $spaceIds[] = $space['id'];
            }
        }

        $folderIds = [];
        $listIds = [];
        foreach ($spaceIds as $spaceId) {
            $request = $this->authorizedRequest(
                'GET',
                sprintf('https://api.clickup.com/api/v2/space/%d/folder', $spaceId)
            );
            $response = $this->client->sendRequest($request);
            $result = Json::decode($response->getBody()->getContents(), forceArrays: true);

            foreach ($result['folders'] as $folder) {
                $folderIds[] = $folder['id'];
            }

            $request = $this->authorizedRequest(
                'GET',
                sprintf('https://api.clickup.com/api/v2/space/%d/list', $spaceId)
            );
            $response = $this->client->sendRequest($request);
            $result = Json::decode($response->getBody()->getContents(), forceArrays: true);

            foreach ($result['lists'] as $list) {
                $listIds[] = $list['id'];
            }
        }

        foreach ($folderIds as $folderId) {
            $request = $this->authorizedRequest(
                'GET',
                sprintf('https://api.clickup.com/api/v2/folder/%d/list', $folderId)
            );
            $response = $this->client->sendRequest($request);
            $result = Json::decode($response->getBody()->getContents(), forceArrays: true);

            foreach ($result['lists'] as $list) {
                $listIds[] = $list['id'];
            }
        }

        $taskIdsByTeamIds = [];

        foreach ($listIds as $listId) {

            do {
                $request = $this->authorizedRequest(
                    'GET',
                    sprintf('https://api.clickup.com/api/v2/list/%d/task?include_closed=true', $listId)
                );
                $response = $this->client->sendRequest($request);

                $result = Json::decode($response->getBody()->getContents(), forceArrays: true);

                foreach ($result['tasks'] as $task) {
                    $teamId = (int)$task['team_id'];

                    if (($taskIdsByTeamIds[$teamId] ?? null) === null) {
                        $taskIdsByTeamIds[$teamId] = [];
                    }

                    $taskIdsByTeamIds[$teamId][] = (string)$task['id'];
                }
            } while ($result['last_page'] !== true);
        }

        return $taskIdsByTeamIds;
    }

    /**
     * @param array<int, string[]> $clickUpTaskIdsByTeamIds
     * @param array $togglTimeEntries
     * @return void
     * @throws ClientExceptionInterface
     * @throws JsonException
     */
    public function handleUploadOfTimeEntries(
        int   $currentClickUpUserId,
        array $clickUpTaskIdsByTeamIds,
        array $togglTimeEntries,
    ): void
    {

        foreach ($clickUpTaskIdsByTeamIds as $teamId => $taskIds) {
            foreach ($taskIds as $taskId) {

                $request = $this->authorizedRequest(
                    'GET',
                    sprintf('https://api.clickup.com/api/v2/team/%d/time_entries?task_id=%s', $teamId, $taskId)
                );

                $response = $this->client->sendRequest($request);

                $result = Json::decode($response->getBody()->getContents(), forceArrays: true);

                $togglEntriesForThisTask = $this->filterTogglEntriesByTaskId($togglTimeEntries, $taskId);

                // =====================================================================================================
                // =====================================================================================================
                // =====================================================================================================

                foreach ($togglEntriesForThisTask as $togglEntryForThisTask) {

                    $foundEntryOnClickUp = null;
                    foreach ($result['data'] as $clickUpEntry) {

                        if ($clickUpEntry['user']['id'] !== $currentClickUpUserId) {
                            continue;
                        }

                        if (Utils::clickUpEntryEqualsTogglEntry($clickUpEntry, $togglEntryForThisTask)) {
                            if ($foundEntryOnClickUp === null) {
                                $foundEntryOnClickUp = $clickUpEntry;
                            } else {
                                dump('[' . $taskId . '] Deleting Entry');
                                $this->deleteClickUpTimeEntry($teamId, $clickUpEntry['id']);
                            }
                        }
                    }

                    if ($foundEntryOnClickUp === null) {
                        dump('[' . $taskId . '] Creating Entry');
                        $this->createClickUpTimeEntry($teamId, $taskId, $currentClickUpUserId, $togglEntryForThisTask);
                    }
                }

                // =====================================================================================================
                // =====================================================================================================
                // =====================================================================================================

                foreach ($result['data'] as $clickUpEntry) {

                    if ($clickUpEntry['user']['id'] !== $currentClickUpUserId) {
                        continue;
                    }

                    $foundEntryOnToggl = null;

                    foreach ($togglEntriesForThisTask as $togglEntryForThisTask) {
                        if (
                            Utils::clickUpEntryEqualsTogglEntry($clickUpEntry, $togglEntryForThisTask)
                            && $foundEntryOnToggl === null
                        ) {
                            $foundEntryOnToggl = $clickUpEntry;
                            break;
                        }
                    }

                    if ($foundEntryOnToggl === null) {
                        // ClickUp Entry that is not on toggl!? Delete!
                        dump('[' . $taskId . '] Deleting Unknown Entry');
                        $this->deleteClickUpTimeEntry($teamId, $clickUpEntry['id']);
                    }
                }
            }
        }
    }

    private function filterTogglEntriesByTaskId(array $togglTimeEntries, string $taskId): array
    {
        $filteredEntries = [];

        foreach ($togglTimeEntries as $togglTimeEntry) {
            if ($togglTimeEntry['click_up_id'] === $taskId) {
                $filteredEntries[] = $togglTimeEntry;
            }
        }

        return $filteredEntries;
    }

    /**
     * @throws JsonException
     * @throws ClientExceptionInterface
     */
    private function createClickUpTimeEntry(
        int    $teamId,
        string $taskId,
        int    $currentClickUpUserId,
        array  $togglEntryForThisTask,
    ): bool
    {
        $request = $this->authorizedRequest(
            'POST',
            sprintf('https://api.clickup.com/api/v2/team/%d/time_entries', $teamId),
            Json::encode([
                'tid' => $taskId,
                'start' => $togglEntryForThisTask['start'],
                'duration' => $togglEntryForThisTask['duration'],
                'assignee' => $currentClickUpUserId,
            ])
        );

        $response = $this->client->sendRequest($request);

        if ($response->getStatusCode() !== 200) {
            dump((string)$response->getBody());
        }

        return $response->getStatusCode() === 200;
    }

    private function deleteClickUpTimeEntry(int $teamId, string $timerId)
    {
        $request = $this->authorizedRequest(
            'DELETE',
            sprintf('https://api.clickup.com/api/v2/team/%s/time_entries/%s', $teamId, $timerId),
        );

        return $this->client->sendRequest($request)->getStatusCode() === 200;

        /* const resp = await fetch(
         ``,
   {
     method: 'DELETE',
     headers: {
         'Content-Type': 'application/json',
       Authorization: 'YOUR_API_KEY_HERE'
     }
   }
 );*/

    }
}
