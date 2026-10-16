<?php

namespace App\Services\Campus;

use App\Models\LandingSlide;
use App\Models\PaymentAccount;
use App\Models\Program;
use App\Models\Setting;
use App\Models\User;
use App\Services\DomainService;
use App\Services\PrivateFiles;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Campus data maintained by kemahasiswaan: programs, configurable policies, official bank accounts, and the
 * front-page banner.
 */
class CampusService extends DomainService
{
    public function __construct(private PrivateFiles $files) {}

    public const SETTING_KEYS = ['regional_definition', 'refund_policy', 'certificate_prefix', 'operating_days', 'operating_open', 'operating_close'];

    /**
     * @param  array{name: string, department_id: int, code?: ?string}  $data
     */
    public function saveProgram(User $actor, ?Program $program, array $data): Program
    {
        abort_unless($actor->isStudentAffairs(), 403);

        return DB::transaction(function () use ($actor, $program, $data) {
            $program ??= new Program;
            $program->fill(['name' => $data['name'], 'department_id' => $data['department_id'], 'code' => ($data['code'] ?? null) ?: null])->save();
            $this->audit($actor, 'program.saved', $program, $data);

            return $program;
        });
    }

    /**
     * @param  array<string, ?string>  $values
     */
    public function saveSettings(User $actor, array $values): void
    {
        abort_unless($actor->isStudentAffairs(), 403);
        DB::transaction(function () use ($actor, $values) {
            foreach (self::SETTING_KEYS as $key) {
                if (array_key_exists($key, $values)) {
                    $setting = Setting::updateOrCreate(['key' => $key], ['value' => $values[$key], 'updated_by' => $actor->id]);
                    $this->audit($actor, 'setting.updated', $setting, ['key' => $key]);
                }
            }
        });
    }

    /**
     * Add a front-page banner slide: a short MP4/WebM video kept as uploaded, or an image re-encoded to 1600×640.
     *
     * @param  array{title: string, caption?: ?string, link_url?: ?string, link_label?: ?string, position?: ?int}  $data
     */
    public function addSlide(User $actor, array $data, UploadedFile $media): LandingSlide
    {
        abort_unless($actor->isStudentAffairs(), 403);
        $this->ensure(blank($data['link_url'] ?? null) || str_starts_with($data['link_url'], '/') || str_starts_with($data['link_url'], 'https://'),
            'Tautan harus diawali / (halaman BRUDEL) atau https://.');
        $video = str_starts_with((string) $media->getMimeType(), 'video/');
        [$path, $disk] = $video ? $this->files->storeVideo($media, 'landing-slides') : $this->files->storeSlideImage($media, 'landing-slides');

        try {
            return DB::transaction(function () use ($actor, $data, $video, $path, $disk) {
                $slide = LandingSlide::create([
                    'title' => $data['title'], 'caption' => $data['caption'] ?? null, 'link_url' => $data['link_url'] ?? null, 'link_label' => $data['link_label'] ?? null,
                    'media_type' => $video ? 'video' : 'image', 'media_path' => $path, 'media_disk' => $disk,
                    'position' => $data['position'] ?? (int) LandingSlide::max('position') + 1, 'created_by' => $actor->id,
                ]);
                $this->audit($actor, 'landing.slide_added', $slide, ['type' => $slide->media_type]);

                return $slide;
            });
        } catch (\Throwable $e) {
            $this->files->delete($path, $disk);
            throw $e;
        }
    }

    /**
     * @param  array{position: int, active: bool}  $data
     */
    public function updateSlide(User $actor, LandingSlide $slide, array $data): void
    {
        abort_unless($actor->isStudentAffairs(), 403);
        $slide->update(['position' => $data['position'], 'active' => $data['active']]);
        $this->audit($actor, 'landing.slide_updated', $slide, $data);
    }

    public function deleteSlide(User $actor, LandingSlide $slide): void
    {
        abort_unless($actor->isStudentAffairs(), 403);
        $this->audit($actor, 'landing.slide_deleted', $slide, ['title' => $slide->title]);
        $this->files->delete($slide->media_path, $slide->media_disk);
        $slide->delete();
    }

    /**
     * @param  array{bank_name: string, account_number: string, account_holder: string, notes?: ?string}  $data
     */
    public function addAccount(User $actor, array $data): PaymentAccount
    {
        abort_unless($actor->isStudentAffairs(), 403);

        return DB::transaction(function () use ($actor, $data) {
            $account = PaymentAccount::create($data + ['active' => true, 'created_by' => $actor->id]);
            $this->audit($actor, 'payment_account.created', $account, ['bank' => $data['bank_name']]);

            return $account;
        });
    }

    public function toggleAccount(User $actor, PaymentAccount $account, bool $active): void
    {
        abort_unless($actor->isStudentAffairs(), 403);
        DB::transaction(function () use ($actor, $account, $active) {
            $account->update(['active' => $active]);
            $this->audit($actor, $active ? 'payment_account.enabled' : 'payment_account.disabled', $account);
        });
    }
}
