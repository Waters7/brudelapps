<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A student who signed in with the birth-date password from the SIAKAD import must set a new password first.
 */
class EnsureInitialPasswordChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->must_change_password && ! $request->is('sandi-baru', 'logout', 'livewire/*')) {
            return redirect('/sandi-baru');
        }

        return $next($request);
    }
}
