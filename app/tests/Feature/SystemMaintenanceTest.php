<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Setting;
use App\Notifications\BrudelNotice;
use App\Services\Campus\DatabaseBackup;
use App\Services\Campus\StorageMonitor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Notification;

/**
 * Pengelolaan sistem: storage use with an early warning, and database backup and restore.
 */
class SystemMaintenanceTest extends DemoTestCase
{
    private string $storage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->storage = sys_get_temp_dir().'/brudel-test-'.uniqid();
        File::makeDirectory($this->storage.'/app/private', 0755, true);
        File::makeDirectory($this->storage.'/logs', 0755, true);
        $this->app->useStoragePath($this->storage);
        Cache::flush();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->storage);
        parent::tearDown();
    }

    private function backups(): DatabaseBackup
    {
        return app(DatabaseBackup::class);
    }

    public function test_only_kemahasiswaan_opens_the_system_pages(): void
    {
        foreach (['/kelola/sistem/penyimpanan', '/kelola/sistem/backup'] as $url) {
            $this->actingAs($this->user('jurusan'))->get($url)->assertForbidden();
            $this->actingAs($this->user('admin'))->get($url)->assertOk();
        }
        $this->get('/')->assertSee('PENGELOLAAN SISTEM')->assertSee('Backup &amp; Restore', false)->assertDontSee('Status Fitur');
    }

    public function test_the_storage_page_shows_the_disk_and_brudel_share(): void
    {
        $this->actingAs($this->user('admin'))->get('/kelola/sistem/penyimpanan')->assertOk()
            ->assertSee('Disk server terpakai')->assertSee('Cadangan database')->assertSee('Peringatan pada 80%');
    }

    public function test_thresholds_are_saved_and_must_be_in_order(): void
    {
        $this->actingAs($this->user('admin'))->post('/kelola/sistem/penyimpanan', ['warning' => 85, 'critical' => 80])->assertSessionHasErrors('critical');
        $this->post('/kelola/sistem/penyimpanan', ['warning' => 70, 'critical' => 85])->assertSessionHasNoErrors();
        $this->assertSame(['warning' => 70, 'critical' => 85], app(StorageMonitor::class)->thresholds());
    }

    public function test_a_full_disk_warns_kemahasiswaan_once_and_again_when_it_gets_worse(): void
    {
        Notification::fake();
        $monitor = app(StorageMonitor::class);
        $percent = $monitor->disk()['percent'];
        Setting::updateOrCreate(['key' => 'storage_warning_percent'], ['value' => (string) max(1, floor($percent) - 1)]);
        Setting::updateOrCreate(['key' => 'storage_critical_percent'], ['value' => '100']);

        $this->assertSame('warning', $monitor->alert());
        $this->assertNull($monitor->alert(), 'Peringatan yang sama tidak dikirim ulang dalam sehari.');
        Notification::assertSentTo($this->user('admin'), BrudelNotice::class);

        Setting::updateOrCreate(['key' => 'storage_critical_percent'], ['value' => (string) max(2, floor($percent))]);
        $this->assertSame('critical', $monitor->alert());
        $this->actingAs($this->user('admin'))->get('/')->assertSee('Penyimpanan server kritis');
    }

    public function test_a_backup_restores_the_data_it_was_taken_from(): void
    {
        $admin = $this->user('admin');
        $this->actingAs($admin)->post('/kelola/sistem/backup')->assertSessionHasNoErrors();
        $name = $this->backups()->all()->first()['name'];
        $clubs = Club::count();

        Club::create(['name' => 'UKM Setelah Cadangan', 'category' => 'ukm', 'field' => 'Seni', 'description' => 'Uji', 'mentor' => 'Uji', 'contact' => 'Uji', 'symbol' => 'spark', 'active' => true]);
        $this->get('/kelola/sistem/backup')->assertOk()->assertSee($name);

        $this->post('/kelola/sistem/backup/'.$name.'/pulihkan', ['confirmation' => 'pulihkan', 'password' => 'BrudelDemo!2026'])->assertSessionHasErrors('confirmation');
        $this->post('/kelola/sistem/backup/'.$name.'/pulihkan', ['confirmation' => 'PULIHKAN', 'password' => 'salah-sandi'])->assertSessionHasErrors('password');
        $this->assertSame($clubs + 1, Club::count());

        $this->post('/kelola/sistem/backup/'.$name.'/pulihkan', ['confirmation' => 'PULIHKAN', 'password' => 'BrudelDemo!2026'])->assertSessionHasNoErrors();
        $this->assertSame($clubs, Club::count());
        $this->assertFalse(Club::where('name', 'UKM Setelah Cadangan')->exists());
        $this->assertTrue($this->backups()->all()->contains('kind', 'sebelum-pemulihan'), 'Kondisi sebelum pemulihan dicadangkan.');
        $this->assertDatabaseHas('audit_logs', ['action' => 'backup.restored']);
    }

    public function test_tampered_or_mismatched_backups_are_refused(): void
    {
        $admin = $this->user('admin');
        $name = $this->backups()->create($admin);
        $path = $this->backups()->path($name);

        $lines = explode("\n", gzdecode(file_get_contents($path)));
        $lines[1] = str_replace('"', '"', $lines[1]).' ';
        $tampered = 'brudel-20260101-000000-unggahan.json.gz';
        file_put_contents($this->backups()->path($tampered), gzencode(implode("\n", $lines)));
        $this->actingAs($admin)->post('/kelola/sistem/backup/'.$tampered.'/pulihkan', ['confirmation' => 'PULIHKAN', 'password' => 'BrudelDemo!2026'])->assertSessionHasErrors('action');

        DB::table('migrations')->insert(['migration' => '2099_01_01_000000_masa_depan', 'batch' => 99]);
        $this->post('/kelola/sistem/backup/'.$name.'/pulihkan', ['confirmation' => 'PULIHKAN', 'password' => 'BrudelDemo!2026'])->assertSessionHasErrors('action');
        $this->post('/kelola/sistem/backup/..%2F..%2F.env/hapus')->assertSessionHasErrors('action');
    }

    public function test_an_uploaded_backup_is_kept_only_when_its_signature_matches(): void
    {
        $admin = $this->user('admin');
        $name = $this->backups()->create($admin);
        $copy = UploadedFile::fake()->createWithContent('cadangan.json.gz', file_get_contents($this->backups()->path($name)));
        $this->actingAs($admin)->post('/kelola/sistem/backup/unggah', ['backup' => $copy])->assertSessionHasNoErrors();
        $this->assertTrue($this->backups()->all()->contains('kind', 'manual'));

        $fake = UploadedFile::fake()->createWithContent('palsu.json.gz', gzencode('{"brudel":1}'."\n"));
        $this->post('/kelola/sistem/backup/unggah', ['backup' => $fake])->assertSessionHasErrors('action');
        $this->assertCount(2, glob($this->backups()->path('*.json.gz')));
    }

    public function test_the_scheduled_backup_keeps_the_newest_seven(): void
    {
        foreach (range(1, 9) as $day) {
            $this->travelTo(now()->addDays($day));
            $this->artisan('brudel:backup', ['--otomatis' => true])->assertSuccessful();
        }
        $this->assertCount(DatabaseBackup::KEEP_AUTOMATIC, $this->backups()->all()->where('kind', 'otomatis'));
    }

    public function test_officers_change_the_organization_icon(): void
    {
        $club = Club::where('name', 'UKM Olahraga')->firstOrFail();
        $this->actingAs($this->user('pengurus'))->get('/kelola/ukm/'.$club->id)->assertOk()->assertSee('Ikon organisasi');
        $this->post('/kelola/ukm/'.$club->id, ['field' => $club->field, 'mentor' => $club->mentor, 'contact' => $club->contact, 'description' => $club->description, 'symbol' => 'ball'])->assertSessionHasNoErrors();
        $this->assertSame('ball', $club->fresh()->symbol);
        $this->post('/kelola/ukm/'.$club->id, ['field' => $club->field, 'mentor' => $club->mentor, 'contact' => $club->contact, 'description' => $club->description, 'symbol' => 'roket'])->assertSessionHasErrors('symbol');
    }
}
