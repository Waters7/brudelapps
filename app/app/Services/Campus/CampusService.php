<?php

namespace App\Services\Campus;

use App\Models\AwardResult;
use App\Models\Competition;
use App\Models\Guideline;
use App\Models\LandingSlide;
use App\Models\PaymentAccount;
use App\Models\Program;
use App\Models\Setting;
use App\Models\User;
use App\Services\Club\ClubService;
use App\Services\DomainService;
use App\Services\PrivateFiles;
use App\Services\Wita;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Campus data maintained by kemahasiswaan: programs, configurable policies, official bank accounts, the
 * front-page banner, and the SOP and guideline documents.
 */
class CampusService extends DomainService
{
    public function __construct(private PrivateFiles $files, private ClubService $clubs) {}

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
            // A HIMAJU is for students of its jurusan, so moving a program ends its students' old HIMAJU membership.
            if ($program->wasChanged('department_id')) {
                User::where('program_id', $program->id)->whereNotNull('nim')->whereHas('memberships', fn ($q) => $q->where('status', 'active'))
                    ->get()->each(fn (User $student) => $this->clubs->releaseHimaju($student));
            }

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
     * Banner made from a competition or a verified award, with no upload needed. A winner's name is public only
     * with their publication consent; the slide keeps its text if the source changes later.
     */
    public function addSourceSlide(User $actor, string $source, int $id): LandingSlide
    {
        abort_unless($actor->isStudentAffairs(), 403);
        $content = match ($source) {
            'lomba' => $this->competitionSlide($id),
            'penghargaan' => $this->awardSlide($id),
            default => abort(404),
        };

        return DB::transaction(function () use ($actor, $source, $content) {
            $slide = LandingSlide::create($content + ['source' => $source, 'position' => (int) LandingSlide::max('position') + 1, 'created_by' => $actor->id]);
            $this->audit($actor, 'landing.slide_added', $slide, ['type' => $source]);

            return $slide;
        });
    }

    /**
     * @return array{title: string, caption: string, link_url: string, link_label: string}
     */
    private function competitionSlide(int $id): array
    {
        $competition = Competition::whereHas('event', fn ($q) => $q->where('status', 'approved'))->findOrFail($id);

        return [
            'title' => Str::limit($competition->name, 117),
            'caption' => Str::limit($competition->field.' · '.$competition->level.' · pendaftaran ditutup '.Wita::show($competition->closes_at, 'd M Y'), 297),
            'link_url' => '/lomba/'.$competition->id, 'link_label' => 'Lihat lomba',
        ];
    }

    /**
     * @return array{title: string, caption: string, link_url: null, link_label: null}
     */
    private function awardSlide(int $id): array
    {
        $award = AwardResult::with('recipients.user')->where('status', 'verified')->findOrFail($id);
        $names = $award->recipients->pluck('user')->where('publication_consent', true)->pluck('name');

        return [
            'title' => Str::limit($award->award.' · '.$award->title, 117),
            'caption' => Str::limit(($names->isNotEmpty() ? 'Selamat kepada '.$names->join(', ', ' dan ') : 'Selamat kepada para pemenang').' · '.$award->levelLabel(), 297),
            'link_url' => null, 'link_label' => null,
        ];
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
     * @param  array{title: string, description?: ?string}  $data
     */
    public function addGuideline(User $actor, array $data, UploadedFile $document): Guideline
    {
        abort_unless($actor->isStudentAffairs(), 403);
        [$path, $disk] = $this->files->storePdf($document, 'guidelines');

        try {
            return DB::transaction(function () use ($actor, $data, $path, $disk) {
                $guideline = Guideline::create(['title' => trim($data['title']), 'description' => $data['description'] ?? null, 'file_path' => $path, 'file_disk' => $disk, 'created_by' => $actor->id]);
                $this->audit($actor, 'guideline.created', $guideline, ['title' => $guideline->title]);

                return $guideline;
            });
        } catch (\Throwable $e) {
            $this->files->delete($path, $disk);
            throw $e;
        }
    }

    public function deleteGuideline(User $actor, Guideline $guideline): void
    {
        abort_unless($actor->isStudentAffairs(), 403);
        $this->audit($actor, 'guideline.deleted', $guideline, ['title' => $guideline->title]);
        $this->files->delete($guideline->file_path, $guideline->file_disk);
        $guideline->delete();
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
