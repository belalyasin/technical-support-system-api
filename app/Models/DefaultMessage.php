<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rule;

class DefaultMessage extends Model
{
    protected $fillable = [
        'body', 'user_id', 'parent_id'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(DefaultMessage::class, 'parent_id', 'id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(DefaultMessage::class, 'parent_id', 'id');
    }

    public function scopeFilter(Builder $builder, $filters): void
    {
        $builder->when($filters['body'] ?? false, function (Builder $builder, $value) {
            $builder->where('default_messages.body', 'like', '%' . $value . '%');
        });
    }

    public static function rules($id = 0)
    {
        return [
            'body' => ['required',
                'string',
                'min:3',
                'max:255',
                Rule::unique('default_messages', 'body')->ignore($id),
            ],
            'parent_id' => ['nullable', 'integer', 'exists:default_messages,id'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }
}
