<?php
namespace App\Http\Controllers;
use App\Models\{Club,Competition,ClubApplication,Membership,Registration,Participant};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class PortalController extends Controller {
    public function dashboard(Request $q) {
        $uid=$q->user()->id;
        return view('dashboard',[
            'clubs'=>Club::withCount(['memberships'=>fn($q)=>$q->where('status','active')])->limit(3)->get(),
            'competitions'=>Competition::with('event')->whereHas('event',fn($q)=>$q->where('status','approved'))->where('closes_at','>',now())->orderBy('closes_at')->limit(3)->get(),
            'memberCount'=>Membership::where('user_id',$uid)->where('status','active')->count(),
            'applicationCount'=>ClubApplication::where('user_id',$uid)->count()+Registration::whereHas('participants',fn($q)=>$q->where('user_id',$uid))->count(),
            'invitationCount'=>Participant::where('user_id',$uid)->where('status','invited')->count(),
            'openCount'=>Competition::whereHas('event',fn($q)=>$q->where('status','approved'))->where('opens_at','<=',now())->where('closes_at','>',now())->count(),
            'recent'=>ClubApplication::with('recruitment.club')->where('user_id',$uid)->latest('updated_at')->limit(3)->get(),
        ]);
    }
    public function club(int $id) { return view('club',['club'=>Club::with(['recruitments'=>fn($q)=>$q->latest(),'memberships'])->findOrFail($id)]); }
    public function competition(int $id) { return view('competition',['competition'=>Competition::with('event')->whereHas('event',fn($q)=>$q->where('status','approved'))->findOrFail($id),'draft'=>Registration::where('competition_id',$id)->where('user_id',auth()->id())->first()]); }
    public function applications(Request $q) {
        $uid=$q->user()->id;
        return view('applications',[
            'applications'=>ClubApplication::with('recruitment.club')->where('user_id',$uid)->latest()->get(),
            'registrations'=>Registration::with('competition','participants.user')->whereHas('participants',fn($q)=>$q->where('user_id',$uid))->latest()->get(),
            'invitations'=>Participant::with('registration.competition','registration.user')->where('user_id',$uid)->where('status','invited')->get(),
        ]);
    }
    public function memberships(Request $q) { return view('memberships',['memberships'=>Membership::with('club')->where('user_id',$q->user()->id)->get()]); }
    public function review(Request $q) {
        $u=$q->user(); abort_unless($u->isReviewer(),403);
        $clubs=ClubApplication::with('user','recruitment.club')->whereIn('status',['submitted','waitlisted']);
        $regs=Registration::with('user','competition','participants.user')->where('status','submitted');
        if (!$u->assigned('kemahasiswaan')) {
            $a=$u->assignments()->where('starts_at','<=',now())->where('ends_at','>',now());
            $clubs->whereHas('recruitment',fn($q)=>$q->whereIn('club_id',(clone $a)->where('role','pengurus')->pluck('club_id')));
            $regs->whereHas('competition',fn($q)=>$q->whereIn('event_id',(clone $a)->where('role','admin_event')->pluck('event_id')));
        }
        return view('review',['applications'=>$clubs->oldest()->get(),'registrations'=>$regs->oldest()->get()]);
    }
    public function profile(Request $q) { return view('profile',['program'=>DB::table('programs')->find($q->user()->program_id)]); }
    public function saveProfile(Request $q) {
        $q->validate(['publication_consent'=>'nullable|boolean']);
        DB::transaction(function () use ($q) {
            $q->user()->update(['publication_consent'=>$q->boolean('publication_consent')]);
            app(\App\Services\EnrollmentService::class)->audit($q->user(),'profile.consent',$q->user(),['consent'=>$q->boolean('publication_consent')]);
        });
        return back()->with('success','Preferensi publikasi tersimpan.');
    }
}
