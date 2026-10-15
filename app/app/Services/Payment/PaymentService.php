<?php

namespace App\Services\Payment;

use App\Models\Competition;
use App\Models\PaymentAccount;
use App\Models\PaymentProof;
use App\Models\Registration;
use App\Models\User;
use App\Notifications\BrudelNotice;
use App\Services\DomainService;
use App\Services\PrivateFiles;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Payment module (MVP): bank transfer proof and manual verification against the fee snapshot.
 * No payment gateway, no generated accounts; only official accounts configured by kemahasiswaan are offered.
 */
class PaymentService extends DomainService
{
    public function __construct(private PrivateFiles $files) {}

    /**
     * @param  array{payment_account_id: int, amount: int, transferred_on: string}  $data
     */
    public function upload(User $actor, int $registrationId, UploadedFile $file, array $data): PaymentProof
    {
        $stored = $this->files->storePdf($file, 'payments/'.$registrationId);

        try {
            return DB::transaction(function () use ($actor, $registrationId, $data, $stored) {
                $reference = Registration::findOrFail($registrationId);
                Competition::whereKey($reference->competition_id)->lockForUpdate()->firstOrFail();
                $registration = Registration::lockForUpdate()->findOrFail($registrationId);
                abort_unless($registration->user_id === $actor->id, 403);
                $this->ensure(in_array($registration->status, Registration::HOLDING, true), 'Kirim pendaftaran terlebih dahulu sebelum mengunggah bukti pembayaran.');
                $this->ensure(in_array($registration->payment_status, ['unpaid', 'rejected'], true), 'Pembayaran sedang diverifikasi atau sudah diterima.');
                $account = PaymentAccount::where('active', true)->find($data['payment_account_id']);
                $this->ensure($account !== null, 'Pilih rekening resmi yang aktif.');
                $this->ensure((int) $data['amount'] === $registration->fee_snapshot, 'Nominal transfer harus sama dengan tagihan Rp '.number_format($registration->fee_snapshot, 0, ',', '.').'.');

                $proof = PaymentProof::create([
                    'registration_id' => $registration->id,
                    'uploaded_by' => $actor->id,
                    'payment_account_id' => $account->id,
                    'amount' => (int) $data['amount'],
                    'transferred_on' => $data['transferred_on'],
                    'path' => $stored[0],
                    'disk' => $stored[1],
                ]);
                $from = $registration->payment_status;
                $registration->update(['payment_status' => 'pending']);
                $this->history($registration, 'payment:'.$from, 'payment:pending', $actor);
                $this->audit($actor, 'payment.uploaded', $proof, ['amount' => $proof->amount]);

                return $proof;
            }, 3);
        } catch (\Throwable $e) {
            $this->files->delete($stored[0], $stored[1]);
            throw $e;
        }
    }

    public function verify(User $actor, int $proofId, string $decision, ?string $reason): void
    {
        DB::transaction(function () use ($actor, $proofId, $decision, $reason) {
            $reference = PaymentProof::findOrFail($proofId);
            $registrationRef = Registration::findOrFail($reference->registration_id);
            $competition = Competition::lockForUpdate()->findOrFail($registrationRef->competition_id);
            $registration = Registration::lockForUpdate()->findOrFail($registrationRef->id);
            $proof = PaymentProof::lockForUpdate()->findOrFail($proofId);
            abort_unless($actor->active && $actor->managesCompetition($competition), 403);
            abort_if($proof->uploaded_by === $actor->id || $registration->participants()->where('user_id', $actor->id)->exists(), 403, 'Petugas tidak dapat memverifikasi pembayarannya sendiri.');
            $this->ensure(in_array($decision, ['accepted', 'rejected'], true), 'Keputusan tidak valid.');
            $this->ensure($proof->status === 'pending' && $registration->payment_status === 'pending', 'Bukti pembayaran sudah diverifikasi.');
            $this->requireReason($decision, $reason);

            $proof->update(['status' => $decision, 'reason' => $reason, 'verified_by' => $actor->id, 'verified_at' => now()]);
            $status = $decision === 'accepted' ? 'paid' : 'rejected';
            $registration->update(['payment_status' => $status]);
            $this->history($registration, 'payment:pending', 'payment:'.$status, $actor, $reason);
            $this->audit($actor, 'payment.'.$decision, $proof, ['reason' => $reason, 'amount' => $proof->amount]);
            $registration->user->notify(new BrudelNotice('Verifikasi pembayaran '.$competition->name,
                $decision === 'accepted' ? 'Pembayaran diterima.' : 'Bukti pembayaran ditolak: '.$reason.'. Unggah bukti yang benar.',
                '/pendaftaran#registration-'.$registration->id));
        }, 3);
    }

    public function canView(User $user, PaymentProof $proof): bool
    {
        $proof->loadMissing('registration.competition');

        return $proof->registration->user_id === $user->id || $user->managesCompetition($proof->registration->competition);
    }
}
