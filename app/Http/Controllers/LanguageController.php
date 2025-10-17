<?php

namespace App\Http\Controllers;

use App\Models\Language;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LanguageController extends Controller
{
    public function index(): JsonResponse
    {
        $languages = Language::orderBy('code')->get();

        return response()->json([
            'data' => $languages,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => 'required|string|max:10|unique:languages,code',
            'name' => 'required|string|max:191',
        ]);

        $language = Language::create($data);

        return response()->json(['data' => $language], 201);
    }
}
