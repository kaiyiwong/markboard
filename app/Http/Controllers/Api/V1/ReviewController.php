<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\AnswerTask;
use App\Actions\WriteFile;
use App\Http\Controllers\Controller;
use App\Http\Requests\AnswerTaskRequest;
use App\Markdown\InputRules;
use App\Models\Project;
use App\Review\ReviewCard;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

/**
 * Kai's answer to a task waiting on him (docs/spec.md, Review): one write to the project's
 * TASKS.md, then the task's review card is deleted. A refused write keeps the card.
 */
class ReviewController extends Controller
{
    public function __construct(
        private readonly WriteFile $write,
    ) {}

    public function answer(AnswerTaskRequest $request, Project $project, string $task): JsonResponse
    {
        $answer = $request->string('answer')->toString();
        $value = $request->input('value');
        $note = $request->input('note');

        $words = match ($answer) {
            'pick' => 'picked '.self::onCard($project, $task, 'pick', $value),
            'choose' => 'chose '.self::onCard($project, $task, 'choose', $value),
            'approve' => self::onCard($project, $task, 'approve', null) ?? 'approved',
            'redo' => self::onCard($project, $task, 'approve', null) ?? 'redo',
            default => null,
        };
        $line = match (true) {
            $words !== null && $note !== null => "{$words}; {$note}",
            default => $words ?? $note,
        };
        $line = $line === null ? null : 'Kai '.today(config()->string('markboard.timezone'))->toDateString().': '.$line;
        if ($line !== null && mb_strlen($line) > InputRules::TEXT_MAX) {
            throw ValidationException::withMessages(['note' => 'The note line would be over '.InputRules::TEXT_MAX.' characters with the answer before it.']);
        }

        $outcome = match ($answer) {
            'done' => AnswerTask::TICK,
            'drop' => AnswerTask::CANCEL,
            default => AnswerTask::UP_NEXT,
        };
        $response = $this->write->write($project, new AnswerTask((int) substr($task, 1), $outcome, $line), $request->etag());

        $card = ReviewCard::path($project->path, $task);
        if (is_file($card)) {
            unlink($card);
        }

        return $response;
    }

    /**
     * Checks the task's card takes this answer, and returns the value as the card writes it
     * (null for an approve card, which has no value).
     */
    private static function onCard(Project $project, string $task, string $kind, mixed $value): ?string
    {
        $card = ReviewCard::read($project->path, $task);
        abort_if($card === null, 409, "{$task} has no review card.");
        abort_if($card->errors !== [], 409, "{$task}'s review card has errors: ".implode('; ', $card->errors));
        abort_if($card->answer !== $kind, 409, "{$task}'s review card is a {$card->answer} card.");
        if ($kind === 'approve') {
            return null;
        }

        $options = $kind === 'pick' ? array_column($card->images, 'label') : $card->choices;
        if (! in_array($value, $options, true)) {
            throw ValidationException::withMessages(['value' => 'Answer with one of: '.implode(', ', $options).'.']);
        }

        return $value;
    }
}
