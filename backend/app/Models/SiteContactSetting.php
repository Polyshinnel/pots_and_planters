<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SiteContactSetting extends Model
{
    public $incrementing = false;

    protected $fillable = ['primary_showroom_id', 'whatsapp_url'];

    protected static function booted(): void
    {
        static::saving(function (SiteContactSetting $setting): void {
            if ((int) $setting->getKey() !== 1) {
                throw ValidationException::withMessages(['id' => 'Only the singleton contact settings record is allowed.']);
            }

            $validator = Validator::make($setting->getAttributes(), [
                'primary_showroom_id' => ['nullable', 'integer', 'exists:showrooms,id'],
                'whatsapp_url' => ['nullable', 'url:http,https', 'max:2048'],
            ]);
            if ($validator->fails()) {
                throw new ValidationException($validator);
            }
            if ($setting->primary_showroom_id !== null && ! Showroom::query()->published()->whereKey($setting->primary_showroom_id)->exists()) {
                throw ValidationException::withMessages(['primary_showroom_id' => 'The primary showroom must be published.']);
            }
        });
    }

    public function primaryShowroom()
    {
        return $this->belongsTo(Showroom::class, 'primary_showroom_id');
    }
}
