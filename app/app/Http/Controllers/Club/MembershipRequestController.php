<?php

namespace App\Http\Controllers\Club;

use App\Http\Controllers\Controller;
use App\Models\Club;
use App\Models\MembershipRequest;
use App\Services\Club\MembershipRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MembershipRequestController extends Controller
{
    public function __construct(private MembershipRequestService $requests) {}

    public function store(Request $request, Club $club): RedirectResponse
    {
        $data = $request->validate(['message' => 'nullable|string|max:500']);
        $this->requests->request($request->user(), $club, $data['message'] ?? null);

        return back()->with('success', 'Permintaan bergabung terkirim. Pengurus akan meninjaunya.');
    }

    public function invite(Request $request, Club $club): RedirectResponse
    {
        $data = $request->validate(['user' => 'required|string|max:190', 'message' => 'nullable|string|max:500']);
        $invitation = $this->requests->invite($request->user(), $club, $data['user'], $data['message'] ?? null);

        return back()->with('success', 'Undangan terkirim ke '.$invitation->user->name.'. Mahasiswa menjadi anggota setelah menerimanya.');
    }

    public function add(Request $request, Club $club): RedirectResponse
    {
        $data = $request->validate(['user' => 'required|string|max:190', 'division' => 'nullable|string|max:80']);
        $membership = $this->requests->add($request->user(), $club, $data['user'], $data['division'] ?? null);

        return back()->with('success', $membership->user->name.' menjadi anggota '.$club->name.'.');
    }

    public function respond(Request $request, MembershipRequest $invitation): RedirectResponse
    {
        $accept = $request->validate(['decision' => 'required|in:accepted,declined'])['decision'] === 'accepted';
        $this->requests->respond($request->user(), $invitation, $accept);

        return back()->with('success', $accept ? 'Selamat bergabung di '.$invitation->club->name.'!' : 'Undangan ditolak.');
    }

    public function decide(Request $request, MembershipRequest $joinRequest): RedirectResponse
    {
        $accept = $request->validate(['decision' => 'required|in:accepted,declined'])['decision'] === 'accepted';
        $this->requests->decide($request->user(), $joinRequest, $accept);

        return back()->with('success', $accept ? $joinRequest->user->name.' menjadi anggota.' : 'Permintaan ditolak.');
    }

    public function cancel(Request $request, MembershipRequest $item): RedirectResponse
    {
        $this->requests->cancel($request->user(), $item);

        return back()->with('success', 'Dibatalkan.');
    }
}
