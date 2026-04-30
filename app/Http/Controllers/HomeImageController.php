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

    private function metadataPath(): string
    {
        return config_path('homepage_images.json');
    }

    private function activeFilenames($files): array
    {
        $path = $this->metadataPath();

        if (!File::exists($path)) {
            return collect($files)->map(fn($file) => $file->getFilename())->values()->toArray();
        }

        $data = json_decode(File::get($path), true);

        return $data['active'] ?? [];
    }

    private function saveActiveFilenames(array $filenames): void
    {
        File::put($this->metadataPath(), json_encode([
            'active' => array_values(array_unique($filenames)),
        ], JSON_PRETTY_PRINT));
    }

    public function index()
    {
        $directory = $this->directory();

        if (!File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $files = collect(File::files($directory))
            ->filter(fn($file) => in_array(strtolower($file->getExtension()), ['jpg', 'jpeg', 'png', 'webp']))
            ->sortBy(fn($file) => $file->getFilename())
            ->values();

        $active = $this->activeFilenames($files);

        $images = $files->map(fn($file) => [
                'name' => $file->getFilename(),
                'url' => '/images/home/' . $file->getFilename(),
                'size' => $file->getSize(),
                'updated_at' => date('Y-m-d H:i:s', $file->getMTime()),
                'active' => in_array($file->getFilename(), $active),
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

        $active = $this->activeFilenames(File::files($directory));
        $active[] = $filename;
        $this->saveActiveFilenames($active);

        return response()->json([
            'name' => $filename,
            'url' => '/images/home/' . $filename,
            'active' => true,
        ], 201);
    }

    public function update(Request $request, string $filename)
    {
        $request->validate([
            'active' => 'required|boolean',
        ]);

        $safeName = basename($filename);
        $path = $this->directory() . DIRECTORY_SEPARATOR . $safeName;

        if (!File::exists($path)) {
            return response()->json(['message' => 'Image not found'], 404);
        }

        $files = File::files($this->directory());
        $active = collect($this->activeFilenames($files));

        if ($request->boolean('active')) {
            $active->push($safeName);
        } else {
            $active = $active->reject(fn($name) => $name === $safeName);
        }

        $this->saveActiveFilenames($active->values()->toArray());

        return response()->json(['message' => 'Image updated']);
    }

    public function destroy(string $filename)
    {
        $safeName = basename($filename);
        $path = $this->directory() . DIRECTORY_SEPARATOR . $safeName;

        if (!File::exists($path)) {
            return response()->json(['message' => 'Image not found'], 404);
        }

        File::delete($path);
        $active = collect($this->activeFilenames(File::files($this->directory())))
            ->reject(fn($name) => $name === $safeName)
            ->values()
            ->toArray();
        $this->saveActiveFilenames($active);

        return response()->json(['message' => 'Image deleted']);
    }
}
