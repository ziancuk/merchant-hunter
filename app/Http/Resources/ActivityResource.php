<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\ProspectActivity;
use App\Support\ActivityText;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ProspectActivity */
class ActivityResource extends JsonResource
{
    public static $wrap = null;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'user' => $this->user ? ['id' => $this->user->id, 'name' => $this->user->name] : null,
            'body' => $this->type->value === 'note' ? $this->body : ActivityText::localize($this->body),
            'created_at' => Support::iso($this->created_at),
            'from_status' => $this->from_status,
            'to_status' => $this->to_status,
        ];
    }
}
