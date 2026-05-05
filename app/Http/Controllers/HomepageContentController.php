<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class HomepageContentController extends Controller
{
    public function show()
    {
        $path = $this->contentPath();

        if (!file_exists($path)) {
            return response()->json([
                'demo' => [
                    'title' => '',
                    'videos' => [],
                ],
                'footer' => [],
            ]);
        }

        return response()->json(
            json_decode(file_get_contents($path), true) ?: []
        );
    }

    public function save(Request $request)
    {
        file_put_contents(
            $this->contentPath(),
            json_encode($request->all(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );

        return response()->json(['status' => 'saved']);
    }

    private function contentPath(): string
    {
        return config_path('homepage_content.json');
    }
}
