<?php

namespace App\Http\Requests;

use App\Enums\VideoVisibility;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVideoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('video')) ?? false;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:10000'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'tags' => ['nullable', 'string', 'max:1000', function ($attribute, $value, $fail) {
                $tags = array_values(array_filter(array_map('trim', explode(',', $value))));
                if (count($tags) > 15 || collect($tags)->contains(fn ($tag) => mb_strlen($tag) > 50)) {
                    $fail('Use up to 15 tags, each containing at most 50 characters.');
                }
            }],
            'visibility' => ['required', Rule::enum(VideoVisibility::class)],
            'scheduled_at' => ['nullable', 'required_if:visibility,scheduled', 'date', 'after:now'],
            'age_restricted' => ['sometimes', 'boolean'],
            'comments_enabled' => ['sometimes', 'boolean'],
            'embedding_enabled' => ['sometimes', 'boolean'],
            'thumbnail' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'extensions:jpg,jpeg,png,webp', 'max:5120', 'dimensions:max_width=7680,max_height=4320'],
            'generated_thumbnail' => ['nullable', 'integer', Rule::in(array_keys($this->route('video')->generated_thumbnails ?? []))],
            'action' => ['required', Rule::in(['draft', 'publish'])],
        ];
    }
}
