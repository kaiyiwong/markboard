<?php

namespace App\Markdown;

/**
 * The stages a pipeline row can be in. Open stages are the board's columns; closed ones are collapsed.
 */
enum Stage: string
{
    case Applied = 'applied';
    case Screening = 'screening';
    case Interviewing = 'interviewing';
    case Offer = 'offer';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';
    case Closed = 'closed';

    public function isOpen(): bool
    {
        return in_array($this, [self::Applied, self::Screening, self::Interviewing, self::Offer], true);
    }
}
