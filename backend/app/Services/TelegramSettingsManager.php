<?php

namespace App\Services;

use App\Models\TelegramSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class TelegramSettingsManager
{
    /**
     * Save Telegram configuration. Empty secret inputs deliberately mean "keep current value".
     * This service has no Filament or network dependency; BE-07.3 supplies the administrator UI.
     */
    public function save(array $input): TelegramSetting
    {
        $validator = Validator::make($input, [
            'notifications_enabled' => ['sometimes', 'boolean'],
            'bot_token' => ['sometimes', 'nullable', 'string', 'max:500'],
            'proxy_enabled' => ['sometimes', 'boolean'],
            'proxy_host' => ['sometimes', 'nullable', 'string', 'max:253', function (string $attribute, mixed $value, \Closure $fail): void {
                if ($value === null || $value === '') {
                    return;
                }
                if (! $this->isHostOnly((string) $value)) {
                    $fail('Enter a proxy hostname or IP address without a URL, scheme, port, or path.');
                }
            }],
            'proxy_port' => ['sometimes', 'nullable', 'integer', 'between:1,65535'],
            'proxy_username' => ['sometimes', 'nullable', 'string', 'max:255'],
            'proxy_password' => ['sometimes', 'nullable', 'string', 'max:500'],
        ]);
        $values = $validator->validate();

        return DB::transaction(function () use ($values): TelegramSetting {
            $settings = TelegramSetting::query()->whereKey(1)->lockForUpdate()->first();
            if (! $settings) {
                $settings = new TelegramSetting;
                $settings->setAttribute('id', 1);
            }

            foreach (['notifications_enabled', 'proxy_enabled', 'proxy_host', 'proxy_port', 'proxy_username'] as $field) {
                if (array_key_exists($field, $values)) {
                    $settings->{$field} = $values[$field];
                }
            }
            foreach (['bot_token', 'proxy_password'] as $secret) {
                if (isset($values[$secret]) && trim((string) $values[$secret]) !== '') {
                    $settings->{$secret} = $values[$secret];
                }
            }

            $errors = [];
            if ($settings->notifications_enabled && blank($settings->bot_token)) {
                $errors['bot_token'] = 'A bot token is required when notifications are enabled.';
            }
            if ($settings->proxy_enabled && (blank($settings->proxy_host) || blank($settings->proxy_port))) {
                $errors['proxy_host'] = 'A proxy host and port are required when proxy is enabled.';
            }
            if ($settings->proxy_enabled && filled($settings->proxy_username) !== filled($settings->proxy_password)) {
                $errors['proxy_username'] = 'Proxy username and password must be provided together.';
            }
            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }

            $settings->save();

            return $settings;
        });
    }

    private function isHostOnly(string $host): bool
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return true;
        }

        if (strlen($host) > 253 || str_contains($host, '://') || str_contains($host, '/') || str_contains($host, '@') || str_contains($host, ':')) {
            return false;
        }

        return (bool) preg_match('/^(?=.{1,253}$)(?:[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?)(?:\.(?:[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?))*$/', $host);
    }
}
