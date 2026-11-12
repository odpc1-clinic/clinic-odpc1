<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SetActingStage
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->filled('as')) {
            // as=registration|nurse|doctor|lab|pharmacy
            session(['acting_stage' => $request->get('as')]);
        }
        return $next($request);
    }
}
