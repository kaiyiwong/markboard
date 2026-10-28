<?php

use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;

/*
| The Review page, on a temp copy of demo/ (see TestCase): four tasks wait on Kai, three with a
| review card (one of each kind) and one with none. The demo's images are written here, so the
| tests don't depend on the image bytes.
*/

beforeEach(function () {
    foreach ([
        'fieldnotes/designs/header-1.png', 'fieldnotes/designs/header-2.png', 'fieldnotes/designs/header-3.png',
        'bramble-bakery/designs/order-before.png', 'bramble-bakery/designs/order-after.png',
    ] as $image) {
        File::ensureDirectoryExists(dirname($this->hub.'/projects/'.$image));
        File::put($this->hub.'/projects/'.$image, "\x89PNG {$image}");
    }
});

/**
 * The page's items, keyed by "<project> <task>".
 *
 * @return array<string, array<string, mixed>>
 */
function reviewItems($test): array
{
    $items = $test->get('/review')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Review/Show'))
        ->inertiaProps('items');

    return collect($items)->keyBy(fn (array $item): string => $item['project']['id'].' '.$item['task']['task_id'])->all();
}

it('lists every task waiting on Kai in active projects, in priority order', function () {
    $items = reviewItems($this);

    expect(array_keys($items))->toBe(['fieldnotes T4', 'fieldnotes T5', 'bramble-bakery T7', 'job-search T5'])
        ->and($items['fieldnotes T4']['project'])->toBe(['id' => 'fieldnotes', 'name' => 'Fieldnotes'])
        ->and($items['fieldnotes T4']['since'])->toBe('2026-10-06')
        ->and($items['fieldnotes T4']['task']['title'])->toBe('Pick the case study header');
});

it('sends each card with its image urls, and no card for a task without one', function () {
    $items = reviewItems($this);

    expect($items['fieldnotes T4']['card'])->toBe([
        'question' => 'Which header for the kiln case study?',
        'answer' => 'pick',
        'images' => [
            ['label' => '1 Photo first', 'url' => '/review/fieldnotes/T4/images/0'],
            ['label' => '2 Title first', 'url' => '/review/fieldnotes/T4/images/1'],
            ['label' => '3 Split', 'url' => '/review/fieldnotes/T4/images/2'],
        ],
        'choices' => [],
    ])
        ->and($items['bramble-bakery T7']['card']['answer'])->toBe('approve')
        ->and(array_column($items['bramble-bakery T7']['card']['images'], 'label'))->toBe(['Before', 'After'])
        ->and($items['job-search T5']['card'])->toMatchArray(['answer' => 'choose', 'images' => [], 'choices' => ['Tue Oct 14', 'Thu Oct 16', 'Ask for next week']])
        ->and($items['fieldnotes T5']['card'])->toBeNull()
        ->and($items['fieldnotes T5']['card_errors'])->toBe([])
        ->and($items['fieldnotes T5']['task']['notes'])->toBe(['the long version has the firing photos; the short one ends on the result']);
});

it('shows a bad card as text, with its errors', function () {
    File::put($this->hub.'/projects/job-search/review/T5.json', '{"question": "Which?", "answer": "vote", "images": []}');
    File::delete($this->hub.'/projects/fieldnotes/designs/header-2.png');

    $items = reviewItems($this);

    expect($items['job-search T5']['card'])->toBeNull()
        ->and($items['job-search T5']['card_errors'])->toBe(['unknown answer: "vote" (pick, approve or choose)'])
        ->and($items['fieldnotes T4']['card'])->toBeNull()
        ->and($items['fieldnotes T4']['card_errors'])->toBe(['image 2 not found: designs/header-2.png']);
});

it('leaves out paused projects and tasks waiting on someone else', function () {
    File::put($this->hub.'/projects/tidepool/TASKS.md', "# Tidepool\n\n## Up next\n\n## In progress\n\n## Waiting on\n- [ ] T1 Pick a palette (waiting Kai, since 2026-10-01)\n\n## Done\n");
    File::put($this->hub.'/projects/job-search/TASKS.md', str_replace('(waiting Kai,', '(waiting Northwind,', File::get($this->hub.'/projects/job-search/TASKS.md')));

    expect(array_keys(reviewItems($this)))->toBe(['fieldnotes T4', 'fieldnotes T5', 'bramble-bakery T7']);
});

it('serves a card image, and 404s anything the card does not name', function () {
    $response = $this->get('/review/fieldnotes/T4/images/1')->assertOk();
    expect($response->baseResponse->getFile()->getContent())->toBe("\x89PNG fieldnotes/designs/header-2.png");

    $this->get('/review/fieldnotes/T4/images/3')->assertNotFound();
    $this->get('/review/fieldnotes/T5/images/0')->assertNotFound();
    $this->get('/review/job-search/T5/images/0')->assertNotFound();
    $this->get('/review/nowhere/T4/images/0')->assertNotFound();
    $this->get('/review/fieldnotes/..%2FT4/images/0')->assertNotFound();

    File::put($this->hub.'/projects/fieldnotes/review/T4.json', json_encode([
        'question' => 'Which?', 'answer' => 'pick',
        'images' => [['label' => '1', 'path' => '../bramble-bakery/designs/order-after.png'], ['label' => '2', 'path' => 'designs/header-1.png']],
    ]));
    $this->get('/review/fieldnotes/T4/images/0')->assertNotFound();
    $this->get('/review/fieldnotes/T4/images/1')->assertNotFound();
});
