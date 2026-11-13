<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class UploadController extends Controller
{
    /** Stores a picture chosen in the description editor and returns the address to put in the <img> tag. */
    public function image(Request $request): JsonResponse
    {
        $request->validate([
            // SVG is left out on purpose: it can carry scripts.
            'image' => ['required', 'file', 'mimes:jpg,jpeg,png,gif,webp', 'max:2048'],
        ]);

        $file = $request->file('image');
        $name = Str::random(32) . '.' . $file->extension();
        $file->move(public_path('uploads/services'), $name);

        return response()->json([
            'url' => $request->getBasePath() . '/uploads/services/' . $name,
        ]);
    }
}
