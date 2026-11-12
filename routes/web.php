<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Admin\AdminAccountController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\AuditController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FormController;
use App\Http\Controllers\InvitationController;
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

Route::get('/invitations/{token}', [InvitationController::class, 'show'])->name('invitations.show');

// ---------------------------------------------------------------------
// Autenticação
// ---------------------------------------------------------------------
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
    Route::get('/forgot-password', [PasswordController::class, 'request'])->name('password.request');
    Route::post('/forgot-password', [PasswordController::class, 'email'])->middleware('throttle:login')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordController::class, 'reset'])->name('password.reset');
    Route::post('/reset-password', [PasswordController::class, 'update'])->name('password.update');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::post('/invitations/{token}/accept', [InvitationController::class, 'accept'])->name('invitations.accept');
    Route::get('/accounts/create', [AccountController::class, 'create'])->name('accounts.create');
    Route::post('/accounts', [AccountController::class, 'store'])->name('accounts.store');
    Route::post('/impersonate/stop', [AdminUserController::class, 'stopImpersonating'])->name('impersonate.stop');
    Route::get('/avatars/{user}', [ProfileController::class, 'showAvatar'])->name('avatars.show');
    Route::post('/accounts/{account}/switch', [AccountController::class, 'switch'])->name('accounts.switch');
});

// ---------------------------------------------------------------------
// Painel (requer conta ativa)
// ---------------------------------------------------------------------
Route::middleware(['auth', 'account'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'password'])->name('profile.password');
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

    // Equipe
    Route::get('/members', [MemberController::class, 'index'])->name('members.index');
    Route::post('/members/invite', [MemberController::class, 'invite'])->middleware('can:members.manage')->name('members.invite');
    Route::put('/members/{membership}', [MemberController::class, 'update'])->middleware('can:members.manage')->name('members.update');
    Route::delete('/members/{membership}', [MemberController::class, 'destroy'])->name('members.destroy');
    Route::delete('/invitations/{invitation}', [MemberController::class, 'revoke'])->middleware('can:members.manage')->name('invitations.revoke');

    // Conta
    Route::get('/settings', [AccountController::class, 'edit'])->middleware('can:account.settings')->name('account.edit');
    Route::put('/settings', [AccountController::class, 'update'])->middleware('can:account.settings')->name('account.update');
    Route::post('/settings/transfer', [AccountController::class, 'transfer'])->middleware('can:account.transfer')->name('account.transfer');
    Route::delete('/settings', [AccountController::class, 'destroy'])->middleware('can:account.delete')->name('account.destroy');

    Route::get('/audit', AuditController::class)->middleware('can:audit.view')->name('audit');
});

// ---------------------------------------------------------------------
// Administração da plataforma (super admin)
// ---------------------------------------------------------------------
Route::middleware(['auth', 'super'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', AdminDashboardController::class)->name('dashboard');

    Route::get('/accounts', [AdminAccountController::class, 'index'])->name('accounts.index');
    Route::get('/accounts/{account}', [AdminAccountController::class, 'show'])->name('accounts.show');
    Route::put('/accounts/{account}', [AdminAccountController::class, 'update'])->name('accounts.update');
    Route::post('/accounts/{account}/suspend', [AdminAccountController::class, 'suspend'])->name('accounts.suspend');
    Route::post('/accounts/{account}/reactivate', [AdminAccountController::class, 'reactivate'])->name('accounts.reactivate');
    Route::post('/accounts/{account}/members', [AdminAccountController::class, 'addMember'])->name('accounts.members.add');
    Route::match(['put', 'delete'], '/accounts/{account}/members/{membership}', [AdminAccountController::class, 'member'])->name('accounts.members.update');
    Route::delete('/accounts/{account}', [AdminAccountController::class, 'destroy'])->name('accounts.destroy');

    Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
    Route::get('/users/create', [AdminUserController::class, 'create'])->name('users.create');
    Route::post('/users', [AdminUserController::class, 'store'])->name('users.store');
    Route::get('/users/{user}', [AdminUserController::class, 'show'])->name('users.show');
    Route::put('/users/{user}', [AdminUserController::class, 'update'])->name('users.update');
    Route::post('/users/{user}/suspend', [AdminUserController::class, 'suspend'])->name('users.suspend');
    Route::post('/users/{user}/reactivate', [AdminUserController::class, 'reactivate'])->name('users.reactivate');
    Route::post('/users/{user}/impersonate', [AdminUserController::class, 'impersonate'])->name('users.impersonate');
    Route::delete('/users/{user}', [AdminUserController::class, 'destroy'])->name('users.destroy');
});
