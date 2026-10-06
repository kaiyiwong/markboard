<?php

namespace App\Markdown;

use RuntimeException;

/**
 * The edit can't be made to this task as it stands; the message says why, for the user.
 */
final class EditRefused extends RuntimeException {}
