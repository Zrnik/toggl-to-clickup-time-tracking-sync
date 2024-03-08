<?php

namespace UploadTool;

use Nette\Neon\Exception;
use Nette\Neon\Neon;
use RuntimeException;

class ApiKeyProvider
{
    private string $togglApiKey;
    private string $clickUpApiKey;

    public function __construct(string $configPath)
    {
        try {
            /** @var array<string, scalar> $config */
            $config = Neon::decodeFile($configPath);

            $this->togglApiKey = $this->parseArgument($config, 'togglApiKey');
            $this->clickUpApiKey = $this->parseArgument($config, 'clickUpApiKey');

        } catch (Exception $e) {
            throw new RuntimeException(sprintf('Error parsing config file "%s"!', $configPath), 0, $e);
        }
    }

    /**
     * @param array<string, scalar> $config
     * @param string $argumentKey
     * @return string
     */
    private function parseArgument(array $config, string $argumentKey): string
    {
        $apiKey = $config[$argumentKey] ?? throw new RuntimeException(
            sprintf('%s is missing in config file!', $argumentKey)
        );

        if (!is_string($apiKey)) {
            throw new RuntimeException(
                sprintf('%s must be a string!', $argumentKey)
            );
        }

        if (trim($apiKey) === '') {
            throw new RuntimeException(
                sprintf('%s must not be empty!', $argumentKey)
            );
        }

        return $apiKey;
    }

    public function getTogglApiKey(): string
    {
        return $this->togglApiKey;
    }

    public function getClickUpApiKey(): string
    {
        return $this->clickUpApiKey;
    }
}
