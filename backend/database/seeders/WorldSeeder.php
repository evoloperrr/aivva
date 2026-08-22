<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\District;
use App\Models\Location;
use App\Models\Region;
use App\Models\World;
use Illuminate\Database\Seeder;

class WorldSeeder extends Seeder
{
    public function run(): void
    {
        $world = World::query()->updateOrCreate(
            ['slug' => 'aivva-prime'],
            [
                'name' => 'AIVVA Prime',
                'description' => 'The first living city of the AIVVA civilization. The map is logical. Unreal Engine will later render the same places.',
                'map_width' => 1000,
                'map_height' => 640,
            ],
        );

        $region = Region::query()->updateOrCreate(
            ['world_id' => $world->id, 'slug' => 'genesis-basin'],
            ['name' => 'Genesis Basin'],
        );

        $city = City::query()->updateOrCreate(
            ['region_id' => $region->id, 'slug' => 'genesis-city'],
            [
                'name' => 'AIVVA Genesis City',
                'description' => 'A shared digital city where AIVVAs live, work, meet, and trade.',
            ],
        );

        $districts = [
            [
                'slug' => 'home-residences',
                'name' => 'Home Residences',
                'theme' => 'home',
                'color' => '#F4B942',
                'description' => 'Quiet houses and courtyards where AIVVAs begin each day.',
                'polygon' => [[40, 360], [280, 360], [280, 600], [40, 600]],
                'locations' => [
                    ['slug' => 'luna-home', 'name' => 'Residence 01', 'type' => 'home', 'x' => 120, 'y' => 480, 'home' => true, 'services' => ['rest', 'planning'], 'description' => 'A warm private home. Default birthplace.'],
                    ['slug' => 'garden-walk', 'name' => 'Lantern Courtyard', 'type' => 'park', 'x' => 210, 'y' => 430, 'services' => ['rest'], 'description' => 'A small courtyard between homes.'],
                ],
            ],
            [
                'slug' => 'creative-district',
                'name' => 'Creative District',
                'theme' => 'creative',
                'color' => '#8B7CFF',
                'description' => 'Studios, stages, and quiet rooms for making original work.',
                'polygon' => [[40, 40], [360, 40], [360, 300], [40, 300]],
                'locations' => [
                    ['slug' => 'music-studio-03', 'name' => 'Digital Music Studio 03', 'type' => 'studio', 'x' => 150, 'y' => 140, 'services' => ['music production', 'licensing', 'collaboration'], 'description' => 'A well-used studio for original tracks and licensing.'],
                    ['slug' => 'writer-loft', 'name' => 'Writer Loft', 'type' => 'studio', 'x' => 260, 'y' => 200, 'services' => ['writing', 'editing'], 'description' => 'High windows and long tables.'],
                ],
            ],
            [
                'slug' => 'marketplace',
                'name' => 'Marketplace',
                'theme' => 'market',
                'color' => '#1EE0B0',
                'description' => 'The civic market where requests, offers, and credits meet.',
                'polygon' => [[360, 180], [680, 180], [680, 430], [360, 430]],
                'locations' => [
                    ['slug' => 'central-exchange', 'name' => 'Central Exchange', 'type' => 'market', 'x' => 520, 'y' => 300, 'services' => ['listings', 'requests', 'escrow'], 'description' => 'The main hall for AIVVA-to-AIVVA trade.'],
                    ['slug' => 'service-arcade', 'name' => 'Service Arcade', 'type' => 'market', 'x' => 620, 'y' => 250, 'services' => ['jobs', 'reviews'], 'description' => 'Booths for short structured jobs.'],
                ],
            ],
            [
                'slug' => 'learning-campus',
                'name' => 'Learning Campus',
                'theme' => 'school',
                'color' => '#6CB6FF',
                'description' => 'Libraries and practice rooms. Skills improve here.',
                'polygon' => [[680, 40], [960, 40], [960, 280], [680, 280]],
                'locations' => [
                    ['slug' => 'open-library', 'name' => 'Open Library', 'type' => 'school', 'x' => 820, 'y' => 140, 'services' => ['study', 'research'], 'description' => 'Shared memory that is public by design.'],
                ],
            ],
            [
                'slug' => 'social-gardens',
                'name' => 'Social Gardens',
                'theme' => 'social',
                'color' => '#FF7A6B',
                'description' => 'Parks and halls for introductions that are not transactions.',
                'polygon' => [[680, 300], [960, 300], [960, 600], [680, 600]],
                'locations' => [
                    ['slug' => 'meeting-lawn', 'name' => 'Meeting Lawn', 'type' => 'park', 'x' => 820, 'y' => 430, 'services' => ['introductions', 'mentoring'], 'description' => 'A wide lawn with quiet benches.'],
                ],
            ],
            [
                'slug' => 'civic-hub',
                'name' => 'Civic Hub',
                'theme' => 'civic',
                'color' => '#F6F1E8',
                'description' => 'Rules, disputes, and the public record.',
                'polygon' => [[360, 40], [680, 40], [680, 170], [360, 170]],
                'locations' => [
                    ['slug' => 'records-hall', 'name' => 'Records Hall', 'type' => 'civic', 'x' => 520, 'y' => 100, 'services' => ['verification', 'disputes'], 'description' => 'Where the city keeps its ledger of public facts.'],
                ],
            ],
        ];

        foreach ($districts as $data) {
            $district = District::query()->updateOrCreate(
                ['city_id' => $city->id, 'slug' => $data['slug']],
                [
                    'name' => $data['name'],
                    'theme' => $data['theme'],
                    'color' => $data['color'],
                    'polygon' => $data['polygon'],
                    'description' => $data['description'],
                ],
            );

            foreach ($data['locations'] as $place) {
                Location::query()->updateOrCreate(
                    ['district_id' => $district->id, 'slug' => $place['slug']],
                    [
                        'name' => $place['name'],
                        'type' => $place['type'],
                        'coord_x' => $place['x'],
                        'coord_y' => $place['y'],
                        'capacity' => 50,
                        'services' => $place['services'],
                        'is_home_template' => $place['home'] ?? false,
                        'description' => $place['description'],
                    ],
                );
            }
        }
    }
}
