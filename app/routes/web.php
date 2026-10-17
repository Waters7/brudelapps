<?php

use App\Http\Controllers\Account\AssignmentController;
use App\Http\Controllers\Account\GoogleController;
use App\Http\Controllers\Account\ProfileController;
use App\Http\Controllers\Account\SignedInUserController;
use App\Http\Controllers\Account\StudentController;
use App\Http\Controllers\Achievement\AchievementController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Campus\LandingSlideController;
use App\Http\Controllers\Campus\MasterDataController;
use App\Http\Controllers\Campus\SettingsController;
use App\Http\Controllers\Campus\SystemSettingsController;
use App\Http\Controllers\Certificate\CertificateController;
use App\Http\Controllers\Club\ActivityController;
use App\Http\Controllers\Club\ClubManageController;
use App\Http\Controllers\Club\SpaceController;
use App\Http\Controllers\Delegation\DelegationController;
use App\Http\Controllers\Delegation\PerjadinController;
use App\Http\Controllers\Department\DepartmentController;
use App\Http\Controllers\EnrollmentController;
use App\Http\Controllers\Event\CompetitionAccountController;
use App\Http\Controllers\Event\EventManageController;
use App\Http\Controllers\Match\CompetitionOpsController;
use App\Http\Controllers\Match\LiveController;
use App\Http\Controllers\Payment\PaymentController;
use App\Http\Controllers\PortalController;
use App\Http\Controllers\Report\ReportController;
use App\Http\Controllers\Scoring\JudgeController;
use App\Services\Campus\MasterData;
use Illuminate\Support\Facades\Route;

/* Halaman depan: info publik untuk tamu, dashboard untuk pengguna yang sudah masuk. */
Route::get('/', [PortalController::class, 'home'])->name('dashboard');

