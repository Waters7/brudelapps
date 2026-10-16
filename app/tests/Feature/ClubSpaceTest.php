<?php

namespace Tests\Feature;

use App\Livewire\ClubChat;
use App\Models\ActivityAttendance;
use App\Models\Club;
use App\Models\ClubActivity;
use App\Models\ClubMessage;
use App\Models\ClubQuest;
use App\Models\QuestSubmission;
use Livewire\Livewire;

class ClubSpaceTest extends DemoTestCase
{
    private function club(): Club
    {
        return Club::where('name', 'UKM Olahraga')->firstOrFail();
    }

    public function test_only_members_and_officers_enter_the_space(): void
    {
        $club = $this->club();
        $this->actingAs($this->user('atlet1'))->get('/ruang/'.$club->id)->assertOk()->assertSee('Ruang')->assertSee('Obrolan')->assertSee('Quest');
        $this->get('/ukm-saya')->assertSee('/ruang/'.$club->id, false);
        $this->actingAs($this->user('pembina'))->get('/ruang/'.$club->id)->assertOk()->assertSee('Buat pengumuman');
        $this->actingAs($this->user('anggota'))->get('/ruang/'.$club->id)->assertForbidden();
        Livewire::actingAs($this->user('anggota'))->test(ClubChat::class, ['clubId' => $club->id])->assertForbidden();
    }

    public function test_announcements_and_live_chat(): void
    {
        $club = $this->club();
        $this->actingAs($this->user('atlet1'))->post('/ruang/'.$club->id.'/pengumuman', ['title' => 'Info', 'body' => 'Tidak boleh.'])->assertForbidden();
        $this->actingAs($this->user('pengurus'))->post('/ruang/'.$club->id.'/pengumuman', ['title' => 'Jadwal turnamen', 'body' => 'Turnamen internal hari Sabtu.', 'pinned' => 1])->assertSessionHasNoErrors();
        $this->assertTrue($this->user('atlet2')->notifications()->get()->contains(fn ($n) => str_contains($n->data['body'], 'Jadwal turnamen')));
        $this->actingAs($this->user('atlet2'))->get('/ruang/'.$club->id)->assertSee('Jadwal turnamen')->assertSee('Disematkan');

        Livewire::actingAs($this->user('atlet2'))->test(ClubChat::class, ['clubId' => $club->id])
            ->set('body', 'Siap ikut turnamen!')->call('send')->assertHasNoErrors()->assertSee('Siap ikut turnamen!');
        $message = ClubMessage::where('body', 'Siap ikut turnamen!')->firstOrFail();
        Livewire::actingAs($this->user('atlet3'))->test(ClubChat::class, ['clubId' => $club->id])->assertSee('Siap ikut turnamen!')->call('remove', $message->id)->assertForbidden();
        Livewire::actingAs($this->user('pengurus'))->test(ClubChat::class, ['clubId' => $club->id])->call('remove', $message->id);
        $this->assertModelMissing($message);
    }

    public function test_quests_award_points_and_the_leaderboard_counts_attendance(): void
    {
        $club = $this->club();
        $this->actingAs($this->user('pengurus'))->post('/ruang/'.$club->id.'/quest', ['title' => 'Ajak satu teman latihan', 'description' => 'Bawa teman ke latihan terbuka.', 'points' => 45])->assertSessionHasNoErrors();
        $quest = ClubQuest::where('title', 'Ajak satu teman latihan')->firstOrFail();
        $this->actingAs($this->user('anggota'))->post('/ruang/quest/'.$quest->id.'/lapor', ['note' => 'Saya mengajak teman ke latihan.'])->assertSessionHasErrors('action');

        $athlete = $this->user('atlet1');
        $this->actingAs($athlete)->post('/ruang/quest/'.$quest->id.'/lapor', ['note' => 'Pendek'])->assertSessionHasErrors('action');
        $this->post('/ruang/quest/'.$quest->id.'/lapor', ['note' => 'Saya mengajak Kevin ikut latihan Rabu.'])->assertSessionHasNoErrors();
        $this->post('/ruang/quest/'.$quest->id.'/lapor', ['note' => 'Saya mengajak Kevin ikut latihan Rabu.'])->assertSessionHasErrors('action');
        $submission = QuestSubmission::where('user_id', $athlete->id)->firstOrFail();
        $this->post('/ruang/laporan-quest/'.$submission->id, ['decision' => 'approved'])->assertForbidden();

        $this->actingAs($this->user('pengurus'))->post('/ruang/laporan-quest/'.$submission->id, ['decision' => 'rejected'])->assertSessionHasErrors('action');
        $this->post('/ruang/laporan-quest/'.$submission->id, ['decision' => 'approved'])->assertSessionHasNoErrors();
        ActivityAttendance::create(['club_activity_id' => ClubActivity::where('club_id', $club->id)->where('type', 'latihan')->value('id'), 'user_id' => $athlete->id, 'method' => 'code', 'checked_in_at' => now()]);

        $this->actingAs($athlete)->get('/ruang/'.$club->id.'?tab=peringkat')->assertOk()->assertSeeInOrder(['Rifky Lasut', 'Penggerak', '55']);
        $this->assertTrue($athlete->notifications()->get()->contains(fn ($n) => str_contains($n->data['title'], '+45 poin')));
        $this->actingAs($this->user('pengurus'))->post('/ruang/quest/'.$quest->id.'/tutup')->assertSessionHasNoErrors();
        $this->actingAs($this->user('atlet2'))->post('/ruang/quest/'.$quest->id.'/lapor', ['note' => 'Saya juga mengajak teman latihan.'])->assertSessionHasErrors('action');
    }
}
