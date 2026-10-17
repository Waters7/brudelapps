<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Until the account is complete (active email, active mobile number, initial password replaced),
 * every page leads to the completion form.
 */
class EnsureInitialPasswordChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->needsAccountSetup() && ! $request->is('sandi-baru', 'logout', 'livewire/*')) {
            return redirect('/sandi-baru');
        }

        return $next($request);
    }
}
