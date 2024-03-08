<?php

namespace UploadTool;

use DateInterval;
use DateTimeImmutable;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use Nette\Utils\Json;
use Nette\Utils\JsonException;
use Psr\Http\Client\ClientExceptionInterface;

/**
 * @phpstan-type TogglCustomEntryFormatArrayType array{id: int, name: string, start: int, end: int, duration: int}
 */
class TogglApi
{
    private Client $client;

    public function __construct(private readonly string $apiKey)
    {
        $this->client = new Client();
    }

    private function authorizedRequest(
        string  $method,
        string  $url,
        ?string $body = null,
    ): Request
    {
        return new Request(
            $method,
            $url,
            [
                'Content-Type' => 'application/json',
                'Authorization' => sprintf(
                    'Basic %s',
                    base64_encode(sprintf('%s:api_token', $this->apiKey))
                ),
            ],
            $body
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws JsonException
     * @phpstan-return TogglCustomEntryFormatArrayType[]
     */
    public function getAllTimeEntriesForLast40Days(): array
    {
        $today = new DateTimeImmutable();
        $fortyDaysAgo = $today->sub(DateInterval::createFromDateString('40 days'));

        $request = $this->authorizedRequest('GET', 'https://api.track.toggl.com/api/v9/me?with_related_data=true');
        $response = $this->client->sendRequest($request);
        $result = Json::decode($response->getBody()->getContents(), forceArrays: true);

        $workspaceApiKeyByWorkSpaceIdList = [];

        foreach ($result['workspaces'] as $workspace) {
            $workspaceApiKeyByWorkSpaceIdList[(int)$workspace['id']] = $workspace['api_token'];
        }

        $deadLockProtection = 50;

        $timeEntryList = [];

        foreach ($workspaceApiKeyByWorkSpaceIdList as $workspaceId => $workSpaceApiKey) {

            $firstId = null;
            do {
                $request = $this->authorizedRequest(
                    'POST',
                    sprintf(
                        'https://api.track.toggl.com/reports/api/v3/workspace/%s/search/time_entries',
                        $workspaceId,
                    ),
                    Json::encode([
                        'start_date' => $fortyDaysAgo->format('Y-m-d'),
                        'end_date' => $today->format('Y-m-d'),
                        'first_id' => $firstId,
                    ])
                );

                $response = $this->client->sendRequest($request);
                $nextIdHeader = $response->getHeader('X-Next-Id');
                $firstId = $nextIdHeader[0] ?? null;

                if ($firstId !== null) {
                    $firstId = (int)$firstId;
                }

                $result = Json::decode($response->getBody()->getContents(), forceArrays: true);

                foreach ($result as $timeEntryContainer) {
                    foreach ($timeEntryContainer['time_entries'] as $timeEntry) {
                        // Bruh, im usually never-nester XD
                        // But this is a quick dirty tool, please don't judge me :D
                        $timeEntryList[] = [
                            'id' => (int)$timeEntry['id'],
                            'name' => (string)$timeEntryContainer['description'],
                            'start' => strtotime($timeEntry['start']) * 1000,
                            'end' => strtotime($timeEntry['stop']) * 1000,
                            'duration' => ((int)$timeEntry['seconds']) * 1000,
                        ];
                    }
                }
            } while ($firstId !== null && --$deadLockProtection > 0);
        }

        return $timeEntryList;
    }
}
