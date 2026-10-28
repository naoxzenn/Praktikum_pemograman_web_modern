<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'status'  => 'ok',
        'message' => 'Portal Mahasiswa API berjalan.',
        'version' => '1.0',
    ]);
});
