<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\{AuthController,PortalController,EnrollmentController};
Route::middleware('guest')->group(function () {
    Route::get('/login',fn()=>view('auth.login'))->name('login');
    Route::post('/login',[AuthController::class,'login'])->middleware('throttle:6,1');
});
Route::middleware('auth')->group(function () {
    Route::post('/logout',[AuthController::class,'logout'])->name('logout');
    Route::get('/',[PortalController::class,'dashboard'])->name('dashboard');
    Route::get('/ukm',fn()=>view('catalog',['kind'=>'ukm']))->name('ukm');
    Route::get('/lomba',fn()=>view('catalog',['kind'=>'lomba']))->name('lomba');
    Route::get('/ukm/{id}',[PortalController::class,'club']);
    Route::get('/lomba/{id}',[PortalController::class,'competition']);
    Route::get('/pendaftaran',[PortalController::class,'applications'])->name('applications');
    Route::get('/ukm-saya',[PortalController::class,'memberships'])->name('memberships');
    Route::get('/rekomendasi',fn()=>view('recommendations'))->name('recommendations');
    Route::get('/tinjauan',[PortalController::class,'review'])->name('review');
    Route::get('/profil',[PortalController::class,'profile'])->name('profile');
    Route::post('/profil',[PortalController::class,'saveProfile']);
    Route::post('/rekrutmen/{id}',[EnrollmentController::class,'club']);
    Route::post('/lomba/{id}/draft',[EnrollmentController::class,'draft']);
    Route::post('/pendaftaran/{id}/invite',[EnrollmentController::class,'invite']);
    Route::post('/undangan/{id}',[EnrollmentController::class,'respond']);
    Route::post('/pendaftaran/{id}/submit',[EnrollmentController::class,'submit']);
    Route::post('/pendaftaran/{id}/document',[EnrollmentController::class,'upload']);
    Route::get('/pendaftaran/{id}/document',[EnrollmentController::class,'document']);
    Route::post('/tinjauan/{type}/{id}',[EnrollmentController::class,'review'])->where('type','ukm|lomba');
    Route::get('/ekspor/{type}',[EnrollmentController::class,'export'])->where('type','ukm|lomba');
});
