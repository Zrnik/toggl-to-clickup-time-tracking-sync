<?php

declare(strict_types=1);

namespace UploadTool\Command;

use GuzzleHttp\Exception\GuzzleException;
use Nette\Utils\JsonException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use UploadTool\ClickUp\ClickUpConnector;
use UploadTool\ClickUp\ClickUpRateLimiter;
use UploadTool\Misc\ApiKeyProvider;
use UploadTool\Misc\UploadFacade;
use UploadTool\Toggl\TogglConnector;

class SyncCommand extends Command
{
    public const string DASH_SEPARATOR = '-------------------------------------------------';

    public const string EQUALS_SEPARATOR = '=================================================';

    public function __construct()
    {
        parent::__construct('tool:sync');
    }

    /**
     * @throws GuzzleException
     * @throws JsonException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $apiKeyProvider = new ApiKeyProvider();
        $rateLimiter = new ClickUpRateLimiter($output);

        $uploadFacade = new UploadFacade(
            $output,
            new TogglConnector($output, $apiKeyProvider->getTogglApiKey()),
            new ClickUpConnector($rateLimiter, $output, $apiKeyProvider->getClickUpApiKey()),
        );

        $uploadFacade->run();

        return 0;
    }
}
