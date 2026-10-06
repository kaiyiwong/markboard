<?php

namespace Tests\Support;

use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Runs the bundled check-tasks.py, the format's final word, from the tests.
 */
final class Checker
{
    public const string PATH = __DIR__.'/../../resources/checker/check-tasks.py';

    /** Loads the checker as a module and runs its check_file() on each path, the same code its CLI runs. */
    private const string BATCH = <<<'PY'
        import importlib.util, json, sys
        spec = importlib.util.spec_from_file_location("check_tasks", sys.argv[1])
        checker = importlib.util.module_from_spec(spec)
        spec.loader.exec_module(checker)
        out = []
        for path in sys.argv[2:]:
            try:
                out.append(checker.check_file(path)["errors"])
            except Exception:
                out.append(None)
        print(json.dumps(out))
        PY;

    /**
     * The checker's format errors for each text, in one Python process.
     *
     * @param  array<array-key, string>  $texts
     * @return array<array-key, list<array{line: int, message: string}>|null> null where the checker crashed
     */
    public static function errors(array $texts): array
    {
        $dir = sys_get_temp_dir().'/markboard-checker-'.bin2hex(random_bytes(6));
        mkdir($dir);
        $paths = [];
        foreach (array_values($texts) as $i => $text) {
            file_put_contents($paths[] = "{$dir}/{$i}.md", $text);
        }

        try {
            // No __pycache__ next to the bundled checker.
            $process = new Process(['python3', '-c', self::BATCH, self::PATH, ...$paths], env: ['PYTHONDONTWRITEBYTECODE' => '1']);
            $process->mustRun();
            /** @var list<list<array{line: int, message: string}>|null> $results */
            $results = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        } finally {
            array_map(unlink(...), $paths);
            rmdir($dir);
        }

        if (count($results) !== count($texts)) {
            throw new RuntimeException('The checker returned '.count($results).' results for '.count($texts).' texts.');
        }

        return array_combine(array_keys($texts), $results);
    }
}
