<?php

namespace Tests\Feature;

use App\Models\Guideline;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class GuidelineTest extends DemoTestCase
{
    public function test_direktorat_publishes_documents_and_everyone_reads_them(): void
    {
        Storage::fake('local');
        $this->actingAs($this->user('mahasiswa'))->get('/pedoman')->assertOk()->assertSee('Dokumen sedang disiapkan')->assertDontSee('Terbitkan dokumen');
        $this->post('/pedoman', ['title' => 'SOP uji', 'document' => $this->pdf()])->assertForbidden();

        $this->actingAs($this->user('admin'))->post('/pedoman', ['title' => 'SOP Perizinan Kegiatan', 'description' => 'Berlaku semua organisasi.', 'document' => $this->pdf()])->assertSessionHasNoErrors();
        $guideline = Guideline::firstOrFail();
        Storage::disk('local')->assertExists($guideline->file_path);

        $this->actingAs($this->user('mahasiswa'))->get('/pedoman')->assertOk()->assertSee('SOP Perizinan Kegiatan');
        $this->get('/pedoman/'.$guideline->id.'/unduh')->assertOk();
        $this->post('/pedoman/'.$guideline->id.'/hapus')->assertForbidden();

        $this->actingAs($this->user('admin'))->post('/pedoman/'.$guideline->id.'/hapus')->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('guidelines', ['id' => $guideline->id]);
        Storage::disk('local')->assertMissing($guideline->file_path);
    }

    public function test_only_real_pdf_files_are_accepted(): void
    {
        Storage::fake('local');
        $this->actingAs($this->user('admin'))->post('/pedoman', ['title' => 'Bukan PDF', 'document' => UploadedFile::fake()->create('sop.docx', 10)])->assertSessionHasErrors('document');
        $this->post('/pedoman', ['title' => 'Bukan PDF', 'document' => UploadedFile::fake()->createWithContent('sop.pdf', 'teks biasa')])->assertSessionHasErrors('document');
        $this->assertSame(0, Guideline::count());
    }
}
