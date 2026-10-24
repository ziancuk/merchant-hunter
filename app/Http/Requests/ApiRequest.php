<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Base for JSON endpoints: auth is enforced by route middleware; Indonesian generic messages. */
abstract class ApiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'required' => ':attribute wajib diisi.',
            'required_if' => ':attribute wajib diisi.',
            'required_with' => ':attribute wajib diisi.',
            'required_without' => ':attribute wajib diisi.',
            'string' => ':attribute harus berupa teks.',
            'integer' => ':attribute harus berupa bilangan bulat.',
            'numeric' => ':attribute harus berupa angka.',
            'boolean' => ':attribute harus berupa benar/salah.',
            'array' => ':attribute harus berupa daftar.',
            'in' => ':attribute tidak valid.',
            'exists' => ':attribute tidak ditemukan.',
            'unique' => ':attribute sudah digunakan.',
            'min' => ':attribute di bawah batas minimum.',
            'max' => ':attribute melebihi batas maksimum.',
            'between' => ':attribute di luar rentang yang diizinkan.',
            'email' => ':attribute harus berupa alamat email yang valid.',
            'date_format' => ':attribute harus berformat tanggal yang valid.',
            'file' => ':attribute harus berupa berkas.',
            'mimes' => ':attribute harus berupa berkas CSV.',
            'distinct' => ':attribute berisi nilai ganda.',
            'regex' => 'Format :attribute tidak valid.',
            'enum' => ':attribute tidak valid.',
        ];
    }
}
