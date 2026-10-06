<?php

namespace App\Markdown;

/**
 * A task line, `- [ ] T12 Title (key value, ...)`, read the way check-tasks.py reads it.
 *
 * One known difference: the checker's \d also matches non-ASCII digits (T٣); here an ID is ASCII
 * digits only, so such a line is "text between sections" and the file is read-only.
 */
final readonly class TaskLine
{
    private const string PATTERN = '/\A- \[( |x)\] (T([0-9]+)) (.+)\z/su';

    private const string TRAILING_PARENS = '/\A(.*?)['.Text::SPACE.']*\(([^()]*)\)\z/su';

    public function __construct(
        public bool $checked,
        public string $id,
        public int $number,
        public string $title,
        public Metadata $metadata,
    ) {}

    /** Null when the text isn't a task line. Expects valid UTF-8 with no line break. */
    public static function parse(string $text): ?self
    {
        if (! preg_match(self::PATTERN, $text, $match)) {
            return null;
        }
        [$title, $metadata] = self::splitMetadata($match[4]);

        return new self($match[1] === 'x', $match[2], (int) $match[3], $title, $metadata ?? new Metadata);
    }

    /**
     * Splits the text after the ID into the title and its metadata, as the checker's split_metadata() does:
     * the final parentheses are metadata only if every comma-separated part starts with a known key.
     *
     * @return array{0: string, 1: Metadata|null} null metadata when the parentheses (if any) belong to the title
     */
    public static function splitMetadata(string $rest): array
    {
        if (! preg_match(self::TRAILING_PARENS, $rest, $match)) {
            return [Text::strip($rest), null];
        }
        $parts = array_map(Text::strip(...), explode(',', $match[2]));
        foreach ($parts as $part) {
            if (! in_array(explode(' ', $part, 2)[0], Metadata::KEYS, true)) {
                return [Text::strip($rest), null];
            }
        }
        $pairs = array_map(function (string $part): array {
            $pair = explode(' ', $part, 2);

            return [$pair[0], Text::strip($pair[1] ?? '')];
        }, $parts);

        return [Text::strip($match[1]), new Metadata($pairs)];
    }

    public function render(): string
    {
        return '- ['.($this->checked ? 'x' : ' ').'] '.$this->id.' '.$this->title.$this->metadata->render();
    }

    public function equals(self $other): bool
    {
        return $this->checked === $other->checked
            && $this->id === $other->id
            && $this->title === $other->title
            && $this->metadata->pairs === $other->metadata->pairs;
    }

    public function withChecked(bool $checked): self
    {
        return new self($checked, $this->id, $this->number, $this->title, $this->metadata);
    }

    public function withTitle(string $title): self
    {
        return new self($this->checked, $this->id, $this->number, $title, $this->metadata);
    }

    public function withMetadata(Metadata $metadata): self
    {
        return new self($this->checked, $this->id, $this->number, $this->title, $metadata);
    }
}
