<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TeamMember extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'role', 'bio', 'avatar_initials', 'sort_order'
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];
}