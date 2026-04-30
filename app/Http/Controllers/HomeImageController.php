<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class HomeImageController extends Controller
{
    private function directory(): string
    {
        return public_path('images/home');
    }

    public function index()
    {
        $directory = $this->directory();

        if (!File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $images = collect(File::files($directory))
            ->filter(fn($file) => in_array(strtolower($file->getExtension()), ['jpg', 'jpeg', 'png', 'webp']))
            ->sortBy(fn($file) => $file->getFilename())
            ->values()
            ->map(fn($file) => [
                'name' => $file->getFilename(),
                'url' => '/images/home/' . $file->getFilename(),
                'size' => $file->getSize(),
                'updated_at' => date('Y-m-d H:i:s', $file->getMTime()),
            ]);

        return response()->json($images);
    }

    public function store(Request $request)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpg,jpeg,png,webp|max:8192',
        ]);

        $directory = $this->directory();

        if (!File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $file = $request->file('image');
        $name = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $extension = $file->getClientOriginalExtension();
        $filename = Str::slug($name) . '-' . now()->format('YmdHis') . '.' . $extension;

        $file->move($directory, $filename);

        return response()->json([
            'name' => $filename,
            'url' => '/images/home/' . $filename,
        ], 201);
    }

    public function destroy(string $filename)
    {
        $safeName = basename($filename);
        $path = $this->directory() . DIRECTORY_SEPARATOR . $safeName;

        if (!File::exists($path)) {
            return response()->json(['message' => 'Image not found'], 404);
        }

        File::delete($path);

        return response()->json(['message' => 'Image deleted']);
    }
}
