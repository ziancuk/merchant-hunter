<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Services\Geo\BoundingBox;
use Illuminate\Validation\Validator;

/** Requests carrying a mandatory `bbox=west,south,east,north`. */
trait BboxRequest
{
    public function bbox(): BoundingBox
    {
        return BoundingBox::parse((string) $this->query('bbox')) ?? new BoundingBox(-180, -90, 180, 90);
    }

    protected function validateBbox(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            if (! $v->errors()->has('bbox') && BoundingBox::parse((string) $this->query('bbox')) === null) {
                $v->errors()->add('bbox', 'bbox harus berformat west,south,east,north dengan koordinat yang valid.');
            }
        });
    }
}
