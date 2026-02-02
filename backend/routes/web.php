<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/_sync', fn() => 'SYNC OK -Ztop ' . now());
