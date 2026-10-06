<?php

namespace Tests\Support;

/**
 * The small TASKS.md files in tests/Fixtures/tasks, each holding an edge case the demo hub shouldn't carry.
 */
final class Fixtures
{
    public const string TASKS = __DIR__.'/../Fixtures/tasks';

    public static function tasks(string $name): string
    {
        return (string) file_get_contents(self::TASKS."/{$name}");
    }

    /** @return list<string> */
    public static function taskNames(): array
    {
        return array_map(basename(...), glob(self::TASKS.'/*.md') ?: []);
    }

    /** @return array<string, array{string}> every fixture, as a Pest dataset */
    public static function taskDataset(): array
    {
        $names = self::taskNames();

        return array_combine($names, array_map(fn (string $name): array => [$name], $names));
    }
}
