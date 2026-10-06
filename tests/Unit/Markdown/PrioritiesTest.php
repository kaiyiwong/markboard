<?php

use App\Markdown\Priorities;

it('ranks categories by the Order line, with = for equal ranks', function () {
    $priorities = Priorities::parse("# Priorities\n\nSome text.\nOrder: own-site, client, job, product = game = gen-ai\n");

    expect($priorities->ranks)->toBe(['own-site' => 0, 'client' => 1, 'job' => 2, 'product' => 3, 'game' => 3, 'gen-ai' => 3])
        ->and($priorities->rank('game'))->toBe(3);
});

it('ranks categories it does not name after all named ones', function () {
    $priorities = Priorities::parse("Order: client, job\n");

    expect($priorities->rank('personal'))->toBe(2)
        ->and($priorities->rank('product'))->toBe(2);
});

it('reads only the first Order line, and tolerates loose spacing', function () {
    $priorities = Priorities::parse("  Order:job=client ,  game\nOrder: game\n");

    expect($priorities->ranks)->toBe(['job' => 0, 'client' => 0, 'game' => 1]);
});

it('ranks every category equal with no Order line or no file', function (string $bytes) {
    $priorities = Priorities::parse($bytes);

    expect($priorities->ranks)->toBe([])
        ->and($priorities->rank('client'))->toBe(0)
        ->and($priorities->rank('personal'))->toBe(0);
})->with([
    'no Order line' => ["# Priorities\n\nClients first, mostly.\n"],
    'no file' => [''],
]);
