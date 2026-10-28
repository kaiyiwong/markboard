<?php

use App\Review\ReviewCard;

/*
| Review cards (the hub's docs/spec.md, "review/<task>.json"), read without booting the app. The
| messages match the hub's check-review.py, so the page and the brief name the same problems.
*/

beforeEach(function () {
    $this->project = sys_get_temp_dir().'/markboard-review-'.bin2hex(random_bytes(6));
    mkdir($this->project.'/shots', 0777, true);
    foreach (['a.png', 'b.png', 'c.png'] as $file) {
        file_put_contents($this->project.'/shots/'.$file, "\x89PNG");
    }
});

afterEach(function () {
    array_map('unlink', glob($this->project.'/shots/*') ?: []);
    rmdir($this->project.'/shots');
    rmdir($this->project);
});

function pickCard(array $changes = []): array
{
    $card = array_merge([
        'question' => 'Which header?',
        'answer' => 'pick',
        'images' => [['label' => '1 Quiet', 'path' => 'shots/a.png'], ['label' => '2 Loud', 'path' => 'shots/b.png']],
    ], $changes);

    return array_filter($card, fn ($value) => $value !== null);
}

function cardErrors(mixed $card, string $project): array
{
    return ReviewCard::parse(is_string($card) ? $card : json_encode($card), $project)->errors;
}

it('reads a good card of each kind', function () {
    $pick = ReviewCard::parse(json_encode(pickCard()), $this->project);
    expect($pick->errors)->toBe([])
        ->and($pick->answer)->toBe('pick')
        ->and($pick->images)->toBe([['label' => '1 Quiet', 'path' => 'shots/a.png'], ['label' => '2 Loud', 'path' => 'shots/b.png']])
        ->and($pick->imagePath($this->project, 1))->toBe(realpath($this->project.'/shots/b.png'))
        ->and($pick->imagePath($this->project, 2))->toBeNull();

    $choose = ReviewCard::parse(json_encode(['question' => 'Keep it?', 'answer' => 'choose', 'images' => [], 'choices' => ['Keep', 'Old one']]), $this->project);
    expect($choose->errors)->toBe([])->and($choose->choices)->toBe(['Keep', 'Old one']);

    expect(cardErrors(pickCard(['answer' => 'approve', 'images' => [['label' => 'New', 'path' => 'shots/a.png']]]), $this->project))->toBe([]);
});

it('names file-level and key errors', function () {
    expect(cardErrors('{nope', $this->project)[0])->toStartWith('not valid JSON')
        ->and(cardErrors([1, 2], $this->project))->toBe(['not a JSON object'])
        ->and(cardErrors(pickCard(['question' => null]), $this->project))->toBe(['missing key: question'])
        ->and(cardErrors(pickCard(['extra' => 'x']), $this->project))->toBe(['unknown key: extra'])
        ->and(cardErrors(pickCard(['images' => 'a.png']), $this->project))->toBe(['images is not a list'])
        ->and(cardErrors(pickCard(['images' => [['path' => 'shots/a.png'], ['label' => '2', 'path' => 'shots/b.png']]]), $this->project))
        ->toBe(['image 1 is not {label, path}']);
});

it('names text errors', function () {
    expect(cardErrors(pickCard(['question' => '  ']), $this->project))->toBe(['question is empty or not text'])
        ->and(cardErrors(pickCard(['question' => "a\nb"]), $this->project))->toBe(['question is more than one line'])
        ->and(cardErrors(pickCard(['question' => str_repeat('x', 121)]), $this->project))->toBe(['question is over 120 characters'])
        ->and(cardErrors(pickCard(['images' => [['label' => str_repeat('x', 41), 'path' => 'shots/a.png'], ['label' => '2', 'path' => 'shots/b.png']]]), $this->project))
        ->toBe(['image 1 label is over 40 characters']);
});

it('names answer, count and repeat errors', function () {
    $five = array_map(fn (int $n): array => ['label' => (string) $n, 'path' => 'shots/a.png'], range(1, 5));
    expect(cardErrors(pickCard(['answer' => 'vote']), $this->project))->toBe(['unknown answer: "vote" (pick, approve or choose)'])
        ->and(cardErrors(pickCard(['images' => [['label' => '1', 'path' => 'shots/a.png']]]), $this->project))->toBe(['pick needs 2 to 10 images, has 1'])
        ->and(cardErrors(pickCard(['answer' => 'approve', 'images' => $five]), $this->project))->toBe(['approve needs 1 to 4 images, has 5'])
        ->and(cardErrors(pickCard(['choices' => ['A', 'B']]), $this->project))->toBe(['choices on a pick card'])
        ->and(cardErrors(pickCard(['answer' => 'choose', 'images' => []]), $this->project))->toBe(['choose needs a list of choices'])
        ->and(cardErrors(pickCard(['answer' => 'choose', 'images' => [], 'choices' => ['A']]), $this->project))->toBe(['choose needs 2 to 4 choices, has 1'])
        ->and(cardErrors(pickCard(['images' => [['label' => 'A', 'path' => 'shots/a.png'], ['label' => 'A', 'path' => 'shots/b.png']]]), $this->project))->toBe(['repeated label: A'])
        ->and(cardErrors(pickCard(['answer' => 'choose', 'images' => [], 'choices' => ['Keep', 'Keep']]), $this->project))->toBe(['repeated choice: Keep']);
});

it('names image path errors, and refuses a symlink out of the project', function () {
    $one = fn (string $path): array => cardErrors(pickCard(['images' => [['label' => '1', 'path' => $path], ['label' => '2', 'path' => 'shots/b.png']]]), $this->project);
    expect($one('/etc/a.png'))->toBe(['image 1 path is absolute: /etc/a.png'])
        ->and($one('../a.png'))->toBe(['image 1 path leaves the project: ../a.png'])
        ->and($one('shots/../../a.png'))->toBe(['image 1 path leaves the project: shots/../../a.png'])
        ->and($one('shots/a.svg'))->toBe(['image 1 is not a png, jpg, webp or gif: shots/a.svg'])
        ->and($one('shots/gone.png'))->toBe(['image 1 not found: shots/gone.png'])
        ->and($one('shots/../shots/c.png'))->toBe([]);

    $outside = tempnam(sys_get_temp_dir(), 'outside').'.png';
    file_put_contents($outside, "\x89PNG");
    symlink($outside, $this->project.'/shots/link.png');
    expect($one('shots/link.png'))->toBe(['image 1 path leaves the project: shots/link.png']);
    unlink($outside);
});
