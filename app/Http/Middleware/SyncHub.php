<?php

namespace App\Http\Middleware;

use App\Sync\Hub;
use App\Sync\HubSync;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Syncs the hub before every request reaches its controller, so a page never shows rows older
 * than the files. Unchanged files cost a stat each. With no hub there is nothing to write to, so
 * every API request is refused before a stale index row could be found.
 */
class SyncHub
{
    public function __construct(
        private readonly Hub $hub,
        private readonly HubSync $sync,
    ) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_if($request->is('api/*') && ! $this->hub->exists(), 409, "Hub not found: no projects.md in {$this->hub->path}.");
        $this->sync->run();

        return $next($request);
    }
}
