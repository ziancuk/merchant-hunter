<?php

declare(strict_types=1);

namespace App\Services\Geocoding;

/** Nominatim answered 403/429: geocoding stops until the block expires (D-44). */
class GeocodingBlockedException extends GeocodingException {}
