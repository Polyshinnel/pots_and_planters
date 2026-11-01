<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class TelegramSetting extends Model
{
    public $incrementing = false;

    protected $hidden = ['bot_token', 'proxy_password'];

    protected $fillable = [
        'notifications_enabled', 'bot_token', 'proxy_enabled', 'proxy_host', 'proxy_port', 'proxy_username', 'proxy_password',
    ];

    protected function casts(): array
    {
        return [
            'notifications_enabled' => 'boolean', 'proxy_enabled' => 'boolean',
            'proxy_port' => 'integer', 'bot_token' => 'encrypted', 'proxy_password' => 'encrypted',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (TelegramSetting $setting): void {
            if ((int) $setting->getKey() !== 1) {
                throw ValidationException::withMessages(['id' => 'Only the Telegram settings record is allowed.']);
            }
        });
    }
}
