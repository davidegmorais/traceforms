<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AuditController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FormController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Public\PublicFormController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SubmissionController;
use App\Http\Controllers\TrackingLinkController;
use Illuminate\Support\Facades\Route;

// ---------------------------------------------------------------------
// Público: formulários, coleta e links rastreados
// ---------------------------------------------------------------------
Route::get('/', fn () => auth()->check() ? redirect()->route('dashboard') : view('welcome'))->name('home');

Route::get('/f/{publicId}', [PublicFormController::class, 'show'])->name('public.form');
Route::post('/f/{publicId}/collect', [PublicFormController::class, 'collect'])->middleware('throttle:collect')->name('public.collect');
Route::post('/f/{publicId}/submit', [PublicFormController::class, 'submit'])->middleware('throttle:submit')->name('public.submit');
Route::post('/f/{publicId}/upload', [PublicFormController::class, 'upload'])->middleware('throttle:upload')->name('public.upload');
Route::get('/l/{code}', [PublicFormController::class, 'link'])->name('public.link');

// ---------------------------------------------------------------------
// Autenticação: somente pelo Trace ID
// ---------------------------------------------------------------------
Route::get('/login', [AuthController::class, 'showLogin'])->middleware('guest')->name('login');
Route::get('/auth/trace', [AuthController::class, 'redirect'])->middleware('throttle:login')->name('auth.trace.redirect');
Route::get('/auth/trace/callback', [AuthController::class, 'callback'])->middleware('throttle:login')->name('auth.trace.callback');
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::middleware(['auth', 'trace'])->group(function () {
    Route::get('/avatars/{user}', [ProfileController::class, 'showAvatar'])->name('avatars.show');
    Route::post('/accounts/{account}/switch', [AccountController::class, 'switch'])->name('accounts.switch');
});

// ---------------------------------------------------------------------
// Painel (requer conta ativa)
// ---------------------------------------------------------------------
Route::middleware(['auth', 'trace', 'account'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::post('/profile/avatar', [ProfileController::class, 'avatar'])->name('profile.avatar');
    Route::delete('/profile/avatar', [ProfileController::class, 'removeAvatar'])->name('profile.avatar.remove');
    Route::post('/profile/logout-others', [ProfileController::class, 'logoutOthers'])->name('profile.logout-others');

    // Formulários
    Route::get('/forms', [FormController::class, 'index'])->name('forms.index');
    Route::get('/forms/create', [FormController::class, 'create'])->middleware('can:forms.create')->name('forms.create');
    Route::post('/forms', [FormController::class, 'store'])->middleware('can:forms.create')->name('forms.store');
    Route::get('/forms/{form}/edit', [FormController::class, 'edit'])->name('forms.edit');
    Route::put('/forms/{form}/builder', [FormController::class, 'saveBuilder'])->name('forms.builder');
    Route::get('/forms/{form}/settings', [FormController::class, 'settings'])->name('forms.settings');
    Route::put('/forms/{form}/settings', [FormController::class, 'updateSettings'])->name('forms.settings.update');
    Route::get('/forms/{form}/share', [FormController::class, 'share'])->name('forms.share');
    Route::post('/forms/{form}/status', [FormController::class, 'status'])->name('forms.status');
    Route::post('/forms/{form}/duplicate', [FormController::class, 'duplicate'])->name('forms.duplicate');
    Route::delete('/forms/{form}', [FormController::class, 'destroy'])->name('forms.destroy');

    // Links rastreados
    Route::post('/forms/{form}/links', [TrackingLinkController::class, 'store'])->name('links.store');
    Route::delete('/forms/{form}/links/{link}', [TrackingLinkController::class, 'destroy'])->name('links.destroy');

    // Relatórios
    Route::get('/forms/{form}/report/{tab?}', [ReportController::class, 'show'])
        ->whereIn('tab', ['overview', 'acquisition', 'audience', 'behavior', 'questions'])->name('reports.show');

    // Respostas
    Route::get('/forms/{form}/responses', [SubmissionController::class, 'index'])->name('submissions.index');
    Route::get('/forms/{form}/responses/export', [SubmissionController::class, 'export'])->name('submissions.export');
    Route::get('/forms/{form}/responses/{submission}', [SubmissionController::class, 'show'])->name('submissions.show');
    Route::patch('/forms/{form}/responses/{submission}', [SubmissionController::class, 'update'])->name('submissions.update');
    Route::delete('/forms/{form}/responses/{submission}', [SubmissionController::class, 'destroy'])->name('submissions.destroy');
    Route::get('/forms/{form}/files/{upload}', [SubmissionController::class, 'file'])->name('submissions.file');
    Route::get('/forms/{form}/abandoned', [SubmissionController::class, 'abandoned'])->name('submissions.abandoned');

    // Campanhas (UTMs de todos os formulários)
    Route::get('/campaigns', CampaignController::class)->middleware('can:reports.view')->name('campaigns');

    // Equipe (acessos vêm do Trace ID; aqui só a restrição por formulário)
    Route::get('/members', [MemberController::class, 'index'])->name('members.index');
    Route::put('/members/{membership}/forms', [MemberController::class, 'scope'])->middleware('can:members.manage')->name('members.scope');

    // Conta
    Route::get('/settings', [AccountController::class, 'edit'])->middleware('can:account.settings')->name('account.edit');
    Route::put('/settings', [AccountController::class, 'update'])->middleware('can:account.settings')->name('account.update');

    Route::get('/audit', AuditController::class)->middleware('can:audit.view')->name('audit');
});
