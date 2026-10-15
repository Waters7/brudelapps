<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Models\PaymentProof;
use App\Services\Payment\PaymentService;
use App\Services\PrivateFiles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentController extends Controller
{
    public function __construct(private PaymentService $payments) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user->managesAnyCompetition(), 403);
        $managed = $user->managedCompetitionIds();
        $scoped = fn ($q) => $managed === null ? $q : $q->whereHas('registration', fn ($r) => $r->whereIn('competition_id', $managed));

        return view('payments.index', [
            'pending' => $scoped(PaymentProof::with('registration.competition', 'registration.participants', 'uploader', 'account')->where('status', 'pending'))->oldest()->get(),
            'recent' => $scoped(PaymentProof::with('registration.competition', 'verifier'))->where('status', '!=', 'pending')->latest('verified_at')->limit(20)->get(),
        ]);
    }

    public function upload(Request $request, int $registration): RedirectResponse
    {
        $data = $request->validate([
            'proof' => PrivateFiles::RULES,
            'payment_account_id' => 'required|integer',
            'amount' => 'required|integer|min:1',
            'transferred_on' => 'required|date|before_or_equal:today',
        ]);
        $this->payments->upload($request->user(), $registration, $request->file('proof'), $data);

        return back()->with('success', 'Bukti transfer terkirim dan menunggu verifikasi petugas.');
    }

    public function verify(Request $request, PaymentProof $proof): RedirectResponse
    {
        $data = $request->validate(['decision' => 'required|in:accepted,rejected', 'reason' => 'nullable|string|max:1000']);
        $this->payments->verify($request->user(), $proof->id, $data['decision'], $data['reason'] ?? null);

        return back()->with('success', 'Verifikasi pembayaran tersimpan.');
    }

    public function download(Request $request, PaymentProof $proof, PrivateFiles $files): StreamedResponse
    {
        abort_unless($this->payments->canView($request->user(), $proof), 403);

        return $files->download($proof->path, $proof->disk, 'bukti-pembayaran-'.$proof->id.'.pdf');
    }
}
