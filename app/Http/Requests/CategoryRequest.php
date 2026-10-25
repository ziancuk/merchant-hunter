<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Services\Contact\MessageBuilder;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class CategoryRequest extends ApiRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $category = $this->route('category');

        return [
            'key' => ['required', 'string', 'max:40', 'regex:/^[a-z0-9_]+$/', Rule::unique('categories', 'key')->ignore($category?->id)],
            'name' => ['required', 'string', 'max:80'],
            'weight' => ['required', 'integer', 'between:0,100'],
            'whatsapp_template' => ['required', 'string', 'max:2000'],
            // osm_filters is NOT NULL in the schema: absent means "leave as is" (or [] on create), null is rejected.
            'osm_filters' => ['sometimes', 'array'],
            'osm_filters.*' => ['array'],
            'osm_filters.*.*' => ['string', 'max:60', 'regex:/^[A-Za-z0-9_]+$/'],
            'google_types' => ['nullable', 'array'],
            'google_types.*' => ['string', 'max:60'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $filters = $this->input('osm_filters');
            if (is_array($filters)) {
                foreach (array_keys($filters) as $key) {
                    // Same pattern OverpassQuery::mergedFilters accepts for tag keys.
                    if (! is_string($key) || preg_match('/^[a-z_]+$/', $key) !== 1) {
                        $v->errors()->add('osm_filters', __('requests.messages.osm_filters_invalid'));
                        break;
                    }
                }
            }

            $template = $this->input('whatsapp_template');
            if (is_string($template) && ! $v->errors()->has('whatsapp_template') && ! MessageBuilder::isValidTemplate($template)) {
                $v->errors()->add('whatsapp_template', __('requests.messages.template_invalid'));
            }
        });
    }
}
