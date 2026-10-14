<?php
namespace App\Http\Controllers;
use App\Models\{Registration,ClubApplication};
use App\Services\EnrollmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Storage};
class EnrollmentController extends Controller {
    public function __construct(private EnrollmentService $service) {}
    public function club(Request $q, int $id) {
        $d=$q->validate(['motivation'=>'required|string|min:20|max:2000']);
        $this->service->applyClub($q->user(),$id,$d['motivation']);
        return redirect('/pendaftaran')->with('success','Pendaftaran UKM berhasil dikirim. Pantau hasil peninjauan di sini.');
    }
    public function draft(Request $q, int $id) {
        $d=$q->validate(['motivation'=>'required|string|min:20|max:2000','team_name'=>'nullable|string|max:80']);
        $c=\App\Models\Competition::findOrFail($id);
        if ($c->format==='team') $q->validate(['team_name'=>'required|string|min:3|max:80']);
        $r=$this->service->draft($q->user(),$id,$d);
        return redirect('/pendaftaran#registration-'.$r->id)->with('success','Draf tersimpan. Lengkapi persyaratan, lalu kirim pendaftaran.');
    }
    public function invite(Request $q, int $id) {
        $d=$q->validate(['nim'=>'required|string|max:30']); $this->service->invite($q->user(),$id,$d['nim']);
        return back()->with('success','Undangan dikirim. Anggota dapat menyetujuinya melalui akun sendiri.');
    }
    public function respond(Request $q, int $id) {
        $q->validate(['decision'=>'required|in:accept,decline']); $this->service->respond($q->user(),$id,$q->decision==='accept');
        return back()->with('success','Tanggapan undangan tersimpan.');
    }
    public function submit(Request $q, int $id) {
        $this->service->submit($q->user(),$id); return back()->with('success','Pendaftaran dikirim. Susunan tim telah dikunci.');
    }
    public function review(Request $q, string $type, int $id) {
        $d=$q->validate(['decision'=>'required|in:accepted,rejected,revision,waitlisted','reason'=>'nullable|string|max:2000']);
        if ($type==='ukm') $this->service->reviewClub($q->user(),$id,$d['decision'],$d['reason']??null);
        else $this->service->reviewRegistration($q->user(),$id,$d['decision'],$d['reason']??null);
        return back()->with('success','Keputusan tersimpan dan tercatat pada riwayat audit.');
    }
    public function upload(Request $q, int $id) {
        $q->validate(['document'=>'required|file|mimes:pdf|extensions:pdf|max:5120']);
        $path=null; $disk=config('brudel.document_disk');
        try {
            DB::transaction(function () use ($q,$id,$disk,&$path) {
                $ref=Registration::findOrFail($id);
                \App\Models\Competition::whereKey($ref->competition_id)->lockForUpdate()->firstOrFail();
                $r=Registration::lockForUpdate()->findOrFail($id);
                abort_unless($r->user_id===$q->user()->id,403);
                $this->service->ensure(in_array($r->status,['draft','revision']), 'Dokumen pendaftaran sudah dikunci.');
                $path=$q->file('document')->store('registrations/'.$r->id,['disk'=>$disk,'visibility'=>'private']);
                $this->service->ensure((bool)$path,'Penyimpanan dokumen gagal.');
                $r->update(['document_path'=>$path,'document_disk'=>$disk]);
                $this->service->audit($q->user(),'document.uploaded',$r);
            },3);
        } catch (\Throwable $e) { if ($path) Storage::disk($disk)->delete($path); throw $e; }
        return back()->with('success','PDF tersimpan privat.');
    }
    public function document(Request $q, int $id) {
        $r=Registration::with('competition')->findOrFail($id);
        abort_unless($r->user_id===$q->user()->id || $q->user()->managesEvent($r->competition->event_id),403);
        abort_unless($r->document_path,404);
        return Storage::disk($r->document_disk)->download($r->document_path,'dokumen-pendaftaran.pdf',['Cache-Control'=>'private, no-store','X-Content-Type-Options'=>'nosniff']);
    }
    public function export(Request $q, string $type) {
        abort_unless($q->user()->isReviewer(),403);
        $query=$type==='ukm' ? ClubApplication::with('user','recruitment.club') : Registration::with('user','competition');
        if (!$q->user()->assigned('kemahasiswaan')) {
            $assign=$q->user()->assignments()->where('starts_at','<=',now())->where('ends_at','>',now());
            if ($type==='ukm') $query->whereHas('recruitment',fn($q)=>$q->whereIn('club_id',(clone $assign)->where('role','pengurus')->pluck('club_id')));
            else $query->whereHas('competition',fn($q)=>$q->whereIn('event_id',(clone $assign)->where('role','admin_event')->pluck('event_id')));
        }
        return response()->streamDownload(function () use ($query,$type) {
            $f=fopen('php://output','w'); fwrite($f,"\xEF\xBB\xBF"); fputcsv($f,['ID','Nama','Kegiatan','Status','Waktu WITA'],',','"','');
            foreach ($query->cursor() as $r) {
                $row=[$r->id,$r->user->name,$type==='ukm'?$r->recruitment->club->name:$r->competition->name,$r->status,$r->created_at->timezone('Asia/Makassar')->format('d/m/Y H:i')];
                fputcsv($f,array_map(fn($s)=>preg_match('/^[=+@\-\t\r\n]/',(string)$s)?"'".$s:$s,$row),',','"','');
            } fclose($f);
        },'brudel-'.$type.'.csv',['Content-Type'=>'text/csv; charset=UTF-8']);
    }
}
