<?php

namespace App\Enums;

enum VideoVisibility: string
{
    case Public = 'public';
    case Unlisted = 'unlisted';
    case Private = 'private';
    case Scheduled = 'scheduled';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
