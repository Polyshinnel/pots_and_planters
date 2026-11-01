<?php

namespace App\Services;

use App\Models\TelegramSetting;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class TelegramBotClient
{
    /** @return array{ok: bool, message_id: ?string, error: string, retry_after: ?int, permanent: bool} */
    public function sendMessage(TelegramSetting $settings, string $chatId, string $text): array
    {
        $response = $this->request($settings, 'sendMessage', ['chat_id' => $chatId, 'text' => mb_substr($text, 0, 3500), 'parse_mode' => 'HTML']);

        return $this->result($response);
    }

    /** @return array{ok: bool, name: ?string, username: ?string, error: string} */
    public function getMe(TelegramSetting $settings): array
    {
        $response = $this->request($settings, 'getMe');
        $data = $response->json();

        if (! $response->successful() || ! is_array($data) || ($data['ok'] ?? false) !== true) {
            return ['ok' => false, 'name' => null, 'username' => null, 'error' => $this->safeError($response)];
        }

        return ['ok' => true, 'name' => (string) data_get($data, 'result.first_name', ''), 'username' => data_get($data, 'result.username'), 'error' => ''];
    }

    /** @return array{ok: bool, message_id: ?string, error: string, retry_after: ?int, permanent: bool} */
    private function result(Response $response): array
    {
        $data = $response->json();
        $ok = $response->successful() && is_array($data) && ($data['ok'] ?? false) === true;

        return [
            'ok' => $ok,
            'message_id' => $ok && isset($data['result']['message_id']) ? (string) $data['result']['message_id'] : null,
            'error' => $ok ? '' : $this->safeError($response),
            'retry_after' => (int) $response->status() === 429 ? max(1, (int) data_get($data, 'parameters.retry_after', 1)) : null,
            'permanent' => $response->status() >= 400 && $response->status() < 500 && $response->status() !== 429,
        ];
    }

    private function request(TelegramSetting $settings, string $method, array $payload = []): Response
    {
        $token = (string) $settings->bot_token;
        if ($token === '') {
            return Http::response(['ok' => false], 401);
        }

        $request = Http::timeout(12)->connectTimeout(5)->acceptJson();
        if ($settings->proxy_enabled) {
            $auth = filled($settings->proxy_username)
                ? rawurlencode((string) $settings->proxy_username).':'.rawurlencode((string) $settings->proxy_password).'@'
                : '';
            $proxy = 'http://'.$auth.$settings->proxy_host.':'.(int) $settings->proxy_port;
            $request = $request->withOptions(['proxy' => ['http' => $proxy, 'https' => $proxy]]);
        }

        // The only remote host is fixed. Credentials are never included in query parameters or error text.
        return $request->post('https://api.telegram.org/bot'.$token.'/'.$method, $payload);
    }

    private function safeError(Response $response): string
    {
        return match (true) {
            $response->status() === 429 => 'Telegram rate limit',
            $response->status() >= 500 => 'Telegram temporarily unavailable',
            $response->status() >= 400 => 'Telegram rejected the request',
            default => 'Telegram returned an invalid response',
        };
    }
}
