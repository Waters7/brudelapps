<?php

namespace Tests\Feature;

use App\Models\AwardResult;
use App\Models\LandingSlide;
use App\Models\User;

/**
 * Front-page slides made from a competition or a winner's award instead of an uploaded video or image.
 */
class BannerSourceTest extends DemoTestCase
{
    private function award(): AwardResult
    {
        return AwardResult::with('recipients.user')->where('status', 'verified')->where('title', 'Lomba Karya Tulis Ilmiah Kesehatan Provinsi · Demo')->firstOrFail();
    }

    private function addSlide(array $data): LandingSlide
    {
        $this->actingAs($this->user('admin'))->post('/kelola/banner-depan/dari-data', $data)->assertSessionHasNoErrors();

        return LandingSlide::latest('id')->firstOrFail();
    }

    public function test_a_competition_becomes_a_slide_without_uploaded_media(): void
    {
        $competition = $this->competition('Tenis Meja Tunggal');
        $this->actingAs($this->user('mahasiswa'))->post('/kelola/banner-depan/dari-data', ['source' => 'lomba', 'lomba' => $competition->id])->assertForbidden();

        $slide = $this->addSlide(['source' => 'lomba', 'lomba' => $competition->id]);
        $this->assertFalse($slide->hasMedia());
        $this->get('/kelola/banner-depan')->assertOk()->assertSee('Tenis Meja Tunggal');
        $this->post('/logout');
        $this->get('/')->assertOk()->assertSee('Tenis Meja Tunggal')->assertSee('/lomba/'.$competition->id, false)->assertDontSee('/slide/'.$slide->id, false);
        $this->get('/slide/'.$slide->id)->assertNotFound();
    }

    public function test_winners_are_named_only_with_their_publication_consent(): void
    {
        $award = $this->award();
        $winners = $award->recipients->pluck('user');
        User::whereIn('id', $winners->pluck('id'))->update(['publication_consent' => false]);
        $anonymous = $this->addSlide(['source' => 'penghargaan', 'penghargaan' => $award->id]);
        $this->assertStringContainsString('para pemenang', $anonymous->caption);
        $this->assertStringNotContainsString($winners->first()->name, $anonymous->caption);

        User::whereKey($winners->first()->id)->update(['publication_consent' => true]);
        $named = $this->addSlide(['source' => 'penghargaan', 'penghargaan' => $award->id]);
        $this->assertStringContainsString($winners->first()->name, $named->caption);
        $this->assertStringNotContainsString($winners->last()->name, $named->caption);
    }

    public function test_only_verified_awards_can_become_slides(): void
    {
        $pending = AwardResult::where('status', 'pending')->firstOrFail();
        $this->actingAs($this->user('admin'))->post('/kelola/banner-depan/dari-data', ['source' => 'penghargaan', 'penghargaan' => $pending->id])->assertNotFound();
    }
}
