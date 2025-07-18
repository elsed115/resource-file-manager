<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Tool API Routes
|--------------------------------------------------------------------------
|
| Here is where you may register API routes for your tool. These routes
| are loaded by the ServiceProvider of your tool. You're free to add
| as many additional routes to this file as your tool may require.
|
*/

Route::get('/list', [\Elsed\ResourceFileManager\Http\Controllers\FileController::class, 'list']);
Route::post('/upload', [\Elsed\ResourceFileManager\Http\Controllers\FileController::class, 'upload']);
Route::post('/create-folder', [\Elsed\ResourceFileManager\Http\Controllers\FileController::class, 'createFolder']);
Route::post('/rename', [\Elsed\ResourceFileManager\Http\Controllers\FileController::class, 'rename']);
Route::post('/delete', [\Elsed\ResourceFileManager\Http\Controllers\FileController::class, 'delete']);
