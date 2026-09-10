<?php

namespace App\Enums;

enum UploadStatus: string
{
    case Draft = 'draft';
    case Uploaded = 'uploaded';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
