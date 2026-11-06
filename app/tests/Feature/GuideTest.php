<?php

namespace Tests\Feature;

/**
 * The "Cara Penggunaan" page shows the steps for the roles the signed-in user holds.
 */
class GuideTest extends DemoTestCase
{
    public function test_the_guide_is_in_the_account_menu_and_needs_a_sign_in(): void
    {
        $this->get('/panduan')->assertRedirect('/login');
        $this->actingAs($this->user('mahasiswa'))->get('/')->assertSee('href="/panduan"', false)->assertSee('Cara Penggunaan');
    }

    public function test_each_role_sees_its_own_steps(): void
    {
        $this->actingAs($this->user('mahasiswa'))->get('/panduan')->assertOk()->assertSee('Mendaftar lomba')->assertDontSee('Tugas kemahasiswaan direktorat')->assertDontSee('Mengajukan proposal dan LPJ');
        $this->actingAs($this->user('pengurus'))->get('/panduan')->assertOk()->assertSee('Mengajukan proposal dan LPJ')->assertSee('Membuka rekrutmen pengurus baru');
        $this->actingAs($this->user('juri'))->get('/panduan')->assertOk()->assertSee('Menilai sebagai juri')->assertDontSee('Mendaftar lomba');
        $this->actingAs($this->user('admin'))->get('/panduan')->assertOk()->assertSee('Tugas kemahasiswaan direktorat')->assertSee('Mendaftar lomba');
    }
}
