<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class WhatsAppService
{
    public function sendTextMessage(string $message): string
    {
        $token = trim((string) config('services.whatsapp.access_token'));
        $phoneNumberId = trim((string) config('services.whatsapp.phone_number_id'));
        $graphVersion = ltrim(trim((string) config('services.whatsapp.graph_version')), 'vV');
        $recipient = preg_replace('/\D+/', '', (string) config('services.whatsapp.test_recipient'));

        if (
            $token === '' ||
            !ctype_digit($phoneNumberId) ||
            !preg_match('/^\d+\.\d+$/', $graphVersion) ||
            !preg_match('/^\d{8,15}$/', $recipient)
        ) {
            throw new RuntimeException('WhatsApp test settings are incomplete or invalid.');
        }

        $response = Http::withToken($token)
            ->acceptJson()
            ->timeout(20)
            ->post("https://graph.facebook.com/v{$graphVersion}/{$phoneNumberId}/messages", [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $recipient,
                'type' => 'text',
                'text' => [
                    'preview_url' => false,
                    'body' => $message,
                ],
            ]);

        $response->throw();

        $messageId = (string) data_get($response->json(), 'messages.0.id');

        if ($messageId === '') {
            throw new RuntimeException('Meta accepted the request without returning a message ID.');
        }

        return $messageId;
    }
}
