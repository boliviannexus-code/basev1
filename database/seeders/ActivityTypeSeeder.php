<?php

namespace Database\Seeders;

use App\Models\ActivityType;
use Illuminate\Database\Seeder;

class ActivityTypeSeeder extends Seeder
{
    public function run(): void
    {
        $activityTypes = [
            [
                'title' => 'Transporte',
                'slug' => 'transporte',
                'icon' => 'ti-bus',
                'description' => 'Traslados, salidas, retornos y movimientos entre puntos del itinerario.',
            ],
            [
                'title' => 'Comida',
                'slug' => 'comida',
                'icon' => 'ti-tools-kitchen-2',
                'description' => 'Desayunos, almuerzos, cenas, snacks o degustaciones incluidas.',
            ],
            [
                'title' => 'Caminata',
                'slug' => 'caminata',
                'icon' => 'ti-walk',
                'description' => 'Recorridos a pie, senderismo y tramos de exploracion caminando.',
            ],
            [
                'title' => 'Visita',
                'slug' => 'visita',
                'icon' => 'ti-map-pin',
                'description' => 'Paradas en atractivos, miradores, comunidades, museos o puntos turisticos.',
            ],
            [
                'title' => 'Descanso',
                'slug' => 'descanso',
                'icon' => 'ti-bed',
                'description' => 'Pausas, tiempo libre y momentos de recuperacion durante el tour.',
            ],
            [
                'title' => 'Alojamiento',
                'slug' => 'alojamiento',
                'icon' => 'ti-building-cottage',
                'description' => 'Hoteles, hostales, refugios, campamentos u hospedajes incluidos.',
            ],
        ];

        foreach ($activityTypes as $activityType) {
            ActivityType::query()->updateOrCreate(
                ['slug' => $activityType['slug']],
                $activityType + ['is_active' => true]
            );
        }
    }
}
