<?php

use App\Http\Controllers\LanguageController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\TranslationController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('languages', [LanguageController::class, 'index']);
    Route::post('languages', [LanguageController::class, 'store']);

    Route::get('tags', [TagController::class, 'index']);
    Route::post('tags', [TagController::class, 'store']);

    // Translations
    Route::get('translations', [TranslationController::class, 'index']);
    Route::get('translations/{translation}', [TranslationController::class, 'show']);
    Route::post('translations', [TranslationController::class, 'store']);
    Route::put('translations/{translation}', [TranslationController::class, 'update']);
    Route::patch('translations/{translation}', [TranslationController::class, 'update']);
    Route::delete('translations/{translation}', [TranslationController::class, 'destroy']);



});
 // Export (streamed)
 Route::get('export/{languageCode}.json', [TranslationController::class, 'export']);
