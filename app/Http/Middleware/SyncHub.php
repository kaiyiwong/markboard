<?php

namespace App\Http\Middleware;

use App\Sync\HubSync;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Syncs the hub before every request reaches its controller, so a page never shows rows older
 * than the files. Unchanged files cost a stat each.
 */
class SyncHub
{
    public function __construct(
        private readonly HubSync $sync,
    ) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $this->sync->run();

        return $next($request);
    }
}
