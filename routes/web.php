<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::redirect('/docs', '/docs/api');
Route::redirect('/docs/', '/docs/api');
