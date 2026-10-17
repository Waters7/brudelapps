<?php

namespace Tests\Feature;

use App\Livewire\SupportChat;
use App\Models\SupportThread;
use App\Services\Support\SupportService;
use Livewire\Livewire;

class SupportChatTest extends DemoTestCase
{
    public function test_users_report_to_direktorat_and_see_replies_live(): void
    {
        $student = $this->user('mahasiswa');
        $admin = $this->user('admin');
        $support = app(SupportService::class);

        $this->actingAs($student)->get('/')->assertSee('support-fab', false)->assertSee('/bantuan', false);
        Livewire::actingAs($student)->test(SupportChat::class)
            ->call('toggle')->assertSee('Hubungi kemahasiswaan')
            ->call('compose')->set('category', 'kendala')->set('subject', 'Bukti bayar gagal diunggah')->set('body', 'Muncul pesan error saat unggah PDF 2 MB.')
            ->call('start')->assertHasNoErrors()->assertSee('Muncul pesan error saat unggah PDF 2 MB.');
        $thread = SupportThread::where('user_id', $student->id)->firstOrFail();
        $this->assertSame(1, $support->unreadCount($admin));
        $this->assertTrue($admin->notifications()->get()->contains(fn ($n) => str_contains($n->data['body'], 'Bukti bayar gagal diunggah')));

        Livewire::actingAs($admin)->test(SupportChat::class, ['page' => true])
            ->assertSee('Bukti bayar gagal diunggah')->assertSee($student->name)
            ->call('select', $thread->id)->set('body', 'Coba kompres PDF di bawah 5 MB; kami cek servernya.')->call('send')->assertHasNoErrors()
            ->call('setStatus', 'closed');
        $this->assertSame(0, $support->unreadCount($admin));
        $this->assertSame(1, $support->unreadCount($student));
        $this->assertSame('closed', $thread->fresh()->status);
        $this->assertTrue($thread->messages()->where('from_admin', true)->exists());

        Livewire::actingAs($student)->test(SupportChat::class)->call('toggle')->call('select', $thread->id)
            ->assertSee('Coba kompres PDF')->set('body', 'Sudah berhasil, terima kasih.')->call('send');
        $this->assertSame('open', $thread->fresh()->status, 'Balasan pengguna membuka kembali percakapan.');
        $this->assertSame(0, $support->unreadCount($student));

        Livewire::actingAs($this->user('anggota'))->test(SupportChat::class)->call('toggle')->call('select', $thread->id)->assertForbidden();
        $this->actingAs($this->user('anggota'))->get('/bantuan')->assertOk()->assertDontSee('Bukti bayar gagal diunggah');
    }

    public function test_students_write_to_the_kemahasiswaan_of_their_jurusan(): void
    {
        $student = $this->user('mahasiswa');
        $jurusan = $this->user('jurusan');
        $support = app(SupportService::class);

        Livewire::actingAs($student)->test(SupportChat::class)->call('toggle')->call('compose')
            ->assertSee('Kemahasiswaan Jurusan Keperawatan')
            ->set('recipient', 'jurusan')->set('subject', 'Jadwal praktik bentrok lomba')->set('body', 'Praktik klinik bertepatan dengan final lomba poster.')
            ->call('start')->assertHasNoErrors();
        $thread = SupportThread::where('subject', 'Jadwal praktik bentrok lomba')->firstOrFail();
        $this->assertSame($student->program->department_id, $thread->department_id);
        $this->assertTrue($jurusan->notifications()->get()->contains(fn ($n) => str_contains($n->data['body'], 'Jadwal praktik bentrok lomba')));
        $this->assertSame(1, $support->unreadCount($jurusan));
        $this->assertSame(1, $support->unreadCount($this->user('admin')), 'Direktorat tetap melihat semua percakapan.');

        Livewire::actingAs($jurusan)->test(SupportChat::class, ['page' => true])->assertSee('Jadwal praktik bentrok lomba')
            ->call('select', $thread->id)->set('body', 'Kami koordinasikan dengan pembimbing klinik.')->call('send')->assertHasNoErrors()
            ->call('setStatus', 'closed');
        $this->assertSame('closed', $thread->fresh()->status);
        $this->assertTrue($thread->messages()->where('from_admin', true)->where('user_id', $jurusan->id)->exists());
        $this->assertTrue($student->notifications()->get()->contains(fn ($n) => $n->data['title'] === 'Balasan Kemahasiswaan Jurusan Keperawatan'));
        $this->actingAs($jurusan)->get('/')->assertSee('Kotak Bantuan');

        // A jurusan without its own kemahasiswaan yet: direktorat is told, and other jurusan cannot read it.
        $other = $this->user('anggota');
        $otherThread = $support->start($other, 'kendala', 'Tanya rekrutmen', 'Kapan rekrutmen UKM dibuka lagi?', 'jurusan');
        $this->assertNotSame($thread->department_id, $otherThread->department_id);
        $this->assertTrue($this->user('admin')->notifications()->get()->contains(fn ($n) => str_contains($n->data['body'], 'Tanya rekrutmen')));
        Livewire::actingAs($jurusan)->test(SupportChat::class)->call('toggle')->call('select', $otherThread->id)->assertForbidden();

        Livewire::actingAs($this->user('pembina'))->test(SupportChat::class)->call('toggle')->call('compose')
            ->assertDontSee('Kemahasiswaan Jurusan')->set('recipient', 'jurusan')->set('subject', 'Uji tujuan')->set('body', 'Akun pegawai tanpa jurusan.')
            ->call('start')->assertHasErrors('recipient');
    }
}
