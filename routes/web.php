<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Events\TetsEvent;
use App\Http\Controllers\RoleDashboardController;
use App\Http\Middleware\RoleMiddleware;
use App\Http\Controllers\CampaignController;

Route::get('/', function () {
    return Inertia::render('welcome');
})->name('home');

Route::middleware(['auth', 'verified','role:Agent'])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('dashboard');
    })->name('dashboard');
});

Route::middleware(['auth', 'verified', 'role:SuperAdmin'])->group(function () {
  Route::get('/SuperAdminDashboard',[RoleDashboardController::class,'superAdmin'])->name('SuperAdmin'); 
  Route::get('/campaign',[CampaignController::class,'index'])->name('campaign');
  Route::get('/campaign/upload',[CampaignController::class,'showUploadForm'])->name('campaign.upload.form');
  Route::post('/upload', [CampaignController::class, 'upload'])->name('campaign.upload');
});



Route::middleware(['auth', 'verified', 'role:Admin'])->group(function () {
Route::get('/AdminDashboard',[RoleDashboardController::class,'admin'])->name('Admin');
});


require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
