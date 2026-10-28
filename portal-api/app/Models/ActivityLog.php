<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'role',
        'action',
        'subject_type',
        'subject_id',
        'description',
        'properties',
        'ip_address',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'properties' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function record(?User $user, string $action, string $description, ?Model $subject = null, ?array $properties = null, ?string $ip = null): self
    {
        return self::create([
            'user_id'      => $user?->id,
            'role'         => $user?->role,
            'action'       => $action,
            'subject_type' => $subject ? get_class($subject) : null,
            'subject_id'   => $subject?->getKey(),
            'description'  => $description,
            'properties'   => $properties,
            'ip_address'   => $ip,
            'created_at'   => now(),
        ]);
    }
}

