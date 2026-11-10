<?php

use App\Http\Controllers\Api\CatalogueController;
use App\Http\Controllers\Api\CatalogueDownloadController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ContactsController;
use App\Http\Controllers\Api\EnquiriesController;
use App\Http\Controllers\Api\NewsController;
use App\Http\Controllers\Api\PageSeoController;
use App\Http\Controllers\Api\ProductReadController;
use App\Http\Middleware\LimitEnquiryPayload;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Route;

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
Route::get('/v1/catalogues', [CatalogueController::class, 'index']);
Route::get('/v1/catalogues/download', CatalogueDownloadController::class)->middleware('throttle:catalogue-download');
Route::get('/v1/contacts', ContactsController::class);
Route::post('/v1/enquiries', [EnquiriesController::class, 'store'])
    ->middleware([LimitEnquiryPayload::class, 'throttle:enquiries']);
Route::get('/v1/news', [NewsController::class, 'index']);
Route::get('/v1/news/{slug}', [NewsController::class, 'show'])->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*');
Route::get('/v1/products', [ProductReadController::class, 'index']);
Route::post('/v1/products/resolve', [ProductReadController::class, 'resolve'])->middleware('throttle:products-resolve');
Route::get('/v1/products/{slug}', [ProductReadController::class, 'show']);
