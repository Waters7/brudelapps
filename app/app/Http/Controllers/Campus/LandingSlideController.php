<?php

namespace App\Http\Controllers\Campus;

use App\Http\Controllers\Controller;
use App\Models\LandingSlide;
use App\Services\Campus\CampusService;
use App\Services\PrivateFiles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Kemahasiswaan direktorat manages the scrolling video/image banner of the public front page.
 */
class LandingSlideController extends Controller
{
    public const VIDEO_MAX_KB = 20480;

    public function __construct(private CampusService $campus) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()->isStudentAffairs(), 403);

        return view('manage.landing-slides', [
            'slides' => LandingSlide::orderBy('position')->orderBy('id')->get(),
            'uploadLimit' => ini_get('upload_max_filesize'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isStudentAffairs(), 403);
        $data = $request->validate([
            'title' => 'required|string|max:120',
            'caption' => 'nullable|string|max:300',
            'link_url' => 'nullable|string|max:300',
            'link_label' => 'nullable|string|max:40',
            'position' => 'nullable|integer|min:1|max:99',
            'media' => 'required|file|mimetypes:video/mp4,video/webm,image/jpeg,image/png,image/webp|max:'.self::VIDEO_MAX_KB,
        ]);
        $image = str_starts_with((string) $request->file('media')->getMimeType(), 'image/');
        if ($image) {
            $request->validate(['media' => 'max:3072|dimensions:min_width=800,min_height=320']);
        }
        $this->campus->addSlide($request->user(), $data, $request->file('media'));

        return back()->with('success', 'Slide banner ditambahkan ke halaman depan.');
    }

    public function update(Request $request, LandingSlide $slide): RedirectResponse
    {
        $data = $request->validate(['position' => 'required|integer|min:1|max:99', 'active' => 'required|boolean']);
        $this->campus->updateSlide($request->user(), $slide, ['position' => (int) $data['position'], 'active' => (bool) $data['active']]);

        return back()->with('success', 'Slide diperbarui.');
    }

    public function destroy(Request $request, LandingSlide $slide): RedirectResponse
    {
        $this->campus->deleteSlide($request->user(), $slide);

        return back()->with('success', 'Slide dihapus.');
    }

    /**
     * Public: the front page shows active slides to guests.
     */
    public function media(LandingSlide $slide, PrivateFiles $files): Response
    {
        abort_unless($slide->active, 404);

        return $files->media($slide->media_path, $slide->media_disk);
    }
}
