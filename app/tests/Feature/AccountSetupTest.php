<?php

namespace Tests\Feature;

use App\Models\CompetitionAccount;
use App\Models\User;

/**
 * Every account confirms an active email and mobile number before using BRUDEL, and can change them later
 * from the account menu at the top right.
 */
class AccountSetupTest extends DemoTestCase
{
    public function test_accounts_without_a_mobile_number_complete_it_before_any_page(): void
    {
        $student = $this->user('atlet1');
        $student->forceFill(['phone' => null])->save();

        $this->actingAs($student)->get('/ukm')->assertRedirect('/sandi-baru');
        $this->get('/sandi-baru')->assertOk()->assertSee($student->email)->assertDontSee('Kata sandi baru');
        $this->post('/sandi-baru', ['email' => $student->email, 'phone' => '6281299990001'])->assertRedirect('/');
        $this->assertSame('081299990001', $student->fresh()->phone);
        $this->get('/ukm')->assertOk();
        $this->get('/sandi-baru')->assertRedirect('/');
    }

    public function test_competition_accounts_are_managed_by_the_proposer_and_skip_completion(): void
    {
        $account = User::factory()->create(['phone' => null, 'email' => 'lomba-uji@akun-lomba.brudel.invalid']);
        CompetitionAccount::create(['competition_id' => $this->competition('Lomba Poster Kesehatan')->id, 'user_id' => $account->id, 'role' => 'penilai', 'secret' => 'uji']);

        $this->assertFalse($account->needsAccountSetup());
        $this->assertNotSame(url('/sandi-baru'), $this->actingAs($account)->get('/profil')->headers->get('Location'));
    }

    public function test_the_account_menu_and_profile_update_email_and_mobile_number(): void
    {
        $student = $this->user('mahasiswa');
        $this->actingAs($student)->get('/')->assertOk()
            ->assertSee('aria-label="Menu akun '.$student->name.'"', false)
            ->assertSee('href="/profil#kontak"', false)->assertSee('href="/profil#ubah-sandi"', false)->assertSee('action="/logout"', false);
        $this->get('/profil')->assertOk()->assertSee('Email & nomor HP', false)->assertSee($student->phone);

        $this->post('/profil/kontak', ['email' => $this->user('anggota')->email, 'phone' => '081211112222'])->assertSessionHasErrors('email');
        $this->post('/profil/kontak', ['email' => 'bintang@contoh.test', 'phone' => '0812'])->assertSessionHasErrors('phone');
        $this->post('/profil/kontak', ['email' => 'Bintang@Contoh.test', 'phone' => '0812-1111-2222'])->assertSessionHasNoErrors();
        $this->assertSame(['bintang@contoh.test', '081211112222'], [$student->fresh()->email, $student->fresh()->phone]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'account.contact_updated', 'subject_id' => $student->id]);
    }

    public function test_guests_find_the_sign_in_button_at_the_top_right(): void
    {
        $this->get('/')->assertOk()->assertSee('aria-label="Masuk ke BRUDEL"', false)->assertSee('href="/login"', false);
    }
}
