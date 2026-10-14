<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'nim', 'program_id', 'active', 'publication_consent', 'email_verified_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    public function assignments() { return $this->hasMany(Assignment::class); }
    public function assigned(string $role, ?int $club = null, ?int $event = null): bool {
        return $this->assignments()->where('role', $role)->where('starts_at', '<=', now())->where('ends_at', '>', now())
            ->when($club !== null, fn($q) => $q->where('club_id', $club))
            ->when($event !== null, fn($q) => $q->where('event_id', $event))->exists();
    }
    public function managesClub(int $club): bool { return $this->assigned('kemahasiswaan') || $this->assigned('pengurus', $club); }
    public function managesEvent(int $event): bool { return $this->assigned('kemahasiswaan') || $this->assigned('admin_event', null, $event); }
    public function isReviewer(): bool { return $this->assigned('kemahasiswaan') || $this->assigned('pengurus') || $this->assigned('admin_event'); }
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'active' => 'boolean',
            'publication_consent' => 'boolean',
        ];
    }
}
