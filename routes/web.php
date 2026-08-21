<?php

use App\Http\Controllers\Admin\CaseTypeController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FollowUpController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReferralController;
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
    Route::resource('referrals', ReferralController::class)->only(['index', 'create', 'store', 'show']);
    Route::get('/referrals/{referral}/attachments/{attachment}', [ReferralController::class, 'downloadAttachment'])
        ->name('referrals.attachments.download');

    Route::post('/referrals/{referral}/ai-summary', [ReferralController::class, 'generateAiSummary'])
        ->name('referrals.ai-summary');
    Route::get('/referrals/{referral}/care-plan', [ReferralController::class, 'showCarePlan'])
        ->name('referrals.care-plan');
    Route::post('/referrals/{referral}/care-plan', [ReferralController::class, 'confirmCarePlan'])
        ->name('referrals.care-plan.confirm');

    Route::get('/follow-up-plans/{plan}/guide', [FollowUpController::class, 'guide'])
        ->name('follow-up-plans.guide');
    Route::post('/follow-up-plans/{plan}/guide', [FollowUpController::class, 'generateGuide'])
        ->name('follow-up-plans.guide.generate');
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

require __DIR__.'/auth.php';
