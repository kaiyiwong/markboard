<?php

namespace App\Http\Controllers;

use App\Markdown\Brief;
use App\Markdown\BriefLinks;
use App\Models\Project;
use App\Sync\Hub;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class BriefController extends Controller
{
    /**
     * Today's brief (TODAY.md) or a past one, split into its title and sections, with the past
     * briefs listed beside it. Briefs aren't indexed: they are read from the hub on each request.
     */
    public function show(Hub $hub, ?string $date = null): Response
    {
        $path = $hub->briefPath($date);
        abort_if($date !== null && ! is_file($path), 404);

        return Inertia::render('Brief/Show', [
            'date' => $date,
            'brief' => is_file($path) ? self::brief((string) file_get_contents($path)) : null,
            'dates' => $hub->briefDates(),
        ]);
    }

    /**
     * @return array{title: string|null, intro: string, sections: list<array{title: string, items: int, html: string}>}
     */
    private static function brief(string $markdown): array
    {
        /** @var array<string, string> $projects */
        $projects = Project::pluck('name', 'id')->all();
        $brief = Brief::parse($markdown);

        return [
            'title' => $brief->title,
            'intro' => self::render($brief->intro, $projects),
            'sections' => array_map(fn (array $section): array => [
                'title' => $section['title'],
                'items' => $section['items'],
                'html' => self::render($section['markdown'], $projects),
            ], $brief->sections),
        ];
    }

    /**
     * Raw HTML in a brief shows as text, and unsafe links (javascript:, data:) are dropped.
     *
     * @param  array<string, string>  $projects
     */
    private static function render(string $markdown, array $projects): string
    {
        return Str::markdown(BriefLinks::apply($markdown, $projects), [
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
        ]);
    }
}
