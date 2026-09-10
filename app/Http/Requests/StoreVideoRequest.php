<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVideoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'channel_id' => ['required', 'integer', Rule::exists('channels', 'id')->where('user_id', $this->user()->id)],
            'video' => ['required', 'file', 'extensions:mp4,mov,webm,mkv', 'mimetypes:video/mp4,video/quicktime,video/webm,video/x-matroska', 'max:'.(config('betube.max_upload_mb') * 1024)],
        ];
    }
}
