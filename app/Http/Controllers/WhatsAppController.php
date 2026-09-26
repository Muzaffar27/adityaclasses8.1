<?php

namespace App\Http\Controllers;

use App\Services\WhatsAppService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class WhatsAppController extends Controller
{
    public function sendTest(Request $request, WhatsAppService $whatsApp): JsonResponse
    {
        abort_unless(in_array($request->user()?->role, ['tutor', 'admin'], true), 403);

        $request->merge([
            'message' => trim((string) $request->input('message')),
        ]);

        $validated = $request->validate([
            'message' => ['required', 'string', 'max:4096'],
        ]);

        try {
            $messageId = $whatsApp->sendTextMessage($validated['message']);

            return response()->json([
                'message' => 'WhatsApp message accepted by Meta.',
                'message_id' => $messageId,
            ]);
        } catch (ConnectionException $exception) {
            return response()->json([
                'message' => 'Could not connect to Meta. Please try again.',
            ], 502);
        } catch (RequestException $exception) {
            $metaMessage = data_get($exception->response?->json(), 'error.message');

            return response()->json([
                'message' => $metaMessage ?: 'Meta rejected the WhatsApp message.',
            ], 502);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }
}
