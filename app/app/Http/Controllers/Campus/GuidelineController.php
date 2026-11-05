<?php

namespace App\Http\Controllers\Campus;

use App\Http\Controllers\Controller;
use App\Models\Guideline;
use App\Services\Campus\CampusService;
use App\Services\PrivateFiles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * SOP dan pedoman kegiatan non-akademik: direktorat publishes PDF documents, every signed-in user reads them.
 */
class GuidelineController extends Controller
{
    public function __construct(private CampusService $campus) {}

    public function index(): View
    {
        return view('guidelines', ['guidelines' => Guideline::latest('id')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['title' => 'required|string|max:150', 'description' => 'nullable|string|max:500', 'document' => PrivateFiles::RULES]);
        $this->campus->addGuideline($request->user(), $data, $request->file('document'));

        return back()->with('success', 'Dokumen diterbitkan.');
    }

    public function destroy(Request $request, Guideline $guideline): RedirectResponse
    {
        $this->campus->deleteGuideline($request->user(), $guideline);

        return back()->with('success', 'Dokumen dihapus.');
    }

    public function download(Guideline $guideline, PrivateFiles $files): StreamedResponse
    {
        return $files->download($guideline->file_path, $guideline->file_disk, str($guideline->title)->slug().'.pdf');
    }
}