/* Akun: masuk, Google, aktivasi, pemulihan kata sandi. */
Route::middleware('guest')->group(function () {
    Route::get('/login', fn () => view('auth.login'))->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::get('/auth/google', [GoogleController::class, 'redirect'])->middleware('throttle:google');
    Route::get('/auth/google/callback', [GoogleController::class, 'callback'])->middleware('throttle:google');
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
Route::get('/banner/{club}', [ClubManageController::class, 'showBanner'])->middleware('throttle:banner');
Route::get('/slide/{slide}', [LandingSlideController::class, 'media'])->middleware('throttle:banner');

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    /* Ruang mahasiswa */
    Route::get('/ukm', fn () => view('catalog', ['kind' => 'ukm']))->name('ukm');
    Route::get('/lomba', fn () => view('catalog', ['kind' => 'lomba']))->name('lomba');
    Route::get('/ukm/{id}', [PortalController::class, 'club']);
    Route::get('/lomba/{id}', [PortalController::class, 'competition']);
    Route::get('/pendaftaran', [PortalController::class, 'applications'])->name('applications');
    Route::get('/ukm-saya', [PortalController::class, 'memberships'])->name('memberships');
    Route::get('/rekomendasi', fn () => view('recommendations'))->name('recommendations');
    Route::get('/profil', [PortalController::class, 'profile'])->name('profile');
    Route::post('/profil', [PortalController::class, 'saveProfile']);
    Route::post('/profil/sandi', [ProfileController::class, 'password'])->middleware('throttle:account-token');
    Route::get('/sandi-baru', [ProfileController::class, 'initialPasswordForm']);
    Route::post('/sandi-baru', [ProfileController::class, 'initialPassword'])->middleware('throttle:account-token');
    Route::post('/profil/kontak', [ProfileController::class, 'contact']);
    Route::post('/profil/foto', [ProfileController::class, 'photo']);
    Route::post('/profil/foto/hapus', [ProfileController::class, 'removePhoto']);
    Route::get('/foto/{user}', [ProfileController::class, 'show']);

    /* Jelajah kegiatan UKM dan absensi berkode */
    Route::get('/kegiatan', [ActivityController::class, 'index']);
    Route::post('/kegiatan', [ActivityController::class, 'store']);
    Route::view('/bantuan', 'support');

    /* Ruang organisasi: pengumuman, obrolan, quest, peringkat */
    Route::get('/ruang/{club}', [SpaceController::class, 'show']);
    Route::post('/ruang/{club}/pengumuman', [SpaceController::class, 'storePost']);
    Route::post('/ruang/pengumuman/{post}/sematkan', [SpaceController::class, 'pinPost']);
    Route::post('/ruang/pengumuman/{post}/hapus', [SpaceController::class, 'destroyPost']);
    Route::post('/ruang/{club}/quest', [SpaceController::class, 'storeQuest']);
    Route::post('/ruang/quest/{quest}/tutup', [SpaceController::class, 'closeQuest']);
    Route::post('/ruang/quest/{quest}/lapor', [SpaceController::class, 'submitQuest']);
    Route::post('/ruang/laporan-quest/{submission}', [SpaceController::class, 'reviewQuest']);
    Route::post('/kegiatan/hadir', [ActivityController::class, 'checkIn'])->middleware('throttle:checkin');
    Route::get('/kegiatan/izin', [ActivityController::class, 'permits']);
    Route::post('/kegiatan/{activity}/izin', [ActivityController::class, 'decidePermit']);
    Route::get('/kegiatan/{activity}', [ActivityController::class, 'show']);
    Route::post('/kegiatan/{activity}/kode', [ActivityController::class, 'openCode']);
    Route::post('/kegiatan/{activity}/kode/tutup', [ActivityController::class, 'closeCode']);
    Route::post('/kegiatan/{activity}/hadir-manual', [ActivityController::class, 'markPresent']);
    Route::post('/kegiatan/{activity}/laporan', [ActivityController::class, 'report']);
    Route::get('/kegiatan/{activity}/laporan', [ActivityController::class, 'printable']);
    Route::get('/kegiatan/{activity}/laporan.csv', [ActivityController::class, 'csv']);
    Route::get('/kegiatan/{activity}/notulen', [ActivityController::class, 'minutes']);
    Route::post('/kegiatan/{activity}/batal', [ActivityController::class, 'cancel']);
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
    Route::post('/kelola/ukm/{club}/anggota', [ClubManageController::class, 'appointMember']);
    Route::post('/kelola/ukm/{club}/banner', [ClubManageController::class, 'banner']);
    Route::post('/kelola/ukm/{club}/banner/hapus', [ClubManageController::class, 'removeBanner']);
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
    Route::get('/kelola/lomba/{competition}/log-nilai', [CompetitionOpsController::class, 'scoreLog']);
    Route::get('/kelola/lomba/{competition}/akun', [CompetitionAccountController::class, 'show']);
    Route::post('/kelola/lomba/{competition}/akun/{account}/reset', [CompetitionAccountController::class, 'reset']);
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

    /* Jurusan dan pembayaran */
    Route::get('/jurusan', [DepartmentController::class, 'index']);
    Route::post('/jurusan/persetujuan/{approval}', [DepartmentController::class, 'decide']);
    Route::get('/jurusan/{department}/ekspor', [DepartmentController::class, 'export']);
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
    Route::post('/kelola/penugasan/akun-staf', [AssignmentController::class, 'storeStaff']);
    Route::post('/kelola/penugasan/impor-ketua', [AssignmentController::class, 'importOfficers']);
    Route::get('/kelola/penugasan/template-ketua.csv', [AssignmentController::class, 'officerTemplate']);
    Route::post('/kelola/penugasan/{assignment}/akhiri', [AssignmentController::class, 'end']);
    Route::get('/kelola/pengguna', [SignedInUserController::class, 'index']);
    Route::get('/kelola/audit', [SignedInUserController::class, 'audit']);
    Route::get('/kelola/sistem', [SystemSettingsController::class, 'index']);
    Route::post('/kelola/sistem', [SystemSettingsController::class, 'update']);
    Route::post('/kelola/sistem/uji-email', [SystemSettingsController::class, 'testMail'])->middleware('throttle:account-link');
    Route::get('/kelola/master', [MasterDataController::class, 'index']);
    Route::get('/kelola/master/{type}', [MasterDataController::class, 'show'])->whereIn('type', array_keys(MasterData::TYPES));
    Route::post('/kelola/master/{type}', [MasterDataController::class, 'store'])->whereIn('type', array_keys(MasterData::TYPES));
    Route::post('/kelola/master/{type}/{option}', [MasterDataController::class, 'update'])->whereIn('type', array_keys(MasterData::TYPES));
    Route::post('/kelola/master/{type}/{option}/hapus', [MasterDataController::class, 'destroy'])->whereIn('type', array_keys(MasterData::TYPES));
    Route::get('/kelola/banner-depan', [LandingSlideController::class, 'index']);
    Route::post('/kelola/banner-depan', [LandingSlideController::class, 'store']);
    Route::post('/kelola/banner-depan/{slide}', [LandingSlideController::class, 'update']);
    Route::post('/kelola/banner-depan/{slide}/hapus', [LandingSlideController::class, 'destroy']);
    Route::get('/kelola/perjadin', [PerjadinController::class, 'index']);
    Route::get('/kelola/perjadin/ekspor', [PerjadinController::class, 'csv']);
    Route::get('/kelola/perjadin/{delegation}', [PerjadinController::class, 'show']);
    Route::get('/kelola/perjadin/{delegation}/data.json', [PerjadinController::class, 'json']);
    Route::get('/kelola/pengaturan', [SettingsController::class, 'index']);
    Route::post('/kelola/pengaturan', [SettingsController::class, 'saveSettings']);
    Route::post('/kelola/prodi/{program?}', [SettingsController::class, 'saveProgram']);
    Route::post('/kelola/rekening', [SettingsController::class, 'addAccount']);
    Route::post('/kelola/rekening/{account}', [SettingsController::class, 'toggleAccount']);
});
