<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AuditController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClashController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SetupController;
use App\Http\Controllers\TargetController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// First run only: the company enters its own details.
Route::get('/setup', [SetupController::class, 'show'])->name('setup.show');
Route::post('/setup', [SetupController::class, 'store'])->middleware('throttle:5,1')->name('setup.store');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
    Route::get('/login/otp', [AuthController::class, 'showOtp'])->name('login.otp');
    Route::post('/login/otp', [AuthController::class, 'verifyOtp'])->middleware('throttle:5,1');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::get('/branding/logo', [SettingsController::class, 'logo'])->name('logo');

    // Leads: everyone, but each person sees only what their role allows.
    Route::get('/leads', [LeadController::class, 'index'])->name('leads.index');
    Route::get('/leads/new', [LeadController::class, 'create'])->name('leads.create');
    Route::post('/leads', [LeadController::class, 'store'])->name('leads.store');
    Route::get('/leads/import', [LeadController::class, 'importForm'])->name('leads.import');
    Route::post('/leads/import', [LeadController::class, 'import'])->middleware('throttle:10,1')->name('leads.import.run');
    Route::get('/leads/import/template', [LeadController::class, 'template'])->name('leads.template');
    Route::get('/leads/export', [LeadController::class, 'export'])->middleware('throttle:10,1')->name('leads.export');
    Route::get('/leads/{lead}', [LeadController::class, 'show'])->name('leads.show');
    Route::get('/leads/{lead}/edit', [LeadController::class, 'edit'])->name('leads.edit');
    Route::put('/leads/{lead}', [LeadController::class, 'update'])->name('leads.update');
    Route::post('/leads/{lead}/activity', [LeadController::class, 'activity'])->name('leads.activity');
    Route::post('/leads/{lead}/assign', [LeadController::class, 'assign'])->middleware('role:manage')->name('leads.assign');

    Route::get('/targets', [TargetController::class, 'index'])->name('targets.index');
    Route::post('/sales', [TargetController::class, 'storeSale'])->name('sales.store');

    Route::post('/attendance/punch', [AttendanceController::class, 'punch'])->middleware('throttle:10,1')->name('attendance.punch');
    Route::get('/attendance/me', [AttendanceController::class, 'mine'])->name('attendance.mine');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile');
    Route::post('/profile/language', [ProfileController::class, 'language'])->name('profile.language');
    Route::post('/profile/password', [ProfileController::class, 'password'])->name('profile.password');
    Route::post('/profile/2fa/start', [ProfileController::class, 'startTwoFactor'])->name('profile.2fa.start');
    Route::post('/profile/2fa/confirm', [ProfileController::class, 'confirmTwoFactor'])->name('profile.2fa.confirm');
    Route::post('/profile/2fa/disable', [ProfileController::class, 'disableTwoFactor'])->name('profile.2fa.disable');

    // Admin and managers.
    Route::middleware('role:manage')->group(function () {
        Route::get('/clashes', [ClashController::class, 'index'])->name('clashes.index');
        Route::post('/clashes/{clash}/resolve', [ClashController::class, 'resolve'])->name('clashes.resolve');
        Route::post('/targets', [TargetController::class, 'saveTargets'])->name('targets.save');
        Route::patch('/sales/{sale}', [TargetController::class, 'updateSale'])->name('sales.update');
        Route::get('/attendance/today', [AttendanceController::class, 'today'])->name('attendance.today');
        Route::get('/attendance/register', [AttendanceController::class, 'register'])->name('attendance.register');
        Route::get('/attendance/people/{user}', [AttendanceController::class, 'person'])->name('attendance.person');
        Route::post('/attendance/people/{user}/day', [AttendanceController::class, 'override'])->name('attendance.override');
    });

    // Admin only.
    Route::middleware('role:admin')->group(function () {
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::get('/users/new', [UserController::class, 'create'])->name('users.create');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::get('/settings', [SettingsController::class, 'edit'])->name('settings');
        Route::post('/settings', [SettingsController::class, 'update'])->name('settings.update');
        Route::get('/audit', AuditController::class)->name('audit');
    });
});
