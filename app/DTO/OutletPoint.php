<?php

declare(strict_types=1);

namespace App\DTO;

use App\Models\Outlet;

final readonly class OutletPoint
{
    public function __construct(
        public int $id,
        public string $code,
        public string $name,
        public float $lat,
        public float $lng,
    ) {}

    public static function fromModel(Outlet $outlet): self
    {
        return new self($outlet->id, $outlet->code, $outlet->name, (float) $outlet->lat, (float) $outlet->lng);
    }
}
