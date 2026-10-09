<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

//    public mixed $verification_code;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'verification_code',
        'verification_code_expires_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'verification_code_expires_at' => 'datetime',
        'password' => 'hashed',
    ];

    /**
     * Roles are stored under the "user" guard. Naming it here keeps role lookups working
     * when a request is authenticated through Sanctum, which switches the default guard.
     */
    protected $guard_name = 'user';

    public const STAFF_ROLES = ['super admin', 'admin'];

    /**
     * Support staff can see every conversation and manage users and predefined messages.
     */
    public function isStaff(): bool
    {
        return $this->hasAnyRole(self::STAFF_ROLES);
    }

    /**
     * Store a fresh 4-digit verification code (hashed) and return the plain code so it can be sent.
     */
    public function issueVerificationCode(): string
    {
        $code = (string) random_int(1000, 9999);

        $this->verification_code = Hash::make($code);
        $this->verification_code_expires_at = now()->addMinutes(15);
        $this->save();

        return $code;
    }

    public function verificationCodeMatches(?string $code): bool
    {
        return $code !== null
            && $this->verification_code !== null
            && $this->verification_code_expires_at !== null
            && $this->verification_code_expires_at->isFuture()
            && Hash::check($code, $this->verification_code);
    }

    public function clearVerificationCode(): void
    {
        $this->verification_code = null;
        $this->verification_code_expires_at = null;
    }

    /**
     * Conversations the user takes part in, as customer or as staff.
     */
    public function joinedConversations(): BelongsToMany
    {
        return $this->belongsToMany(Conversation::class)
            ->withPivot('read_at')
            ->withTimestamps();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function details(): HasMany
    {
        return $this->hasMany(Details::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function default_messages(): HasMany
    {
        return $this->hasMany(DefaultMessage::class);
    }
}
