<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Club;
use App\Models\ClubSubmission;
use App\Models\Department;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Proposal & LPJ: officers submit, kemahasiswaan validates, and everyone follows the same timeline.
 */
class ClubSubmissionTest extends DemoTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function sports(): Club
    {
        return Club::where('name', 'UKM Olahraga')->firstOrFail();
    }

    private function jurusan(string $code = 'KEP'): Department
    {
        return Department::where('code', $code)->firstOrFail();
    }

    private function submit(string $target = 'direktorat', string $type = 'proposal'): ClubSubmission
    {
        $this->actingAs($this->user('pengurus'))->post('/proposal', [
            'club_id' => $this->sports()->id, 'type' => $type, 'title' => 'Proposal Turnamen Uji', 'summary' => 'Ringkasan uji.', 'target' => $target, 'document' => $this->pdf(),
        ])->assertSessionHasNoErrors();

        return ClubSubmission::latest('id')->firstOrFail();
    }

    public function test_officers_submit_every_document_type_and_the_reviewer_is_told(): void
    {
        $submission = $this->submit('jurusan:'.$this->jurusan()->id);

        $this->assertSame(['submitted', 'jurusan', 1], [$submission->status, $submission->target, $submission->version]);
        Storage::disk('local')->assertExists($submission->file_path);
        $this->assertTrue($this->user('jurusan')->notifications()->get()->contains(fn ($n) => str_contains($n->data['title'], 'Proposal kegiatan baru')));
        $this->get('/proposal/'.$submission->id)->assertOk()->assertSee('Kemahasiswaan Jurusan Keperawatan')->assertSee('Menunggu tinjauan');

        $this->assertSame('lpj_kegiatan', $this->submit(type: 'lpj_kegiatan')->type);
        $this->assertSame('lpj_kepengurusan', $this->submit(type: 'lpj_kepengurusan')->type);
        $this->assertTrue($this->user('admin')->notifications()->get()->contains(fn ($n) => str_contains($n->data['title'], 'LPJ kepengurusan baru')));
    }

    public function test_a_revision_goes_back_to_the_officer_and_returns_as_a_new_version(): void
    {
        $submission = $this->submit();

        $this->actingAs($this->user('admin'))->post('/proposal/'.$submission->id.'/tinjau', ['decision' => 'revision'])->assertSessionHasErrors('action');
        $this->post('/proposal/'.$submission->id.'/tinjau', ['decision' => 'revision', 'note' => 'Lengkapi rincian anggaran.'])->assertSessionHasNoErrors();
        $this->assertTrue($this->user('pengurus')->notifications()->get()->contains(fn ($n) => str_contains($n->data['title'], 'Perlu revisi')));

        $officer = $this->user('pengurus');
        $this->actingAs($officer)->get('/')->assertSee('Proposal &amp; LPJ perlu perbaikan', false);
        $this->get('/proposal/'.$submission->id)->assertOk()->assertSee('Lengkapi rincian anggaran.')->assertSee('Kirim perbaikan')->assertDontSee('Minta perbaikan');
        $this->post('/proposal/'.$submission->id.'/perbaiki', ['document' => $this->pdf('perbaikan.pdf')])->assertSessionHasNoErrors();

        $submission->refresh();
        $this->assertSame(['submitted', 2], [$submission->status, $submission->version]);
        $this->assertNull($submission->reviewed_by);

        $this->actingAs($this->user('admin'))->post('/proposal/'.$submission->id.'/tinjau', ['decision' => 'accepted'])->assertSessionHasNoErrors();
        $this->assertSame('accepted', $submission->fresh()->status);
        $this->assertSame(['submitted', 'revision', 'submitted', 'accepted'], $submission->fresh()->histories->pluck('to_status')->all());
        $this->post('/proposal/'.$submission->id.'/tinjau', ['decision' => 'rejected', 'note' => 'Terlambat dibatalkan'])->assertSessionHasErrors('action');
    }

    public function test_a_rejected_submission_is_closed_and_needs_a_reason(): void
    {
        $submission = $this->submit();
        $this->actingAs($this->user('admin'))->post('/proposal/'.$submission->id.'/tinjau', ['decision' => 'rejected', 'note' => 'ok'])->assertSessionHasErrors('action');
        $this->post('/proposal/'.$submission->id.'/tinjau', ['decision' => 'rejected', 'note' => 'Bertabrakan dengan ujian.'])->assertSessionHasNoErrors();

        $this->actingAs($this->user('pengurus'))->get('/proposal/'.$submission->id)->assertSee('Bertabrakan dengan ujian.')->assertDontSee('Kirim perbaikan');
        $this->post('/proposal/'.$submission->id.'/perbaiki', ['document' => $this->pdf()])->assertSessionHasErrors('action');
    }

    public function test_each_reviewer_stays_within_what_was_addressed_to_them(): void
    {
        $toNursing = $this->submit('jurusan:'.$this->jurusan()->id);
        $toNutrition = $this->submit('jurusan:'.$this->jurusan('GIZ')->id);
        $toDirektorat = $this->submit();

        $admin = $this->user('jurusan');
        $this->actingAs($admin)->get('/')->assertSee('Proposal &amp; LPJ menunggu', false);
        $this->get('/proposal')->assertOk()->assertViewHas('submissions', fn ($list) => $list->pluck('id')->all() === [$toNursing->id]);
        $this->get('/proposal/'.$toNutrition->id)->assertForbidden();
        $this->post('/proposal/'.$toDirektorat->id.'/tinjau', ['decision' => 'accepted'])->assertForbidden();
        $this->post('/proposal/'.$toNursing->id.'/tinjau', ['decision' => 'accepted'])->assertSessionHasNoErrors();

        $this->actingAs($this->user('admin'))->get('/proposal')->assertViewHas('submissions', fn ($list) => $list->count() === 3);
        $this->post('/proposal/'.$toNutrition->id.'/tinjau', ['decision' => 'accepted'])->assertSessionHasNoErrors();
    }

    public function test_only_officers_submit_and_members_cannot_read(): void
    {
        $data = ['club_id' => $this->sports()->id, 'type' => 'proposal', 'title' => 'Proposal Anggota', 'target' => 'direktorat', 'document' => $this->pdf()];
        $this->actingAs($this->user('atlet1'))->get('/proposal')->assertForbidden();
        $this->post('/proposal', $data)->assertForbidden();
        $this->actingAs($this->user('pembina'))->post('/proposal', $data)->assertForbidden();

        $submission = $this->submit();
        $this->actingAs($this->user('pembina'))->get('/proposal/'.$submission->id)->assertOk();
        $this->actingAs($this->user('pembina'))->get('/proposal/'.$submission->id.'/unduh')->assertOk();
        $this->actingAs($this->user('atlet1'))->get('/proposal/'.$submission->id)->assertForbidden();
        $this->get('/proposal/'.$submission->id.'/unduh')->assertForbidden();
    }

    public function test_himaju_submits_to_its_own_jurusan_or_direktorat_and_nobody_reviews_their_own(): void
    {
        $himaju = Club::where('name', 'HIMAJU Keperawatan')->firstOrFail();
        $officer = $this->user('mahasiswa');
        Assignment::create(['user_id' => $officer->id, 'role' => 'pengurus', 'club_id' => $himaju->id, 'position' => 'Ketua', 'starts_at' => now()->subDay(), 'ends_at' => now()->addYear()]);
        $data = ['club_id' => $himaju->id, 'type' => 'lpj_kepengurusan', 'title' => 'LPJ HIMAJU 2026', 'document' => $this->pdf()];

        $officer = User::find($officer->id);
        $this->actingAs($officer)->post('/proposal', $data + ['target' => 'jurusan:'.$this->jurusan('GIZ')->id])->assertSessionHasErrors('action');
        $this->post('/proposal', $data + ['target' => 'jurusan:'.$this->jurusan()->id])->assertSessionHasNoErrors();

        $own = ClubSubmission::latest('id')->firstOrFail();
        $own->update(['submitted_by' => $this->user('jurusan')->id]);
        $this->actingAs($this->user('jurusan'))->post('/proposal/'.$own->id.'/tinjau', ['decision' => 'accepted'])->assertForbidden();
    }

    public function test_only_pdf_or_docx_documents_are_accepted(): void
    {
        $this->actingAs($this->user('pengurus'))->post('/proposal', [
            'club_id' => $this->sports()->id, 'type' => 'proposal', 'title' => 'Proposal Salah', 'target' => 'direktorat', 'document' => UploadedFile::fake()->create('virus.exe', 10),
        ])->assertSessionHasErrors('document');
        $this->assertSame(0, ClubSubmission::count());
    }
}
