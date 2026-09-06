<?php

namespace App\Http\Requests\Teams;

use Illuminate\Foundation\Http\FormRequest;

final class ChangeTrackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'trackId' => ['required', 'uuid'],
        ];
    }
}
