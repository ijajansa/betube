<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SearchRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:200'],
            'type' => ['nullable', Rule::in(['all', 'videos', 'channels'])],
            'date' => ['nullable', Rule::in(['any', 'today', 'week', 'month', 'year'])],
            'duration' => ['nullable', Rule::in(['any', 'short', 'medium', 'long'])],
            'category' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', Rule::in(['relevance', 'latest', 'popular'])],
        ];
    }
}
