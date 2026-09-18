<?php

use App\Http\Controllers\Admin\CaseTypeController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\CarePlanController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FollowUpController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReferralController;
use App\Http\Controllers\Reports\MonthlyReportController;
use App\Http\Controllers\Reports\VisitSummaryController;
use App\Http\Controllers\SatisfactionSurveyController;
use App\Http\Controllers\WardController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/referrals/zone-lookup', [ReferralController::class, 'zoneLookup'])->name('referrals.zone-lookup');
    Route::resource('referrals', ReferralController::class)->only(['index', 'create', 'store', 'show', 'edit', 'update']);
    Route::get('/referrals/{referral}/attachments/{attachment}', [ReferralController::class, 'downloadAttachment'])
        ->name('referrals.attachments.download');

    Route::post('/referrals/{referral}/ai-summary', [ReferralController::class, 'generateAiSummary'])
        ->name('referrals.ai-summary');
    Route::get('/referrals/{referral}/care-plan', [ReferralController::class, 'showCarePlan'])
        ->name('referrals.care-plan');
    Route::post('/referrals/{referral}/care-plan', [ReferralController::class, 'confirmCarePlan'])
        ->name('referrals.care-plan.confirm');
    Route::get('/referrals/{referral}/care-plan/print', [ReferralController::class, 'printCarePlan'])
        ->name('referrals.care-plan.print');

    Route::get('/care-plan/pending', [CarePlanController::class, 'pending'])
        ->middleware('role:home_visit_team,admin')->name('care-plan.pending');

    Route::get('/follow-up-plans', [FollowUpController::class, 'index'])->name('follow-up-plans.index');

    Route::get('/follow-up-plans/{plan}/record', [FollowUpController::class, 'createRecord'])
        ->name('follow-up-plans.record.create');
    Route::post('/follow-up-plans/{plan}/record', [FollowUpController::class, 'storeRecord'])
        ->name('follow-up-plans.record.store');

    Route::get('/follow-up-plans/{plan}/review', [FollowUpController::class, 'review'])
        ->name('follow-up-plans.review');
    Route::post('/follow-up-plans/{plan}/analyze', [FollowUpController::class, 'analyzeRecord'])
        ->name('follow-up-plans.analyze');
    Route::post('/follow-up-plans/{plan}/decision', [FollowUpController::class, 'confirmDecision'])
        ->name('follow-up-plans.decision');

    Route::get('/ward/visit-results', [WardController::class, 'visitResults'])->name('ward.visit-results');

    Route::get('/reports/visit-summary', [VisitSummaryController::class, 'show'])->name('reports.visit-summary');
    Route::get('/reports/monthly', [MonthlyReportController::class, 'show'])->name('reports.monthly');
    Route::post('/reports/monthly/photos', [MonthlyReportController::class, 'storePhoto'])->name('reports.monthly.photos.store');
    Route::get('/reports/monthly/photos/{photo}', [MonthlyReportController::class, 'showPhoto'])->name('reports.monthly.photos.show');
    Route::delete('/reports/monthly/photos/{photo}', [MonthlyReportController::class, 'destroyPhoto'])->name('reports.monthly.photos.destroy');

    Route::middleware('role:home_visit_team,admin')->group(function () {
        Route::get('/satisfaction-surveys', [SatisfactionSurveyController::class, 'index'])
            ->name('satisfaction-surveys.index');
        Route::get('/satisfaction-surveys/create', [SatisfactionSurveyController::class, 'create'])
            ->name('satisfaction-surveys.create');
        Route::post('/satisfaction-surveys', [SatisfactionSurveyController::class, 'store'])
            ->name('satisfaction-surveys.store');
        Route::get('/satisfaction-surveys/{satisfactionSurvey}', [SatisfactionSurveyController::class, 'show'])
            ->name('satisfaction-surveys.show');
    });

    Route::prefix('admin')->name('admin.')->middleware('role:admin')->group(function () {
        Route::get('/case-types', [CaseTypeController::class, 'index'])->name('case-types.index');
        Route::get('/case-types/create', [CaseTypeController::class, 'create'])->name('case-types.create');
        Route::post('/case-types', [CaseTypeController::class, 'store'])->name('case-types.store');
        Route::get('/case-types/{caseType}/edit', [CaseTypeController::class, 'edit'])->name('case-types.edit');
        Route::put('/case-types/{caseType}', [CaseTypeController::class, 'update'])->name('case-types.update');

        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
    });
});

// ลิงก์ตอบแบบประเมินความพึงพอใจด้วยตนเอง (สแกน QR) — ไม่ต้อง login เพราะเป็นผู้ป่วย/ญาติ
// ใช้ token แบบ opaque แทนการส่ง HN/ชื่อผ่าน query string (DESIGN.md §4.5 ห้ามส่งข้อมูลผู้ป่วยผ่าน URL)
Route::get('/s/{token}', [SatisfactionSurveyController::class, 'showByToken'])->name('satisfaction-surveys.token.show');
Route::post('/s/{token}', [SatisfactionSurveyController::class, 'submitByToken'])->name('satisfaction-surveys.token.submit');

require __DIR__.'/auth.php';
