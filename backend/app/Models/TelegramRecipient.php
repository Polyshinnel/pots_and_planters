<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class TelegramRecipient extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'chat_id', 'enabled'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saving(function (TelegramRecipient $recipient): void {
            $rawChatId = $recipient->getAttributes()['chat_id'] ?? null;
            $chatId = is_int($rawChatId) || is_string($rawChatId) ? (string) $rawChatId : '';
            $validator = Validator::make(['name' => $recipient->name, 'chat_id' => $chatId], [
                'name' => ['required', 'string', 'max:120'],
                'chat_id' => ['required', function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! is_string($value) || ! preg_match('/^-?[1-9][0-9]{0,18}$/', $value)) {
                        $fail('The chat ID must be a non-zero signed 64-bit integer.');

                        return;
                    }
                    $digits = ltrim($value, '-');
                    $limit = str_starts_with($value, '-') ? '9223372036854775808' : '9223372036854775807';
                    if (strlen($digits) > strlen($limit) || (strlen($digits) === strlen($limit) && strcmp($digits, $limit) > 0)) {
                        $fail('The chat ID must be a non-zero signed 64-bit integer.');
                    }
                }],
            ]);

            if ($validator->fails()) {
                throw new ValidationException($validator);
            }
        });
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(EnquiryDelivery::class, 'recipient_id');
    }
}
