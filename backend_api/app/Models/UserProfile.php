<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'headline',
        'mini_bio',
        'location',
        'equipped_avatar_frame_item_id',
        'equipped_profile_title_item_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function equippedAvatarFrameItem()
    {
        return $this->belongsTo(UserItem::class, 'equipped_avatar_frame_item_id');
    }

    public function equippedProfileTitleItem()
    {
        return $this->belongsTo(UserItem::class, 'equipped_profile_title_item_id');
    }
}
