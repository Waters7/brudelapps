<?php

namespace App\Services\Campus;

use App\Models\PaymentAccount;
use App\Models\Program;
use App\Models\Setting;
use App\Models\User;
use App\Services\DomainService;
use Illuminate\Support\Facades\DB;

/**
 * Campus data maintained by kemahasiswaan: programs, configurable policies and official bank accounts.
 */
class CampusService extends DomainService
{
    public const SETTING_KEYS = ['regional_definition', 'refund_policy', 'certificate_prefix'];

    /**
     * @param  array{name: string, department: string, code?: ?string}  $data
     */
    public function saveProgram(User $actor, ?Program $program, array $data): Program
    {
        abort_unless($actor->isStudentAffairs(), 403);

        return DB::transaction(function () use ($actor, $program, $data) {
            $program ??= new Program;
            $program->fill(['name' => $data['name'], 'department' => $data['department'], 'code' => $data['code'] ?: null])->save();
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
