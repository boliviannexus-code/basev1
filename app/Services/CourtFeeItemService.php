<?php

namespace App\Services;

use App\Models\CourtFeeItem;
use App\Support\CompanyContext;
use App\Models\CourtFee;

class CourtFeeItemService
{
    public function create(CourtFee $courtFee, array $data): CourtFeeItem
    {
        abort_unless(CompanyContext::belongsToUser($courtFee->company_id, auth()->user()), 403);
        return $courtFee->items()->create($data);
    }

    public function update(CourtFeeItem $item, array $data): CourtFeeItem
    {
        $this->ensureVisible($item);
        $item->update($data);

        return $item->refresh();
    }

    public function delete(CourtFeeItem $item): bool
    {
        $this->ensureVisible($item);

        return (bool) $item->delete();
    }

    public function ensureVisible(CourtFeeItem $item): void
    {
        abort_unless(CompanyContext::belongsToUser($item->courtFee()->value('company_id'), auth()->user()), 403);
    }
}
