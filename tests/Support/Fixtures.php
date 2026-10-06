<?php

namespace Tests\Support;

/**
 * The small TASKS.md and pipeline.md files in tests/Fixtures, each holding an edge case the demo hub shouldn't carry.
 */
final class Fixtures
{
    public const string TASKS = __DIR__.'/../Fixtures/tasks';

    public const string PIPELINE = __DIR__.'/../Fixtures/pipeline';

    public static function tasks(string $name): string
    {
        return (string) file_get_contents(self::TASKS."/{$name}");
    }

    public static function pipeline(string $name): string
    {
        return (string) file_get_contents(self::PIPELINE."/{$name}");
    }

    /** @return list<string> */
    public static function taskNames(): array
    {
        return self::names(self::TASKS);
    }

    /** @return list<string> */
    public static function pipelineNames(): array
    {
        return self::names(self::PIPELINE);
    }

    /** @return array<string, array{string}> every TASKS.md fixture, as a Pest dataset */
    public static function taskDataset(): array
    {
        return self::dataset(self::taskNames());
    }

    /** @return array<string, array{string}> every pipeline.md fixture, as a Pest dataset */
    public static function pipelineDataset(): array
    {
        return self::dataset(self::pipelineNames());
    }

    /** @return list<string> */
    private static function names(string $dir): array
    {
        return array_map(basename(...), glob("{$dir}/*.md") ?: []);
    }

    /**
     * @param  list<string>  $names
     * @return array<string, array{string}>
     */
    private static function dataset(array $names): array
    {
        return array_combine($names, array_map(fn (string $name): array => [$name], $names));
    }
}
