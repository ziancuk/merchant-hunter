<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Prospect;
use App\Services\Contact\ContactPolicy;
use App\Services\Contact\WhatsAppNormalizer;
use App\Services\Settings\SettingsService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Prospect */
class ProspectDetailResource extends JsonResource
{
    public static $wrap = null;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $p = $this->resource;

        return [
            'id' => $p->id,
            'name' => $p->name,
            'category' => ['id' => $p->category->id, 'key' => $p->category->key, 'name' => $p->category->name],
            'address' => $p->address,
            'address_source' => $p->address_source,
            'lat' => $p->lat,
            'lng' => $p->lng,
            'phone' => $p->phone,
            'phone_simulated' => (bool) $p->phone_simulated,
            'whatsapp_url' => $this->whatsappUrl($p),
            'website' => Support::safeUrl($p->website),
            'rating' => $p->rating,
            'rating_simulated' => (bool) $p->rating_simulated,
            'review_count' => $p->review_count,
            'reviews_simulated' => (bool) $p->reviews_simulated,
            'data_updated_at' => Support::iso($p->fetched_at),
            'first_seen_at' => Support::iso($p->first_seen_at),
            'score' => $p->score,
            'score_band' => $p->scoreBand()->value,
            'score_breakdown' => $p->score_breakdown ?? [],
            'nearest_outlet' => $p->nearestOutlet ? [
                'id' => $p->nearestOutlet->id,
                'name' => $p->nearestOutlet->name,
                'distance_m' => $p->nearest_outlet_distance_m,
            ] : null,
            'neighbor_count_500m' => $p->neighbor_count_500m,
            'area_density' => $p->area_density,
            'pipeline_status' => $p->pipeline_status->value,
            'rejection_reason' => $p->rejectionReason
                ? ['id' => $p->rejectionReason->id, 'key' => $p->rejectionReason->key, 'label' => $p->rejectionReason->label]
                : null,
            'handled_by' => $p->handler ? ['id' => $p->handler->id, 'name' => $p->handler->name] : null,
            'last_contacted_at' => Support::iso($p->last_contacted_at),
            'last_contacted_by' => $p->lastContactedBy ? ['id' => $p->lastContactedBy->id, 'name' => $p->lastContactedBy->name] : null,
            'contact_count' => $p->contact_count,
            'do_not_contact' => (bool) $p->do_not_contact,
            'badges' => Support::badges($p),
            'lifecycle_status' => $p->lifecycle_status->value,
            'latest_note' => $p->getAttribute('latest_note'),
            'activities' => ActivityResource::collection($p->activities)->resolve(),
            'contact_warning' => app(ContactPolicy::class)->warning($p),
        ];
    }

    private function whatsappUrl(Prospect $p): ?string
    {
        if ($p->do_not_contact) {
            return null;
        }
        if ($p->phone_simulated && ! app(SettingsService::class)->bool('demo.allow_simulated_whatsapp')) {
            return null;
        }
        $normalized = WhatsAppNormalizer::normalize($p->phone);

        return $normalized !== null ? 'https://wa.me/'.$normalized : null;
    }
}
