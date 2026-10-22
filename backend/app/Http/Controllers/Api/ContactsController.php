<?php

namespace App\Http\Controllers\Api;

use App\Data\ShowroomData;
use App\Http\Controllers\Controller;
use App\Models\Showroom;
use App\Models\SiteContactSetting;
use Illuminate\Http\JsonResponse;

class ContactsController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $showrooms = Showroom::query()->published()->publicOrder()->get();
        $settings = SiteContactSetting::query()->with('primaryShowroom')->find(1);
        $primary = $settings?->primaryShowroom;
        if ($primary?->status !== 'published') {
            $primary = null;
        }

        return response()->json([
            'data' => [
                'primary' => $primary ? ShowroomData::fromModel($primary)->toArray() : null,
                'whatsappUrl' => $settings?->whatsapp_url,
                'showrooms' => $showrooms->map(fn (Showroom $showroom): array => ShowroomData::fromModel($showroom)->toArray())->values(),
            ],
        ])->header('Cache-Control', 'no-store');
    }
}
