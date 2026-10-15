<?php

use App\Http\Controllers\Account\AssignmentController;
use App\Http\Controllers\Account\StudentController;
use App\Http\Controllers\Achievement\AchievementController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Campus\SettingsController;
use App\Http\Controllers\Certificate\CertificateController;
use App\Http\Controllers\Club\ClubManageController;
use App\Http\Controllers\Delegation\DelegationController;
use App\Http\Controllers\EnrollmentController;
use App\Http\Controllers\Event\EventManageController;
use App\Http\Controllers\Match\CompetitionOpsController;
use App\Http\Controllers\Match\LiveController;
use App\Http\Controllers\Payment\PaymentController;
use App\Http\Controllers\PortalController;
use App\Http\Controllers\Registration\ProgramApprovalController;
use App\Http\Controllers\Report\ReportController;
use App\Http\Controllers\Scoring\JudgeController;
use Illuminate\Support\Facades\Route;

/* Akun: masuk, aktivasi, pemulihan kata sandi. */
Route::middleware('guest')->group(function () {
    Route::get('/login', fn () => view('auth.login'))->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::get('/aktivasi', fn () => view('auth.request-link', ['mode' => 'activation']));
    Route::post('/aktivasi', [AuthController::class, 'requestActivation'])->middleware('throttle:account-link');
    Route::get('/aktivasi/{token}', [AuthController::class, 'activationForm']);
    Route::post('/aktivasi/{token}', [AuthController::class, 'activate'])->middleware('throttle:account-token');
    Route::get('/lupa-sandi', fn () => view('auth.request-link', ['mode' => 'reset']));
    Route::post('/lupa-sandi', [AuthController::class, 'requestReset'])->middleware('throttle:account-link');
    Route::get('/reset-sandi/{token}', [AuthController::class, 'resetForm']);
    Route::post('/reset-sandi/{token}', [AuthController::class, 'reset'])->middleware('throttle:account-token');
});

