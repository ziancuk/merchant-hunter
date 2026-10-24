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
            'osm_filters' => ['nullable', 'array'],
            'google_types' => ['nullable', 'array'],
            'google_types.*' => ['string', 'max:60'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $template = $this->input('whatsapp_template');
            if (is_string($template) && ! $v->errors()->has('whatsapp_template') && ! MessageBuilder::isValidTemplate($template)) {
                $v->errors()->add('whatsapp_template', 'Template harus memuat placeholder yang valid ({nama_bisnis}, {nama_pengirim}, {kategori}) dan tidak boleh memuat placeholder lain.');
            }
        });
    }
}
