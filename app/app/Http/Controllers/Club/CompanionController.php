<?php

namespace App\Http\Controllers\Club;

use App\Http\Controllers\Controller;
use App\Models\ClubActivity;
use App\Models\Companion;
use App\Models\Competition;
use App\Services\Club\CompanionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CompanionController extends Controller
{
    public function __construct(private CompanionService $companions) {}

    public function storeForActivity(Request $request, ClubActivity $activity): RedirectResponse
    {
        return $this->store($request, $activity);
    }

    public function storeForCompetition(Request $request, Competition $competition): RedirectResponse
    {
        return $this->store($request, $competition);
    }

    public function destroy(Request $request, Companion $companion): RedirectResponse
    {
        $this->companions->remove($request->user(), $companion);

        return back()->with('success', 'Pendamping dihapus.');
    }

    private function store(Request $request, ClubActivity|Competition $subject): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'kind' => ['required', Rule::in(array_keys(Companion::KINDS))],
            'identifier' => 'nullable|string|max:40',
        ]);
        $this->companions->add($request->user(), $subject, $data);

        return back()->with('success', 'Pendamping ditambahkan.');
    }
}
