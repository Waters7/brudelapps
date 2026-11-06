<?php

namespace App\Http\Controllers\Campus;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * "Cara Penggunaan": short how-to steps for the roles the signed-in user holds.
 */
class GuideController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $roles = $user->activeRoles()->when($user->nim, fn ($held) => $held->push('mahasiswa'));

        return view('guide', [
            'sections' => collect(config('guide.sections'))->filter(fn (array $section) => $section['roles'] === []
                || $user->isStudentAffairs() || $roles->intersect($section['roles'])->isNotEmpty()),
        ]);
    }
}
