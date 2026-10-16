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
            ->call('toggle')->assertSee('Laporkan kendala atau usulkan fitur')
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
}
