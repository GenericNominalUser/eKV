<?php

namespace App\Models;

use App\Models\UserProfile;
use App\Models\AnnouncementPost;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;
    protected $table = 'users';
    protected $primaryKey = "id";
    public $incrementing = true;
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'fullname',
        'username',
        'email',
        'password',
        'role'
    ];
    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    // Relationships
    public function profile(){
        return $this->hasOne(UserProfile::class, 'users_username');
    }
    public function announcementPosts(){
        return $this->hasMany(AnnouncementPost::class);
    }
}
