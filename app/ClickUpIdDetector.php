<?php

namespace UploadTool;

use RuntimeException;

class ClickUpIdDetector
{
    /**
     * @param string[] $existingId
     */
    public function __construct(private readonly array $existingId)
    {
    }

    public function find(string $code): ?string
    {
        $pieces = explode('#', $code);
        unset($pieces[0]);

        foreach ($pieces as $piece) {
            foreach ($this->existingId as $existingId) {
                if (str_starts_with($piece, $existingId)) {
                    return $existingId;
                }
            }
        }

        return null;
    }

    // This is a quick test it works properly...
    public static function test(): void
    {
        $clickUpIdDetector = new ClickUpIdDetector(['qwe', 'asd', 'yxc']);

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
}
