<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\JobController;
use App\Http\Controllers\Api\CalibrationController;
use App\Http\Controllers\Api\CertificateController;
use App\Http\Controllers\Api\InstrumentController;
use App\Http\Controllers\Api\TemplateController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\ReportController;

// ── Auth ─────────────────────────────────────────────────────────
Route::post('/login',  [\App\Http\Controllers\Api\AuthController::class, 'login']);
Route::post('/logout', [\App\Http\Controllers\Api\AuthController::class, 'logout'])->middleware('auth:sanctum');

Route::get('/user', fn(Request $request) => $request->user())->middleware('auth:sanctum');

// ── Jobs (no auth while building frontend) ────────────────────────
// TODO: wrap in auth:sanctum + role middleware before production
Route::prefix('jobs')->group(function () {
    Route::get('/',               [JobController::class, 'index']);
    Route::get('/create',         [JobController::class, 'create']);
    Route::post('/',              [JobController::class, 'store']);
    Route::get('/{id}',           [JobController::class, 'show']);
    Route::put('/{id}',           [JobController::class, 'update']);
    Route::delete('/{id}',        [JobController::class, 'destroy']);
    Route::post('/{id}/finalize',    [JobController::class, 'finalize']);
    Route::put('/{id}/instruments',  [JobController::class, 'syncInstruments']);
});

// ── Calibrations / Worksheets (no auth while building frontend) ───
// TODO: wrap in auth:sanctum + role middleware before production
Route::prefix('calibrations')->group(function () {
    Route::get('/{id}',            [CalibrationController::class, 'show']);
    Route::get('/{id}/worksheet',  [CalibrationController::class, 'worksheet']);
    Route::put('/{id}/worksheet',  [CalibrationController::class, 'saveWorksheet']);
    Route::post('/{id}/calculate', [CalibrationController::class, 'calculate']);
    Route::get('/{id}/review',     [CalibrationController::class, 'review']);
});

// ── Certificates ──────────────────────────────────────────────────
Route::prefix('certificates')->group(function () {
    Route::get('/',          [CertificateController::class, 'index']);
    Route::get('/{id}',      [CertificateController::class, 'show']);
    Route::post('/{id}/send',[CertificateController::class, 'send']);
});

// ── Master Instruments ────────────────────────────────────────────
Route::prefix('instruments')->group(function () {
    Route::get('/due-soon',  [InstrumentController::class, 'dueSoon']);
    Route::get('/',          [InstrumentController::class, 'index']);
    Route::post('/',         [InstrumentController::class, 'store']);
    Route::get('/{id}',      [InstrumentController::class, 'show']);
    Route::put('/{id}',      [InstrumentController::class, 'update']);
    Route::delete('/{id}',   [InstrumentController::class, 'destroy']);
});

// ── Worksheet Templates ───────────────────────────────────────────
Route::prefix('templates')->group(function () {
    Route::get('/',                          [TemplateController::class, 'index']);
    Route::post('/',                         [TemplateController::class, 'store']);
    Route::get('/{id}',                      [TemplateController::class, 'show']);
    Route::put('/{id}',                      [TemplateController::class, 'update']);
    Route::get('/{id}/nodes',                [TemplateController::class, 'nodes']);
    Route::post('/{id}/nodes',               [TemplateController::class, 'storeNode']);
    Route::put('/{id}/nodes/{nodeId}',       [TemplateController::class, 'updateNode']);
    Route::delete('/{id}/nodes/{nodeId}',    [TemplateController::class, 'destroyNode']);
});

// ── Users ─────────────────────────────────────────────────────────
Route::prefix('users')->group(function () {
    Route::get('/',        [UserController::class, 'index']);
    Route::get('/roles',   [UserController::class, 'roles']);
    Route::post('/',       [UserController::class, 'store']);
    Route::put('/{id}',    [UserController::class, 'update']);
    Route::delete('/{id}', [UserController::class, 'destroy']);
});

// ── Reports ───────────────────────────────────────────────────────
Route::prefix('reports')->group(function () {
    Route::get('/jobs',      [ReportController::class, 'jobs']);
    Route::get('/instruments',[ReportController::class, 'instruments']);
    Route::get('/due-dates', [ReportController::class, 'dueDates']);
});
