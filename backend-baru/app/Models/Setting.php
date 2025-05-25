<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'email_notifications',
        'dark_mode',
        'language',
        'preferences',
    ];

    protected $casts = [
        'email_notifications' => 'boolean',
        'dark_mode' => 'boolean',
        'preferences' => 'json',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
