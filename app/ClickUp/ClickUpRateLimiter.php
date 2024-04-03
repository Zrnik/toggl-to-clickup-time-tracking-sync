<?php

declare(strict_types=1);

namespace UploadTool\ClickUp;

use Closure;
use Nette\Utils\Json;
use Nette\Utils\JsonException;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Console\Output\OutputInterface;

readonly class ClickUpRateLimiter
{
    public function __construct(
        private OutputInterface $output
    ) {
    }

    /**
     * @param Closure $closure
     * @return array<mixed>
     * @throws JsonException
     * @noinspection PhpPluralMixedCanBeReplacedWithArrayInspection
     */
    public function limitedRequest(Closure $closure): array
    {
        $response = $closure();

        assert($response instanceof ResponseInterface);

        $clickUpRemainingLimit = (int) ($response->getHeader('X-RateLimit-Remaining')[0] ?? -1);
        $clickUpReset = (int) ($response->getHeader('X-RateLimit-Reset')[0] ?? -1);

        if ($clickUpRemainingLimit < 5) {
            while (time() <= $clickUpReset) {
                $optimalSleep = (int) floor($clickUpReset - time());

                $this->output->write(
                    sprintf(
                        'w%ds',
                        $optimalSleep
                    )
                );

                $maxSleep = 5;
                $minSleep = 1;

                if ($optimalSleep < 1) {
                    break;
                }

                $sleep = min($maxSleep, max($minSleep, $optimalSleep));

                // $this->output->write('['.$sleep.']');

                sleep($sleep);
            }
        }

        return Json::decode($response->getBody()->getContents(), forceArrays: true);
    }
}
