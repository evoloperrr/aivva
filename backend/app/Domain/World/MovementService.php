<?php

namespace App\Domain\World;

use App\Enums\AivvaStatus;
use App\Models\Aivva;
use App\Models\Location;
use App\Models\TravelEvent;

class MovementService
{
    public function distance(Location $from, Location $to): float
    {
        return round(hypot($to->coord_x - $from->coord_x, $to->coord_y - $from->coord_y), 2);
    }

    public function startTravel(Aivva $aivva, Location $destination): TravelEvent
    {
        $from = $aivva->currentLocation;
        if (! $from) {
            $aivva->current_location_id = $destination->id;
            $aivva->status = AivvaStatus::Idle;
            $aivva->save();

            return TravelEvent::query()->create([
                'aivva_id' => $aivva->id,
                'from_location_id' => $destination->id,
                'to_location_id' => $destination->id,
                'distance' => 0,
                'world_minutes_duration' => 0,
                'started_at' => now(),
                'arrives_at' => now(),
                'completed_at' => now(),
                'status' => 'ARRIVED',
            ]);
        }

        if ($from->id === $destination->id) {
            return TravelEvent::query()->create([
                'aivva_id' => $aivva->id,
                'from_location_id' => $from->id,
                'to_location_id' => $destination->id,
                'distance' => 0,
                'world_minutes_duration' => 0,
                'started_at' => now(),
                'arrives_at' => now(),
                'completed_at' => now(),
                'status' => 'ARRIVED',
            ]);
        }

        $distance = max(1, $this->distance($from, $destination));
        $seconds = max(3, (int) ceil($distance * (float) config('aivva.travel_seconds_per_unit', 0.08)));
        $worldMinutes = max(4, (int) round($distance / 8));

        $aivva->destination_location_id = $destination->id;
        $aivva->status = AivvaStatus::Traveling;
        $aivva->save();

        return TravelEvent::query()->create([
            'aivva_id' => $aivva->id,
            'from_location_id' => $from->id,
            'to_location_id' => $destination->id,
            'distance' => $distance,
            'world_minutes_duration' => $worldMinutes,
            'started_at' => now(),
            'arrives_at' => now()->addSeconds($seconds),
            'status' => 'TRAVELING',
        ]);
    }

    public function completeIfDue(Aivva $aivva): ?TravelEvent
    {
        $travel = TravelEvent::query()
            ->where('aivva_id', $aivva->id)
            ->where('status', 'TRAVELING')
            ->latest()
            ->first();

        if (! $travel || ! $travel->hasArrived()) {
            return $travel;
        }

        $travel->status = 'ARRIVED';
        $travel->completed_at = now();
        $travel->save();

        $aivva->current_location_id = $travel->to_location_id;
        $aivva->destination_location_id = null;
        $aivva->status = AivvaStatus::Idle;
        $aivva->advanceWorldMinutes($travel->world_minutes_duration);
        $aivva->save();

        return $travel;
    }

    public function locationProgress(Aivva $aivva): array
    {
        $travel = TravelEvent::query()
            ->where('aivva_id', $aivva->id)
            ->where('status', 'TRAVELING')
            ->latest()
            ->first();

        if (! $travel) {
            return [
                'traveling' => false,
                'from' => $aivva->currentLocation?->toMapArray(),
                'to' => null,
                'progress' => 1,
            ];
        }

        $total = max(1, $travel->started_at->diffInSeconds($travel->arrives_at));
        $elapsed = $travel->started_at->diffInSeconds(now());
        $progress = min(1, max(0, $elapsed / $total));

        $from = $travel->fromLocation;
        $to = $travel->toLocation;

        return [
            'traveling' => true,
            'from' => $from?->toMapArray(),
            'to' => $to?->toMapArray(),
            'progress' => $progress,
            'x' => $from && $to ? $from->coord_x + ($to->coord_x - $from->coord_x) * $progress : $from?->coord_x,
            'y' => $from && $to ? $from->coord_y + ($to->coord_y - $from->coord_y) * $progress : $from?->coord_y,
            'arrives_at' => $travel->arrives_at->toIso8601String(),
        ];
    }
}
