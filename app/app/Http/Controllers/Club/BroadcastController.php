<?php

namespace App\Http\Controllers\Club;

use App\Http\Controllers\Controller;
use App\Models\Broadcast;
use App\Models\Club;
use App\Services\Club\BroadcastService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Pengumuman UKM: kemahasiswaan direktorat writes to many organizations at once; members read it in their space,
 * on the dashboard, and on its own page.
 */
class BroadcastController extends Controller
{
    public function __construct(private BroadcastService $broadcasts) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()->isStudentAffairs(), 403);

        return view('manage.broadcasts', [
            'broadcasts' => Broadcast::with('author')->withCount('clubs')->latest('id')->paginate(20),
            'clubs' => Club::where('active', true)->ordered()->get()->groupBy('category'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => 'required|string|max:150',
            'body' => 'required|string|max:5000',
            'audience' => ['required', Rule::in(array_keys(Broadcast::AUDIENCES))],
            'clubs' => 'nullable|array',
            'clubs.*' => 'integer',
            'expires_at' => 'nullable|date',
            'is_public' => 'nullable|boolean',
        ]);
        $broadcast = $this->broadcasts->publish($request->user(), $data);

        return back()->with('success', 'Pengumuman terbit untuk '.$broadcast->clubs()->count().' organisasi dan anggotanya diberi tahu.');
    }

    public function destroy(Request $request, Broadcast $broadcast): RedirectResponse
    {
        $this->broadcasts->delete($request->user(), $broadcast);

        return back()->with('success', 'Pengumuman dihapus.');
    }

    public function show(Request $request, Broadcast $broadcast): View
    {
        abort_unless($this->broadcasts->canRead($request->user(), $broadcast), 403);

        return view('broadcasts.show', ['broadcast' => $broadcast->load('author', 'clubs')]);
    }
}
