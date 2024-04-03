<?php

declare(strict_types=1);

namespace UploadTool\ClickUp;

use RuntimeException;

readonly class ClickUpIdDetector
{
    /**
     * @param string[] $taskIds
     */
    final private function __construct(
        private array $taskIds,
        bool $skipTests = false,
    ) {
        if (! $skipTests) {
            self::test();
        }
    }

    /**
     * @param string $code Any string
     * @return string|null Pure valid code or null
     */
    public function find(string $code): ?string
    {
        $pieces = explode('#', $code);
        unset($pieces[0]);

        foreach ($pieces as $piece) {
            foreach ($this->taskIds as $taskId) {
                if (str_starts_with($piece, $taskId)) {
                    return $taskId;
                }
            }
        }

        return null;
    }

    // This is a quick test it works properly...
    private static function test(): void
    {
        $clickUpIdDetector = new ClickUpIdDetector(['qwe', 'asd', 'yxc'], true);

        $detectableValue = [
            'hello world #qwe' => 'qwe',
            'hello world #asd' => 'asd',
            '#asdqweyxc' => 'asd',
            '#yxcasdqweyxc' => 'yxc',
            '#yxsdqweyxc' => null,
            '#awsdcqwe' => null,
            'qwe' => null,
            'asd' => null,
            'yxc' => null,
            '#qwe #asd' => 'qwe',
            '#asd #qwe' => 'asd',
        ];

        foreach ($detectableValue as $code => $expectedResult) {
            if ($clickUpIdDetector->find($code) !== $expectedResult) {
                throw new RuntimeException(
                    sprintf(
                        'Test Failed, "%s" not found in "%s"',
                        $expectedResult,
                        $code
                    )
                );
            }
        }
    }

    /**
     * @param string[] $taskIds
     * @return self
     */
    public static function fromIds(array $taskIds): self
    {
        return new self($taskIds);
    }
}
