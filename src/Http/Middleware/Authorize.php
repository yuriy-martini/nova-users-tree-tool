<?php

namespace SoluzioneSoftware\Nova\Tools\UsersTree\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use SoluzioneSoftware\Nova\Tools\UsersTree\UsersTree;

class Authorize
{
    /**
     * Handle the incoming request.
     *
     * @param  Request  $request
     * @param  Closure  $next
     * @return Response
     */
    public function handle($request, $next)
    {
        return resolve(UsersTree::class)->authorize($request) ? $next($request) : abort(403);
    }
}
