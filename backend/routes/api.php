<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\PageSeoController;
use App\Http\Controllers\Api\CategoryController;

Route::get('/health', function () {
    try {
        DB::select('SELECT 1');
        Redis::connection()->ping();

        return response()->json(['status' => 'ok', 'dependencies' => ['database' => 'ok', 'redis' => 'ok']]);
    } catch (Throwable $exception) {
        report($exception);

        return response()->json(['status' => 'unavailable'], 503);
    }
});

Route::get('/v1/page-seo/{pageKey}', [PageSeoController::class, 'show'])
    ->where('pageKey', '[a-z_]+');
Route::get('/v1/categories', [CategoryController::class, 'index']);
Route::get('/v1/categories/{slug}', [CategoryController::class, 'show'])->where('slug', '[a-z0-9-]+');
