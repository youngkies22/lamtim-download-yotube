<?php

use App\Http\Controllers\VideoDownloadController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('home');
});

Route::post('/api/video-info', [VideoDownloadController::class, 'info'])
    ->middleware('throttle:10,1');

Route::post('/api/download', [VideoDownloadController::class, 'download'])
    ->middleware('throttle:10,1');
