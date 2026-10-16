<?php

namespace App\Http\Controllers\Campus;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\MasterOption;
use App\Models\PaymentAccount;
use App\Models\Program;
use App\Services\Campus\MasterData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Master Data menu of kemahasiswaan direktorat: one sub menu per choice list.
 */
class MasterDataController extends Controller
{
    public function __construct(private MasterData $master) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()->isStudentAffairs(), 403);

        return view('manage.master-index', [
            'counts' => collect(MasterData::TYPES)->map(fn ($spec, $type) => [
                'active' => $this->master->active($type)->count(), 'total' => $this->master->all($type)->count(),
            ]),
            'programs' => Program::count(),
            'unmapped' => Program::whereNull('department_id')->count(),
            'departments' => Department::count(),
            'accounts' => PaymentAccount::where('active', true)->count(),
        ]);
    }

    public function show(Request $request, string $type): View
    {
        abort_unless($request->user()->isStudentAffairs(), 403);
        $options = $this->master->all($type);

        return view('manage.master-show', [
            'type' => $type,
            'spec' => MasterData::TYPES[$type],
            'options' => $options,
            'usage' => $options->mapWithKeys(fn (MasterOption $option) => [$option->id => $this->master->usage($option)]),
        ]);
    }

    public function store(Request $request, string $type): RedirectResponse
    {
        $this->master->save($request->user(), $type, null, $this->validated($request));

        return back()->with('success', 'Pilihan ditambahkan.');
    }

    public function update(Request $request, string $type, MasterOption $option): RedirectResponse
    {
        $this->master->save($request->user(), $type, $option, $this->validated($request));

        return back()->with('success', 'Pilihan diperbarui.');
    }

    public function destroy(Request $request, string $type, MasterOption $option): RedirectResponse
    {
        abort_if($option->type !== $type, 404);
        $this->master->delete($request->user(), $option);

        return back()->with('success', 'Pilihan dihapus.');
    }

    /**
     * @return array{label: string, position?: ?int, active: bool, meta: array<string, mixed>}
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'label' => 'required|string|max:120',
            'position' => 'nullable|integer|min:1|max:999',
            'active' => 'nullable|boolean',
            'meta' => 'nullable|array',
            'meta.*' => 'nullable|string|max:120',
        ]);

        return ['label' => $data['label'], 'position' => $data['position'] ?? null, 'active' => (bool) ($data['active'] ?? true), 'meta' => $data['meta'] ?? []];
    }
}
