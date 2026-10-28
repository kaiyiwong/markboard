<?php

namespace App\Review;

/**
 * A review card (`<project>/review/<task>.json`): what Kai needs to answer a task waiting on him
 * without opening the session that parked it. The format and its rules are the hub's
 * (its docs/spec.md, "review/<task>.json", checked by its scripts/check-review.py); this reads
 * them the same way, so a card the hub flags shows here as text with its errors.
 */
final readonly class ReviewCard
{
    public const array ANSWERS = ['pick' => [2, 10], 'approve' => [1, 4], 'choose' => [0, 4]];

    private const array KEYS = ['question', 'answer', 'images', 'choices'];

    private const array REQUIRED = ['question', 'answer', 'images'];

    private const array IMAGE_TYPES = ['png', 'jpg', 'jpeg', 'webp', 'gif'];

    private const int QUESTION_MAX = 120;

    private const int LABEL_MAX = 40;

    /**
     * @param  list<array{label: string, path: string}>  $images  paths relative to the project
     * @param  list<string>  $choices
     * @param  list<string>  $errors  empty when the card can be shown
     */
    public function __construct(
        public string $question,
        public string $answer,
        public array $images,
        public array $choices,
        public array $errors,
    ) {}

    public static function path(string $projectPath, string $taskId): string
    {
        return $projectPath.'/review/'.$taskId.'.json';
    }

    public static function read(string $projectPath, string $taskId): ?self
    {
        $path = self::path($projectPath, $taskId);

        return is_file($path) ? self::parse((string) file_get_contents($path), $projectPath) : null;
    }

    public static function parse(string $json, string $projectPath): self
    {
        $card = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return self::broken('not valid JSON: '.json_last_error_msg());
        }
        if (! is_array($card) || array_is_list($card)) {
            return self::broken('not a JSON object');
        }

        $errors = [];
        foreach (array_diff(self::REQUIRED, array_keys($card)) as $key) {
            $errors[] = "missing key: {$key}";
        }
        foreach (array_diff(array_keys($card), self::KEYS) as $key) {
            $errors[] = "unknown key: {$key}";
        }

        $question = $card['question'] ?? '';
        if (array_key_exists('question', $card) && ($e = self::lineError($question, self::QUESTION_MAX, 'question'))) {
            $errors[] = $e;
        }

        $answer = $card['answer'] ?? null;
        if (array_key_exists('answer', $card) && ! (is_string($answer) && isset(self::ANSWERS[$answer]))) {
            $errors[] = 'unknown answer: '.json_encode($answer).' (pick, approve or choose)';
            $answer = null;
        }

        $images = [];
        $raw = $card['images'] ?? [];
        $isList = is_array($raw) && array_is_list($raw);
        if (! $isList) {
            $errors[] = 'images is not a list';
            $raw = [];
        }
        foreach ($raw as $i => $image) {
            $n = $i + 1;
            if (! is_array($image) || array_is_list($image) || ! self::keysAre($image, ['label', 'path'])) {
                $errors[] = "image {$n} is not {label, path}";

                continue;
            }
            $labelError = self::lineError($image['label'], self::LABEL_MAX, "image {$n} label");
            $pathError = self::pathError($image['path'], $projectPath, $n);
            array_push($errors, ...array_filter([$labelError, $pathError]));
            if ($labelError === null && $pathError === null) {
                $images[] = ['label' => trim($image['label']), 'path' => $image['path']];
            }
        }
        $errors = [...$errors, ...self::repeats(array_column($images, 'label'), 'label')];
        if ($answer !== null && $isList && array_key_exists('images', $card)) {
            [$low, $high] = self::ANSWERS[$answer];
            if (count($raw) < $low || count($raw) > $high) {
                $errors[] = "{$answer} needs {$low} to {$high} images, has ".count($raw);
            }
        }

        $choices = [];
        if (array_key_exists('choices', $card) && $answer !== null && $answer !== 'choose') {
            $errors[] = "choices on a {$answer} card";
        } elseif ($answer === 'choose') {
            [$choices, $choiceErrors] = self::choices($card['choices'] ?? null);
            $errors = [...$errors, ...$choiceErrors];
        }

        return new self(is_string($question) ? trim($question) : '', $answer ?? '', $images, $choices, $errors);
    }

    /**
     * @return array{0: list<string>, 1: list<string>}
     */
    private static function choices(mixed $raw): array
    {
        if (! is_array($raw) || ! array_is_list($raw)) {
            return [[], ['choose needs a list of choices']];
        }
        $choices = [];
        $errors = [];
        foreach ($raw as $i => $choice) {
            if ($e = self::lineError($choice, self::LABEL_MAX, 'choice '.($i + 1))) {
                $errors[] = $e;
            } else {
                $choices[] = trim($choice);
            }
        }
        if (count($raw) < 2 || count($raw) > 4) {
            $errors[] = 'choose needs 2 to 4 choices, has '.count($raw);
        }

        return [$choices, [...$errors, ...self::repeats($choices, 'choice')]];
    }

    private static function lineError(mixed $value, int $limit, string $what): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return "{$what} is empty or not text";
        }
        if (preg_match('/[\r\n]/', $value) === 1) {
            return "{$what} is more than one line";
        }
        if (mb_strlen($value) > $limit) {
            return "{$what} is over {$limit} characters";
        }

        return null;
    }

    private static function pathError(mixed $value, string $projectPath, int $n): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return "image {$n} path is empty or not text";
        }
        if (str_starts_with($value, '/')) {
            return "image {$n} path is absolute: {$value}";
        }
        $depth = 0;
        foreach (explode('/', $value) as $part) {
            $depth += match ($part) {
                '..' => -1,
                '', '.' => 0,
                default => 1,
            };
            if ($depth < 0) {
                return "image {$n} path leaves the project: {$value}";
            }
        }
        if (! in_array(strtolower(pathinfo($value, PATHINFO_EXTENSION)), self::IMAGE_TYPES, true)) {
            return "image {$n} is not a png, jpg, webp or gif: {$value}";
        }
        $full = realpath($projectPath.'/'.$value);
        if ($full === false || ! is_file($full)) {
            return "image {$n} not found: {$value}";
        }
        if (! str_starts_with($full, realpath($projectPath).'/')) {
            return "image {$n} path leaves the project: {$value}";
        }

        return null;
    }

    /**
     * @param  list<string>  $values
     * @return list<string>
     */
    private static function repeats(array $values, string $what): array
    {
        $repeated = array_keys(array_filter(array_count_values($values), fn (int $count): bool => $count > 1));
        sort($repeated);

        return array_map(fn (string $value): string => "repeated {$what}: {$value}", $repeated);
    }

    /**
     * @param  array<mixed>  $value
     * @param  list<string>  $keys
     */
    private static function keysAre(array $value, array $keys): bool
    {
        $actual = array_keys($value);
        sort($actual);

        return $actual === $keys;
    }

    private static function broken(string $error): self
    {
        return new self('', '', [], [], [$error]);
    }

    /** The image's real path, for serving it; null if it no longer passes the card's rules. */
    public function imagePath(string $projectPath, int $index): ?string
    {
        $image = $this->images[$index] ?? null;

        return $this->errors === [] && $image !== null ? realpath($projectPath.'/'.$image['path']) ?: null : null;
    }
}
