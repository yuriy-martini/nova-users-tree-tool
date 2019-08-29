<?php

use Illuminate\Support\Facades\Route;
use SoluzioneSoftware\Nova\Tools\UsersTree\Http\Controllers\ToolController;

/*
|--------------------------------------------------------------------------
| Tool API Routes
|--------------------------------------------------------------------------
|
| Here is where you may register API routes for your tool. These routes
| are loaded by the ServiceProvider of your tool. They are protected
| by your tool's "Authorize" middleware by default. Now, go build!
|
*/

Route::get('/', ToolController::class . '@getData');
Route::get('/{id}', ToolController::class . '@getNodeData');
Route::post('/search', ToolController::class . '@search');
