<?php

namespace App\Http\Controllers;

use App\Markdown\BriefLinks;
use App\Sync\Hub;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class BriefController extends Controller
{
    /**
     * Today's brief (TODAY.md) or a past one, with the past briefs listed below. Briefs aren't
     * indexed: they are read from the hub on each request.
     */
    public function show(Hub $hub, ?string $date = null): Response
    {
        $path = $hub->briefPath($date);
        abort_if($date !== null && ! is_file($path), 404);

        return Inertia::render('Brief/Show', [
            'date' => $date,
            'html' => is_file($path) ? self::render((string) file_get_contents($path)) : null,
            'dates' => $hub->briefDates(),
        ]);
    }

    /** Raw HTML in a brief shows as text, and unsafe links (javascript:, data:) are dropped. */
    private static function render(string $markdown): string
    {
        return Str::markdown(BriefLinks::apply($markdown), [
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
        ]);
    }
}
