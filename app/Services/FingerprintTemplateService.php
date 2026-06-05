<?php

namespace App\Services;

use App\Models\FingerprintTemplate;
use App\Models\User;
use App\Support\CompanyContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class FingerprintTemplateService
{
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return FingerprintTemplate::query()
            ->with('user.company')
            ->whereHas('user', fn ($query) => CompanyContext::scope($query))
            ->latest()
            ->paginate($perPage);
    }

    public function usersForSelect(): Collection
    {
        return CompanyContext::scope(User::query())
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'company_id']);
    }

    public function create(array $data): FingerprintTemplate
    {
        return FingerprintTemplate::query()->create($this->normalize($data));
    }

    public function update(FingerprintTemplate $template, array $data): FingerprintTemplate
    {
        $this->ensureVisible($template);
        $template->update($this->normalize($data));

        return $template->refresh();
    }

    public function delete(FingerprintTemplate $template): bool
    {
        $this->ensureVisible($template);

        return (bool) $template->delete();
    }

    public function ensureVisible(FingerprintTemplate $template): void
    {
        $template->loadMissing('user');
        abort_unless(CompanyContext::belongsToUser($template->user?->company_id, auth()->user()), 403);
    }

    private function normalize(array $data): array
    {
        $data['template_data'] = trim((string) $data['template_data']);
        $data['format'] = blank($data['format'] ?? null) ? null : trim((string) $data['format']);

        return $data;
    }
}
