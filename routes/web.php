<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;

Route::get('/', function () {
    $dbConnected = false;
    $dbName = '';

    try {
        // Supabase(DB) への疎通確認
        DB::connection()->getPdo();
        $dbConnected = true;
        $dbName = DB::connection()->getDatabaseName();
    } catch (\Exception $e) {
        $dbConnected = false;
    }

    return view('welcome', [
        'dbConnected' => $dbConnected,
        'dbName' => $dbName,
    ]);
});