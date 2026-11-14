<?php

use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\AutomationController;
use App\Http\Controllers\AvailabilityController;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\InboxController;
use App\Http\Controllers\QuoteController;
use App\Http\Controllers\ReplyTemplateController;
use App\Http\Controllers\SystemController;
use App\Http\Controllers\WhatsAppSettingsController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AuditController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClashController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\MasterController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SetupController;
use App\Http\Controllers\TargetController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// First run only: the company enters its own details.
Route::get('/setup', [SetupController::class, 'show'])->name('setup.show');
Route::post('/setup', [SetupController::class, 'store'])->middleware('throttle:5,1')->name('setup.store');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:60,1'); // per-email lock is inside the controller
    Route::get('/login/otp', [AuthController::class, 'showOtp'])->name('login.otp');
    Route::post('/login/otp', [AuthController::class, 'verifyOtp'])->middleware('throttle:60,1');
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

    Route::get('/search', SearchController::class)->name('search');
    Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
    Route::get('/analytics', AnalyticsController::class)->name('analytics');

    Route::get('/products', [ProductController::class, 'index'])->name('products.index');
    Route::get('/products/new', [ProductController::class, 'create'])->name('products.create');
    Route::post('/products', [ProductController::class, 'store'])->name('products.store');
    Route::get('/products/export', [ProductController::class, 'export'])->middleware('throttle:10,1')->name('products.export');
    Route::post('/products/import', [ProductController::class, 'import'])->middleware('throttle:10,1')->name('products.import');
    Route::get('/products/{product}/edit', [ProductController::class, 'edit'])->name('products.edit');
    Route::put('/products/{product}', [ProductController::class, 'update'])->name('products.update');

    Route::get('/tasks', [TaskController::class, 'index'])->name('tasks.index');
    Route::post('/tasks', [TaskController::class, 'store'])->name('tasks.store');
    Route::patch('/tasks/{task}/status', [TaskController::class, 'status'])->name('tasks.status');
    Route::delete('/tasks/{task}', [TaskController::class, 'destroy'])->name('tasks.destroy');

    Route::get('/inbox', [InboxController::class, 'index'])->name('inbox');
    Route::get('/inbox/{conversation}', [InboxController::class, 'index'])->name('inbox.show');
    Route::post('/inbox/{conversation}/send', [InboxController::class, 'send'])->middleware('throttle:30,1')->name('inbox.send');
    Route::post('/inbox/{conversation}/done', [InboxController::class, 'done'])->name('inbox.done');
    Route::post('/availability', [AvailabilityController::class, 'update'])->name('availability');
    Route::post('/availability/{user}', [AvailabilityController::class, 'update'])->name('availability.user');

    Route::get('/quotes', [QuoteController::class, 'index'])->name('quotes.index');
    Route::get('/quotes/new', [QuoteController::class, 'create'])->name('quotes.create');
    Route::post('/quotes', [QuoteController::class, 'store'])->name('quotes.store');
    Route::get('/quotes/{quote}', [QuoteController::class, 'show'])->name('quotes.show');
    Route::get('/quotes/{quote}/pdf', [QuoteController::class, 'pdf'])->middleware('throttle:30,1')->name('quotes.pdf');
    Route::patch('/quotes/{quote}/status', [QuoteController::class, 'status'])->name('quotes.status');
    Route::post('/quotes/{quote}/send', [QuoteController::class, 'send'])->middleware('throttle:10,1')->name('quotes.send');

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
        Route::get('/campaigns', [CampaignController::class, 'index'])->name('campaigns.index');
        Route::get('/campaigns/new', [CampaignController::class, 'create'])->name('campaigns.create');
        Route::post('/campaigns', [CampaignController::class, 'store'])->middleware('throttle:5,1')->name('campaigns.store');
        Route::get('/campaigns/{campaign}', [CampaignController::class, 'show'])->name('campaigns.show');
        Route::get('/ready-replies', [ReplyTemplateController::class, 'index'])->name('replies.index');
        Route::post('/ready-replies', [ReplyTemplateController::class, 'save'])->name('replies.save');
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
        Route::get('/office-hours', [SettingsController::class, 'officeHours'])->name('office-hours');
        Route::post('/office-hours', [SettingsController::class, 'updateOfficeHours'])->name('office-hours.update');
        Route::get('/masters', [MasterController::class, 'index'])->name('masters.index');
        Route::post('/masters', [MasterController::class, 'store'])->name('masters.store');
        Route::put('/masters/{option}', [MasterController::class, 'update'])->name('masters.update');
        Route::get('/whatsapp', [WhatsAppSettingsController::class, 'edit'])->name('whatsapp.settings');
        Route::post('/whatsapp', [WhatsAppSettingsController::class, 'update'])->name('whatsapp.update');
        Route::post('/whatsapp/numbers', [WhatsAppSettingsController::class, 'storeNumber'])->name('whatsapp.numbers.store');
        Route::put('/whatsapp/numbers/{number}', [WhatsAppSettingsController::class, 'updateNumber'])->name('whatsapp.numbers.update');
        Route::post('/whatsapp/lead-form-token', [WhatsAppSettingsController::class, 'rotateToken'])->name('whatsapp.token');
        Route::get('/automations', [AutomationController::class, 'edit'])->name('automations');
        Route::post('/automations', [AutomationController::class, 'update'])->name('automations.update');
        Route::get('/system', SystemController::class)->name('system');
        Route::get('/integrations', [\App\Http\Controllers\IntegrationController::class, 'index'])->name('integrations.index');
        Route::post('/integrations/facebook/subscribe', [\App\Http\Controllers\IntegrationController::class, 'subscribeFacebook'])->middleware('throttle:10,1')->name('integrations.facebook');
        Route::post('/integrations/{platform}', [\App\Http\Controllers\IntegrationController::class, 'update'])->name('integrations.update')->where('platform', '[a-z_]+');
        Route::post('/integrations/{platform}/fetch', [\App\Http\Controllers\IntegrationController::class, 'fetch'])->middleware('throttle:10,1')->name('integrations.fetch')->where('platform', '[a-z_]+');
        Route::post('/integrations/{platform}/new-link', [\App\Http\Controllers\IntegrationController::class, 'rotate'])->name('integrations.rotate')->where('platform', '[a-z_]+');
        Route::get('/roles', [RoleController::class, 'edit'])->name('roles');
        Route::post('/roles', [RoleController::class, 'update'])->name('roles.update');
    });
});
