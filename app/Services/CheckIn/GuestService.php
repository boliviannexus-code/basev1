<?php

namespace App\Services\CheckIn;

use App\Models\Guest;

class GuestService
{
    public function findOrCreateHolder(int $companyId, array $data, ?int $createdBy = null): Guest
    {
        $payload = [
            'company_id' => $companyId,
            'document_type' => $data['document_type'],
            'document_number' => $data['document_number'] ?? null,
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'birth_country_id' => $data['birth_country_id'],
            'birth_date' => $data['birth_date'] ?? null,
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'created_by' => $createdBy,
        ];

        if (filled($payload['document_number'])) {
            $guest = Guest::query()
                ->where('company_id', $companyId)
                ->where('document_type', $payload['document_type'])
                ->where('document_number', $payload['document_number'])
                ->first();

            if ($guest) {
                $guest->update(array_filter($payload, fn ($value) => $value !== null));

                return $guest->refresh();
            }
        }

        return Guest::query()->create($payload);
    }

    public function createStayGuest(int $companyId, array $data, ?int $createdBy = null): Guest
    {
        if (filled($data['document_number'] ?? null)) {
            $guest = Guest::query()
                ->where('company_id', $companyId)
                ->where('document_type', $data['document_type'] ?? 'passport')
                ->where('document_number', $data['document_number'])
                ->first();

            if ($guest) {
                $guest->update([
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'],
                    'birth_country_id' => $data['birth_country_id'],
                    'birth_date' => $data['birth_date'],
                ]);

                return $guest->refresh();
            }
        }

        return Guest::query()->create([
            'company_id' => $companyId,
            'document_type' => $data['document_type'] ?? 'passport',
            'document_number' => $data['document_number'] ?? null,
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'birth_country_id' => $data['birth_country_id'],
            'birth_date' => $data['birth_date'],
            'created_by' => $createdBy,
        ]);
    }
}
