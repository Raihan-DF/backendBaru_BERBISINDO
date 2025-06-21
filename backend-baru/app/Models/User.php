<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Notifications\CustomVerifyEmail;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'profile_photo',
        'bio',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Send the email verification notification.
     * Override untuk menggunakan custom notification
     */
    public function sendEmailVerificationNotification()
    {
        $this->notify(new CustomVerifyEmail);
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'user_roles');
    }

    public function materials()
    {
        return $this->hasMany(Material::class, 'created_by');
    }

    public function exercises()
    {
        return $this->hasMany(Exercise::class, 'created_by');
    }

    public function quizzes()
    {
        return $this->hasMany(Quiz::class, 'created_by');
    }

    public function progress()
    {
        return $this->hasMany(StudentProgress::class);
    }

    public function achievements()
    {
        return $this->belongsToMany(Achievement::class, 'student_achievements')
            ->withPivot('achieved_at')
            ->withTimestamps();
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    public function settings()
    {
        return $this->hasOne(Setting::class);
    }

    public function hasRole($role)
    {
        return $this->roles()->where('slug', $role)->exists();
    }

    public function isTeacher()
    {
        return $this->hasRole('teacher');
    }

    public function isStudent()
    {
        return $this->hasRole('student');
    }

    // Helper method to get user role names
    public function getRoleNames()
    {
        try {
            return $this->roles()->pluck('name')->toArray();
        } catch (\Exception $e) {
            return [];
        }
    }

    // Helper method to get user role slugs
    public function getRoleSlugs()
    {
        try {
            return $this->roles()->pluck('slug')->toArray();
        } catch (\Exception $e) {
            return [];
        }
    }

    // Debug method to check roles
    public function debugRoles()
    {
        try {
            $roles = $this->roles()->get();
            return $roles;
        } catch (\Exception $e) {
            return collect();
        }
    }

    /**
     * Check if user has verified email
     */
    public function hasVerifiedEmail()
    {
        return !is_null($this->email_verified_at);
    }

    /**
     * Mark email as verified
     */
    public function markEmailAsVerified()
    {
        return $this->forceFill([
            'email_verified_at' => $this->freshTimestamp(),
        ])->save();
    }

    /**
     * Get email for verification
     */
    public function getEmailForVerification()
    {
        return $this->email;
    }
}


// namespace App\Models;

// use Illuminate\Container\Attributes\Log;
// use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Illuminate\Foundation\Auth\User as Authenticatable;
// use Illuminate\Notifications\Notifiable;
// use Laravel\Sanctum\HasApiTokens;

// class User extends Authenticatable
// {
//     use HasApiTokens, HasFactory, Notifiable;

//     protected $fillable = [
//         'name',
//         'email',
//         'password',
//         'profile_photo',
//         'bio',
//     ];

//     protected $hidden = [
//         'password',
//         'remember_token',
//     ];

//     protected $casts = [
//         'password' => 'hashed',
//         'email_verified_at' => 'datetime',
//     ];

//     public function roles()
//     {
//         return $this->belongsToMany(Role::class, 'user_roles');
//     }

//     public function materials()
//     {
//         return $this->hasMany(Material::class, 'created_by');
//     }

//     public function exercises()
//     {
//         return $this->hasMany(Exercise::class, 'created_by');
//     }

//     public function quizzes()
//     {
//         return $this->hasMany(Quiz::class, 'created_by');
//     }

//     public function progress()
//     {
//         return $this->hasMany(StudentProgress::class);
//     }

//     public function achievements()
//     {
//         return $this->belongsToMany(Achievement::class, 'student_achievements')
//             ->withPivot('achieved_at')
//             ->withTimestamps();
//     }

//     public function notifications()
//     {
//         return $this->hasMany(Notification::class);
//     }

//     public function settings()
//     {
//         return $this->hasOne(Setting::class);
//     }

//     public function hasRole($role)
//     {
//         return $this->roles()->where('slug', $role)->exists();
//     }

//     public function isTeacher()
//     {
//         return $this->hasRole('teacher');
//     }

//     public function isStudent()
//     {
//         return $this->hasRole('student');
//     }

//     // Helper method to get user role names
//     public function getRoleNames()
//     {
//         try {
//             return $this->roles()->pluck('name')->toArray();
//         } catch (\Exception $e) {
//             // Log::error('Error getting role names: ' . $e->getMessage());
//             return [];
//         }
//     }

//     // Helper method to get user role slugs
//     public function getRoleSlugs()
//     {
//         try {
//             return $this->roles()->pluck('slug')->toArray();
//         } catch (\Exception $e) {
//             // Log::error('Error getting role slugs: ' . $e->getMessage());
//             return [];
//         }
//     }

//     // Debug method to check roles
//     public function debugRoles()
//     {
//         try {
//             $roles = $this->roles()->get();
//             // Log::info('User roles debug', [
//             //     'user_id' => $this->id,
//             //     'roles' => $roles->toArray(),
//             //     'role_count' => $roles->count()
//             // ]);
//             return $roles;
//         } catch (\Exception $e) {
//             // Log::error('Error debugging roles: ' . $e->getMessage());
//             return collect();
//         }
//     }
// }
