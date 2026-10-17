<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class PrivateResponse
{
    public function handle(Request $request, Closure $next)
    {
        // Permission checks cache the user's active assignments; start every request fresh.
        $request->user()?->flushAssignments();
        if ($request->user() && ! $request->user()->active && ! $request->routeIs('logout')) {
            abort(403, 'Akun mahasiswa nonaktif. Hubungi kemahasiswaan.');
        }
        $response = $next($request);
        // Public banner media carry a version in the URL, so browsers may keep them; everything else is private.
        $publicMedia = $request->is('banner/*', 'slide/*') && $response->isSuccessful();
        $response->headers->set('Cache-Control', $publicMedia ? 'public, max-age=86400' : 'private, no-store, max-age=0');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'same-origin');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');

        return $response;
    }
}
