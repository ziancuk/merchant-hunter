<?php

declare(strict_types=1);

namespace App\Services\Geocoding;

use RuntimeException;

/** A geocoding request failed (transient). */
class GeocodingException extends RuntimeException {}
