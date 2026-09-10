<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSetting extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'active_channel_id', 'theme'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function activeChannel(): BelongsTo
    {
        return $this->belongsTo(Channel::class, 'active_channel_id');
    }
}
