<?php

namespace App\Domain\World;

use App\Models\District;
use App\Models\Location;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Turns a place mentioned in free-text owner direction ("go to 8th street")
 * into a real Location: first against what Genesis City already knows,
 * then against real BGC place names, then (for a real street/spot it has
 * never seen) via a bounded OpenStreetMap geocode.
 */
class LocationResolver
{
    // Mirrors frontend/src/lib/geo.ts GENESIS_MAP — keep both in sync.
    private const NORTH = 14.5578;

    private const SOUTH = 14.5472;

    private const WEST = 121.0432;

    private const EAST = 121.0594;

    /**
     * Real BGC place names already pinned to a seeded Genesis location slug.
     * Mirrors the osmName values in frontend/src/lib/geo.ts REAL_LANDMARKS.
     */
    private const OSM_ALIASES = [
        'serendra piazza' => 'garden-walk',
        'serendra' => 'luna-home',
        'the mind museum' => 'music-studio-03',
        'mind museum' => 'music-studio-03',
        'burgos circle' => 'writer-loft',
        'market! market!' => 'central-exchange',
        'market market' => 'central-exchange',
        'bonifacio high street' => 'service-arcade',
        'british school manila' => 'open-library',
        'british school' => 'open-library',
        '30th street' => 'meeting-lawn',
        'one bonifacio high street' => 'records-hall',
    ];

    public function resolveFromText(string $text): ?Location
    {
        $phrase = $this->extractPlacePhrase($text);
        if ($phrase === null) {
            return null;
        }

        return $this->matchSeeded($phrase) ?? $this->matchAlias($phrase) ?? $this->geocode($phrase);
    }

    private function extractPlacePhrase(string $text): ?string
    {
        // English ("go to", "at") and Taglish/Tagalog ("punta sa", "papunta sa") phrasing.
        if (! preg_match(
            '/\b(?:go(?:es)?|travel|head|walk|move|punta|pumunta|papunta)?\s*(?:to|at|in|sa)\s+(.+?)(?:\s+(?:and|at)\s+(?:meet|find|talk)|\s*,|\s+then\b|$)/i',
            trim($text),
            $matches,
        )) {
            return null;
        }

        $phrase = trim($matches[1], " \t\n\r\0\x0B.");
        $phrase = preg_replace('/^(the|a|an)\s+/i', '', $phrase) ?? $phrase;
        $phrase = $this->stripTrailingFillers($phrase);

        return $phrase !== '' ? $phrase : null;
    }

    /**
     * Trailing Tagalog particles ("ngayon", "na", "muna"...) aren't part of a
     * place name but land inside the captured phrase — strip them, or a
     * geocode query like "8th st BGC ngayon" silently returns nothing.
     */
    private function stripTrailingFillers(string $phrase): string
    {
        $fillers = ['ngayon', 'na lang', 'nalang', 'muna', 'agad', 'ulit', 'nga', 'po', 'na'];

        do {
            $before = $phrase;
            foreach ($fillers as $filler) {
                $phrase = preg_replace('/\s+'.preg_quote($filler, '/').'$/i', '', $phrase) ?? $phrase;
            }
            $phrase = rtrim($phrase);
        } while ($phrase !== $before && $phrase !== '');

        return $phrase;
    }

    private function matchSeeded(string $phrase): ?Location
    {
        $needle = mb_strtolower($phrase);

        return Location::query()
            ->with('district')
            ->get()
            ->first(function (Location $location) use ($needle) {
                $name = mb_strtolower($location->name);
                if (str_contains($name, $needle) || str_contains($needle, $name)) {
                    return true;
                }

                $district = mb_strtolower((string) $location->district?->name);

                return $district !== '' && (str_contains($district, $needle) || str_contains($needle, $district));
            });
    }

    private function matchAlias(string $phrase): ?Location
    {
        $needle = mb_strtolower($phrase);

        foreach (self::OSM_ALIASES as $alias => $slug) {
            if (str_contains($needle, $alias) || str_contains($alias, $needle)) {
                return Location::query()->where('slug', $slug)->first();
            }
        }

        return null;
    }

    private function geocode(string $phrase): ?Location
    {
        try {
            $response = Http::withHeaders([
                'User-Agent' => 'AIVVA-Genesis-City/1.0 (owner direction geocoding)',
            ])
                ->timeout(6)
                ->get('https://nominatim.openstreetmap.org/search', [
                    'format' => 'jsonv2',
                    'q' => $phrase.', Bonifacio Global City, Taguig',
                    'viewbox' => self::WEST.','.self::NORTH.','.self::EAST.','.self::SOUTH,
                    'bounded' => 1,
                    'limit' => 1,
                ]);
        } catch (Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $hit = $response->json('0');
        if (! is_array($hit) || ! isset($hit['lat'], $hit['lon'])) {
            return null;
        }

        return $this->createCustomSpot($phrase, (float) $hit['lat'], (float) $hit['lon']);
    }

    private function createCustomSpot(string $name, float $lat, float $lng): Location
    {
        [$x, $y] = $this->project($lat, $lng);

        $district = District::query()->firstOrCreate(
            ['slug' => 'custom-spots'],
            [
                'city_id' => District::query()->value('city_id'),
                'name' => 'Custom Spots',
                'theme' => 'meetup',
                'color' => '#22E3D0',
                'polygon' => [],
                'description' => 'Real BGC places resolved from owner directions.',
            ],
        );

        return Location::query()->create([
            'district_id' => $district->id,
            'name' => Str::title($name),
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'type' => 'meetup',
            'coord_x' => $x,
            'coord_y' => $y,
            'capacity' => 10,
            'services' => ['meetup'],
            'description' => 'A real BGC place resolved from an owner direction.',
        ]);
    }

    /**
     * @return array{0: float, 1: float} logical [x, y], 0-1000 by 0-640
     */
    private function project(float $lat, float $lng): array
    {
        $width = 1000.0;
        $height = 640.0;
        $nx = ($lng - self::WEST) / (self::EAST - self::WEST);
        $ny = (self::NORTH - $lat) / (self::NORTH - self::SOUTH);

        return [
            max(0.0, min($width, $nx * $width)),
            max(0.0, min($height, $ny * $height)),
        ];
    }
}
