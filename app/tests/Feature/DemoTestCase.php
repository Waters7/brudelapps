<?php

namespace Tests\Feature;

use App\Models\Competition;
use App\Models\PaymentAccount;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Base for feature tests that run against the labelled demo data set.
 */
abstract class DemoTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['brudel.demo' => true, 'brudel.document_disk' => 'local']);
        $this->seed(DemoSeeder::class);
    }

    protected function user(string $key): User
    {
        return User::where('email', $key.'@demo.brudel.test')->firstOrFail();
    }

    protected function competition(string $name): Competition
    {
        return Competition::where('name', $name)->firstOrFail();
    }

    protected function pdf(string $name = 'dokumen.pdf'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n%BRUDEL test\n%%EOF\n");
    }

    protected function officialAccount(): PaymentAccount
    {
        return PaymentAccount::create(['bank_name' => 'Bank Uji', 'account_number' => '0000000000', 'account_holder' => 'Rekening uji otomatis', 'active' => true]);
    }
}
