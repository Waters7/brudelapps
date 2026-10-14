<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
class PrivateResponse {
    public function handle(Request $request, Closure $next) {
        if ($request->user() && !$request->user()->active && !$request->routeIs('logout')) abort(403, 'Akun mahasiswa nonaktif. Hubungi kemahasiswaan.');
        $response = $next($request);
        $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'same-origin');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        return $response;
    }
}
