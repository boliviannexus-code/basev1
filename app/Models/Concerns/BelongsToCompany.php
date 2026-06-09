<?php

namespace App\Models\Concerns;

use App\Support\CompanyContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

trait BelongsToCompany
{
    public static function bootBelongsToCompany(): void
    {
        static::addGlobalScope('company', function (Builder $builder): void {
            CompanyContext::scope($builder, column: $builder->getModel()->qualifyColumn('company_id'));
        });

        static::creating(function (Model $model): void {
            $companyId = CompanyContext::id();

            if ($companyId !== null && $companyId > 0 && $model->getAttribute('company_id') === null) {
                $model->setAttribute('company_id', $companyId);
            }
        });
    }

    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->withoutGlobalScope('company')->where($query->getModel()->qualifyColumn('company_id'), $companyId);
    }
}
