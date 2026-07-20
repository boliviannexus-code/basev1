<?php

namespace App\Models;

use App\Models\Concerns\AuditsCompanyChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable;

class RedCardArticle extends Model implements Auditable
{
    use AuditsCompanyChanges;

    protected $fillable = [
        'company_id',
        'number',
        'detail',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function sanctions(): HasMany
    {
        return $this->hasMany(RedCardSanction::class);
    }
}
