<?php
namespace App\Services;

use App\Models\{User,Recruitment,ClubApplication,Membership,Competition,Registration,Participant};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EnrollmentService {
    public function ensure(bool $condition, string $message): void {
        if (!$condition) throw ValidationException::withMessages(['action' => $message]);
    }
    public function audit(User $actor, string $action, $subject, array $details = []): void {
        DB::table('audit_logs')->insert(['user_id'=>$actor->id,'action'=>$action,'subject_type'=>$subject->getTable(),'subject_id'=>$subject->id,'details'=>json_encode($details),'created_at'=>now()]);
    }
    private function student(User $user): void {
        $this->ensure($user->active && $user->nim && $user->email_verified_at, 'Akun mahasiswa harus aktif dan sudah diaktivasi.');
    }
    public function applyClub(User $user, int $id, string $motivation): ClubApplication {
        return DB::transaction(function () use ($user,$id,$motivation) {
            $this->student($user);
            $r = Recruitment::lockForUpdate()->findOrFail($id);
            User::whereKey($user->id)->lockForUpdate()->first();
            $this->ensure(now()->between($r->opens_at,$r->closes_at), 'Periode pendaftaran UKM telah ditutup atau belum dimulai.');
            $members = Membership::where('user_id',$user->id)->where('status','active');
            $this->ensure(!(clone $members)->where('club_id',$r->club_id)->exists(), 'Anda sudah menjadi anggota aktif UKM ini.');
            $this->ensure(config('brudel.multiple_clubs') || !$members->exists(), 'Kebijakan kampus hanya mengizinkan satu UKM aktif.');
            $a = ClubApplication::where('recruitment_id',$id)->where('user_id',$user->id)->first();
            $this->ensure(!$a || $a->status === 'revision', 'Anda sudah mengajukan pendaftaran pada periode ini.');
            $a = ClubApplication::updateOrCreate(['recruitment_id'=>$id,'user_id'=>$user->id],['motivation'=>$motivation,'status'=>'submitted','reason'=>null]);
            $this->audit($user,'club.submitted',$a);
            return $a;
        }, 3);
    }
    public function reviewClub(User $actor, int $id, string $decision, ?string $reason): void {
        DB::transaction(function () use ($actor,$id,$decision,$reason) {
            $ref = ClubApplication::findOrFail($id);
            $r = Recruitment::lockForUpdate()->findOrFail($ref->recruitment_id);
            $a = ClubApplication::lockForUpdate()->findOrFail($id);
            abort_unless($actor->active && $actor->managesClub($r->club_id),403);
            abort_if($a->user_id === $actor->id,403,'Tidak dapat meninjau pengajuan sendiri.');
            $this->ensure(in_array($decision,['accepted','rejected','revision','waitlisted']), 'Keputusan tidak valid.');
            $this->ensure(in_array($a->status,['submitted','waitlisted']), 'Pengajuan ini sudah ditinjau.');
            $this->ensure($decision === 'accepted' || mb_strlen(trim($reason ?? '')) >= 5, 'Alasan keputusan minimal 5 karakter.');
            if ($decision === 'accepted') {
                $student = User::whereKey($a->user_id)->lockForUpdate()->firstOrFail();
                $this->student($student);
                $this->ensure($r->applications()->where('status','accepted')->count() < $r->quota, 'Kuota UKM sudah penuh.');
                $members = Membership::where('user_id',$a->user_id)->where('status','active');
                $this->ensure(!(clone $members)->where('club_id',$r->club_id)->exists(), 'Mahasiswa sudah menjadi anggota aktif.');
                $this->ensure(config('brudel.multiple_clubs') || !$members->exists(), 'Mahasiswa sudah memiliki UKM aktif.');
                Membership::updateOrCreate(['club_id'=>$r->club_id,'user_id'=>$a->user_id],['club_application_id'=>$a->id,'status'=>'active','joined_at'=>now()]);
            }
            $a->update(['status'=>$decision,'reason'=>$reason]);
            $this->audit($actor,'club.'.$decision,$a,['reason'=>$reason]);
        },3);
    }
    public function draft(User $user, int $id, array $data): Registration {
        return DB::transaction(function () use ($user,$id,$data) {
            $this->student($user);
            $c = Competition::lockForUpdate()->findOrFail($id);
            $this->ensure($c->event->status === 'approved' && now()->between($c->opens_at,$c->closes_at), 'Pendaftaran lomba tidak dibuka.');
            $this->ensure(!$c->requires_program_approval && $c->fee === 0, 'Lomba dengan persetujuan prodi atau pembayaran belum dibuka pada tahap ini.');
            $r = Registration::where('competition_id',$id)->where('user_id',$user->id)->first();
            $this->ensure(!$r || in_array($r->status,['draft','revision']), 'Pendaftaran sudah terkunci.');
            $this->ensure(!Participant::where('competition_id',$id)->where('user_id',$user->id)->when($r,fn($q)=>$q->where('registration_id','!=',$r->id))->exists(), 'Anda sudah terdaftar atau diundang dalam tim lain pada nomor ini.');
            $r = Registration::updateOrCreate(['competition_id'=>$id,'user_id'=>$user->id],['team_name'=>$c->format === 'team' ? $data['team_name'] : null,'motivation'=>$data['motivation']]);
            Participant::firstOrCreate(['registration_id'=>$r->id,'competition_id'=>$id,'user_id'=>$user->id],['status'=>'confirmed']);
            $this->audit($user,'registration.draft',$r);
            return $r;
        },3);
    }
    public function invite(User $actor, int $id, string $nim): void {
        DB::transaction(function () use ($actor,$id,$nim) {
            $ref = Registration::findOrFail($id);
            $c = Competition::lockForUpdate()->findOrFail($ref->competition_id);
            $r = Registration::lockForUpdate()->findOrFail($id);
            abort_unless($actor->active && $r->user_id === $actor->id,403);
            $this->ensure(in_array($r->status,['draft','revision']) && $c->format === 'team', 'Susunan tim terkunci.');
            $this->ensure(now()->between($c->opens_at,$c->closes_at), 'Periode pendaftaran tidak dibuka.');
            $member = User::where('nim',$nim)->where('active',true)->whereNotNull('email_verified_at')->first();
            $this->ensure((bool)$member, 'NIM tidak tersedia sebagai anggota aktif.');
            $this->ensure($r->participants()->count() < $c->max_members, 'Jumlah maksimum anggota tercapai.');
            $this->ensure(!Participant::where('competition_id',$c->id)->where('user_id',$member->id)->exists(), 'Mahasiswa sudah terdaftar atau mendapat undangan pada nomor ini.');
            $p = Participant::create(['registration_id'=>$r->id,'competition_id'=>$c->id,'user_id'=>$member->id]);
            $this->audit($actor,'team.invited',$p);
        },3);
    }
    public function respond(User $actor, int $id, bool $accept): void {
        DB::transaction(function () use ($actor,$id,$accept) {
            $ref = Participant::findOrFail($id);
            Competition::whereKey($ref->competition_id)->lockForUpdate()->firstOrFail();
            $r = Registration::lockForUpdate()->findOrFail($ref->registration_id);
            $p = Participant::lockForUpdate()->findOrFail($id);
            abort_unless($p->user_id === $actor->id,403);
            $this->student($actor);
            $this->ensure(in_array($r->status,['draft','revision']) && $p->status === 'invited', 'Undangan sudah diproses atau tim terkunci.');
            $this->audit($actor,$accept ? 'team.confirmed' : 'team.declined',$p);
            $accept ? $p->update(['status'=>'confirmed']) : $p->delete();
        },3);
    }
    public function submit(User $actor, int $id): void {
        DB::transaction(function () use ($actor,$id) {
            $this->student($actor);
            $ref = Registration::findOrFail($id);
            $c = Competition::lockForUpdate()->findOrFail($ref->competition_id);
            $r = Registration::lockForUpdate()->findOrFail($id);
            abort_unless($r->user_id === $actor->id,403);
            $this->ensure(in_array($r->status,['draft','revision']), 'Pendaftaran sudah dikirim.');
            $this->ensure($c->event->status === 'approved' && now()->between($c->opens_at,$c->closes_at), 'Periode pendaftaran lomba ditutup.');
            $this->ensure(!$c->requires_program_approval && $c->fee === 0, 'Alur pembayaran / persetujuan prodi belum tersedia.');
            $members = $r->participants()->with('user')->get();
            $this->ensure($members->count() >= $c->min_members && $members->count() <= $c->max_members, 'Jumlah anggota belum sesuai persyaratan.');
            foreach ($members as $p) { $this->student($p->user); $this->ensure($p->status === 'confirmed', 'Semua anggota wajib mengonfirmasi undangan.'); }
            $this->ensure(!$c->requires_document || (bool)$r->document_path, 'Dokumen wajib belum diunggah.');
            $this->ensure($c->registrations()->whereIn('status',['submitted','accepted'])->count() < $c->quota, 'Kuota lomba sudah penuh.');
            $r->update(['status'=>'submitted','reason'=>null,'fee_snapshot'=>$c->fee * ($c->fee_basis === 'participant' ? $members->count() : 1),'submitted_at'=>now()]);
            $this->audit($actor,'registration.submitted',$r);
        },3);
    }
    public function reviewRegistration(User $actor, int $id, string $decision, ?string $reason): void {
        DB::transaction(function () use ($actor,$id,$decision,$reason) {
            $ref = Registration::findOrFail($id);
            $c = Competition::lockForUpdate()->findOrFail($ref->competition_id);
            $r = Registration::lockForUpdate()->findOrFail($id);
            abort_unless($actor->active && $actor->managesEvent($c->event_id),403);
            abort_if($r->participants()->where('user_id',$actor->id)->exists(),403,'Tidak dapat meninjau diri atau tim sendiri.');
            $this->ensure($r->status === 'submitted', 'Pendaftaran sudah ditinjau.');
            $this->ensure(in_array($decision,['accepted','rejected','revision']), 'Keputusan tidak valid.');
            $this->ensure($decision === 'accepted' || mb_strlen(trim($reason ?? '')) >= 5, 'Alasan keputusan minimal 5 karakter.');
            if ($decision === 'accepted') foreach ($r->participants()->with('user')->get() as $p) $this->student($p->user);
            $r->update(['status'=>$decision,'reason'=>$reason]);
            $this->audit($actor,'registration.'.$decision,$r,['reason'=>$reason]);
        },3);
    }
}
