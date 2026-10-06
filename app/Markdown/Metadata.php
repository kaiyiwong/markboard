<?php

namespace App\Markdown;

/**
 * A task line's metadata: the ordered `key value` pairs in its final parentheses.
 *
 * Pairs stay in file order, and a repeated key keeps every pair (a format error the checker reports),
 * so nothing is lost on reading. Changes return a new instance.
 */
final readonly class Metadata
{
    public const array KEYS = ['due', 'started', 'waiting', 'since', 'done', 'evidence', 'from', 'cancelled'];

    public const array DATE_KEYS = ['due', 'started', 'since', 'done', 'cancelled'];

    /**
     * @param  list<array{0: string, 1: string}>  $pairs
     */
    public function __construct(
        public array $pairs = [],
    ) {}

    /** The first value for the key: the one the index uses. */
    public function get(string $key): ?string
    {
        foreach ($this->pairs as [$name, $value]) {
            if ($name === $key) {
                return $value;
            }
        }

        return null;
    }

    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    /**
     * Replaces the key's value in place, collapsing repeated copies into the first position,
     * or appends it when the key is absent.
     */
    public function with(string $key, string $value): self
    {
        if (! $this->has($key)) {
            return new self([...$this->pairs, [$key, $value]]);
        }

        $pairs = [];
        $placed = false;
        foreach ($this->pairs as $pair) {
            if ($pair[0] !== $key) {
                $pairs[] = $pair;
            } elseif (! $placed) {
                $pairs[] = [$key, $value];
                $placed = true;
            }
        }

        return new self($pairs);
    }

    public function without(string ...$keys): self
    {
        return new self(array_values(array_filter(
            $this->pairs,
            fn (array $pair): bool => ! in_array($pair[0], $keys, true),
        )));
    }

    /** ` (key value, key value)`, or '' with no pairs. */
    public function render(): string
    {
        if ($this->pairs === []) {
            return '';
        }

        return ' ('.implode(', ', array_map(fn (array $pair): string => $pair[0].' '.$pair[1], $this->pairs)).')';
    }
}