/* Halaman publik dengan informasi minimum. */
Route::get('/verifikasi/{token}', [CertificateController::class, 'verify'])->middleware('throttle:public');
Route::get('/mahasiswa/{slug}', [AchievementController::class, 'profile']);
Route::get('/live', [LiveController::class, 'index']);
Route::get('/live/{competition}', [LiveController::class, 'show']);

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    /* Ruang mahasiswa */
    Route::get('/', [PortalController::class, 'dashboard'])->name('dashboard');
    Route::get('/ukm', fn () => view('catalog', ['kind' => 'ukm']))->name('ukm');
    Route::get('/lomba', fn () => view('catalog', ['kind' => 'lomba']))->name('lomba');
    Route::get('/ukm/{id}', [PortalController::class, 'club']);
    Route::get('/lomba/{id}', [PortalController::class, 'competition']);
    Route::get('/pendaftaran', [PortalController::class, 'applications'])->name('applications');
    Route::get('/ukm-saya', [PortalController::class, 'memberships'])->name('memberships');
    Route::get('/rekomendasi', fn () => view('recommendations'))->name('recommendations');
    Route::get('/profil', [PortalController::class, 'profile'])->name('profile');
    Route::post('/profil', [PortalController::class, 'saveProfile']);
    Route::get('/notifikasi', [PortalController::class, 'notifications']);
    Route::post('/notifikasi/baca', [PortalController::class, 'readNotifications']);

    /* Pendaftaran UKM dan lomba */
    Route::post('/rekrutmen/{id}', [EnrollmentController::class, 'club']);
    Route::get('/ukm-pendaftaran/{id}/document', [EnrollmentController::class, 'clubDocument']);
    Route::post('/lomba/{id}/draft', [EnrollmentController::class, 'draft']);
    Route::post('/pendaftaran/{id}/invite', [EnrollmentController::class, 'invite']);
    Route::post('/pendaftaran/{id}/anggota/{participant}/hapus', [EnrollmentController::class, 'removeMember']);
    Route::post('/pendaftaran/{id}/batal', [EnrollmentController::class, 'cancel']);
    Route::post('/undangan/{id}', [EnrollmentController::class, 'respond']);
    Route::post('/pendaftaran/{id}/submit', [EnrollmentController::class, 'submit']);
    Route::post('/pendaftaran/{id}/document', [EnrollmentController::class, 'upload']);
    Route::get('/pendaftaran/{id}/document', [EnrollmentController::class, 'document']);
    Route::post('/pendaftaran/{registration}/pembayaran', [PaymentController::class, 'upload']);
    Route::get('/pembayaran/{proof}/bukti', [PaymentController::class, 'download']);

    /* Sertifikat, prestasi, delegasi */
    Route::get('/sertifikat', [CertificateController::class, 'mine']);
    Route::get('/sertifikat/{certificate}/unduh', [CertificateController::class, 'download']);
    Route::get('/prestasi', [AchievementController::class, 'mine']);
    Route::post('/prestasi', [AchievementController::class, 'store']);
    Route::get('/prestasi/{award}/bukti', [AchievementController::class, 'evidence']);
    Route::get('/delegasi', [DelegationController::class, 'index']);
    Route::get('/delegasi/baru', [DelegationController::class, 'create']);
    Route::post('/delegasi', [DelegationController::class, 'store']);
    Route::get('/delegasi/{delegation}', [DelegationController::class, 'show']);
    Route::post('/delegasi/{delegation}/ajukan', [DelegationController::class, 'submit']);
    Route::post('/delegasi/{delegation}/keputusan', [DelegationController::class, 'decide']);
    Route::post('/delegasi/{delegation}/surat-tugas', [DelegationController::class, 'letter']);
    Route::get('/delegasi/{delegation}/surat-tugas', [DelegationController::class, 'downloadLetter']);
    Route::post('/delegasi/{delegation}/hasil', [DelegationController::class, 'outcome']);

    /* Tinjauan pengurus/admin event */
    Route::get('/tinjauan', [PortalController::class, 'review'])->name('review');
    Route::post('/tinjauan/{type}/{id}', [EnrollmentController::class, 'review'])->where('type', 'ukm|lomba');
    Route::get('/ekspor/{type}', [EnrollmentController::class, 'export'])->where('type', 'ukm|lomba');

    /* UKM */
    Route::get('/kelola/ukm', [ClubManageController::class, 'index']);
    Route::post('/kelola/ukm', [ClubManageController::class, 'store']);
    Route::get('/kelola/ukm/{club}', [ClubManageController::class, 'show']);
    Route::post('/kelola/ukm/{club}', [ClubManageController::class, 'update']);
    Route::post('/kelola/ukm/{club}/rekrutmen/{recruitment?}', [ClubManageController::class, 'saveRecruitment']);
    Route::post('/kelola/anggota/{membership}', [ClubManageController::class, 'updateMember']);
    Route::get('/kelola/ukm/{club}/ekspor', [ClubManageController::class, 'export']);

    /* Event dan nomor lomba */
    Route::get('/kelola/event', [EventManageController::class, 'index']);
    Route::post('/kelola/event', [EventManageController::class, 'store']);
    Route::get('/kelola/event/{event}', [EventManageController::class, 'show']);
    Route::post('/kelola/event/{event}', [EventManageController::class, 'update']);
    Route::post('/kelola/event/{event}/ajukan', [EventManageController::class, 'submit']);
    Route::post('/kelola/event/{event}/keputusan', [EventManageController::class, 'decide']);
    Route::post('/kelola/event/{event}/lomba/{competition?}', [EventManageController::class, 'saveCompetition']);

    /* Operasional lomba: bagan, rubrik, hasil, penghargaan */
    Route::get('/kelola/lomba/{competition}', [CompetitionOpsController::class, 'show']);
    Route::post('/kelola/lomba/{competition}/unggulan', [CompetitionOpsController::class, 'seeds']);
    Route::post('/kelola/lomba/{competition}/bagan', [CompetitionOpsController::class, 'generate']);
    Route::post('/kelola/lomba/{competition}/rubrik', [CompetitionOpsController::class, 'criteria']);
    Route::post('/kelola/lomba/{competition}/sahkan', [CompetitionOpsController::class, 'publish']);
    Route::post('/kelola/lomba/{competition}/buka-koreksi', [CompetitionOpsController::class, 'reopen']);
    Route::post('/kelola/lomba/{competition}/penghargaan', [CompetitionOpsController::class, 'award']);
    Route::post('/pertandingan/{matchup}/jadwal', [CompetitionOpsController::class, 'schedule']);
    Route::post('/pertandingan/{matchup}/pemenang', [CompetitionOpsController::class, 'winner']);
    Route::post('/pertandingan/{matchup}/koreksi', [CompetitionOpsController::class, 'correct']);

    /* Live score dan penilaian */
    Route::get('/operator', [LiveController::class, 'operator']);
    Route::post('/operator/pertandingan/{matchup}', [LiveController::class, 'updateMatch']);
    Route::post('/operator/tampil/{registration}', [LiveController::class, 'performance']);
    Route::get('/penilaian', [JudgeController::class, 'index']);
    Route::get('/penilaian/{competition}', [JudgeController::class, 'show']);
    Route::post('/penilaian/{competition}/{registration}', [JudgeController::class, 'store']);

    /* Prodi dan pembayaran */
    Route::get('/prodi', [ProgramApprovalController::class, 'index']);
    Route::post('/prodi/{approval}', [ProgramApprovalController::class, 'decide']);
    Route::get('/pembayaran', [PaymentController::class, 'index']);
    Route::post('/pembayaran/{proof}', [PaymentController::class, 'verify']);

    /* Sertifikat dan prestasi (pengelola) */
    Route::get('/kelola/sertifikat', [CertificateController::class, 'manage']);
    Route::post('/kelola/sertifikat', [CertificateController::class, 'issue']);
    Route::post('/kelola/sertifikat/{certificate}/ganti', [CertificateController::class, 'replace']);
    Route::post('/kelola/sertifikat/{certificate}/cabut', [CertificateController::class, 'revoke']);
    Route::get('/kelola/prestasi', [AchievementController::class, 'manage']);
    Route::post('/kelola/prestasi/{award}', [AchievementController::class, 'verify']);

    /* Laporan */
    Route::get('/laporan', [ReportController::class, 'index']);
    Route::get('/laporan/ekspor/{type}', [ReportController::class, 'export']);

    /* Administrasi kampus */
    Route::get('/kelola/mahasiswa', [StudentController::class, 'index']);
    Route::post('/kelola/mahasiswa/impor', [StudentController::class, 'import']);
    Route::post('/kelola/mahasiswa/{student}/status', [StudentController::class, 'status']);
    Route::post('/kelola/mahasiswa/{student}/aktivasi', [StudentController::class, 'sendActivation']);
    Route::get('/kelola/penugasan', [AssignmentController::class, 'index']);
    Route::post('/kelola/penugasan', [AssignmentController::class, 'store']);
    Route::post('/kelola/penugasan/{assignment}/akhiri', [AssignmentController::class, 'end']);
    Route::get('/kelola/pengaturan', [SettingsController::class, 'index']);
    Route::post('/kelola/pengaturan', [SettingsController::class, 'saveSettings']);
    Route::post('/kelola/prodi/{program?}', [SettingsController::class, 'saveProgram']);
    Route::post('/kelola/rekening', [SettingsController::class, 'addAccount']);
    Route::post('/kelola/rekening/{account}', [SettingsController::class, 'toggleAccount']);
});
