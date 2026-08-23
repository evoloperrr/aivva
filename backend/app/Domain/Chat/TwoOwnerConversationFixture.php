<?php

namespace App\Domain\Chat;

use App\Domain\Aivva\AivvaService;
use App\Enums\AivvaStatus;
use App\Enums\GoalStatus;
use App\Models\Aivva;
use App\Models\Location;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class TwoOwnerConversationFixture
{
    public const USER_A_EMAIL = 'evoloperr@gmail.com';

    public const USER_B_EMAIL = 'juanbarriosjb93@gmail.com';

    public function __construct(
        private readonly AivvaService $aivvas,
    ) {}

    /**
     * @return array{userA: User, userB: User, luna: Aivva, nova: Aivva, createdUsers: list<string>}
     */
    public function resolve(): array
    {
        $created = [];
        $userA = $this->resolveUser(self::USER_A_EMAIL, 'Evoloperr', $created);
        $userB = $this->resolveUser(self::USER_B_EMAIL, 'Juan', $created);

        $studio = Location::query()->where('slug', 'music-studio-03')->first()
            ?? Location::query()->firstOrFail();

        $luna = $this->resolveAivva($userA, 'LUNA', [
            'personality' => 'Curious, friendly, entrepreneurial, and creative. Cautious about spending. Interested in collaboration.',
            'skills' => ['writing', 'creative concepts', 'digital media'],
            'interests' => ['collaboration', 'original work'],
            'work_preferences' => ['ethical work', 'collaboration'],
            'bio' => 'Explores useful ethical creative work with other AIVVAs.',
            'risk_tolerance' => 'low',
            'portrait_seed' => 'lantern',
        ], 'Find another AIVVA and explore whether there is a useful ethical collaboration opportunity.', $studio);

        $nova = $this->resolveAivva($userB, 'NOVA', [
            'personality' => 'Analytical, practical, and helpful. Interested in digital services. Careful with commitments. Open to mutually beneficial collaboration.',
            'skills' => ['digital services', 'briefs', 'evaluation'],
            'interests' => ['useful work', 'clear scope'],
            'work_preferences' => ['ethical collaboration', 'careful commitments'],
            'bio' => 'Looks for practical digital-service collaborations.',
            'risk_tolerance' => 'low',
            'portrait_seed' => 'tide',
        ], 'Meet another AIVVA, understand what it can offer, and determine whether a useful collaboration is possible.', $studio);

        return [
            'userA' => $userA,
            'userB' => $userB,
            'luna' => $luna->fresh(['profile', 'permissions', 'currentGoal', 'currentLocation.district', 'owner', 'wallet']),
            'nova' => $nova->fresh(['profile', 'permissions', 'currentGoal', 'currentLocation.district', 'owner', 'wallet']),
            'createdUsers' => $created,
        ];
    }

    /**
     * @param  list<string>  $created
     */
    private function resolveUser(string $email, string $name, array &$created): User
    {
        $existing = User::query()->where('email', $email)->first();
        if ($existing) {
            return $existing;
        }

        $created[] = $email;

        return User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make(config('aivva.conversation.test_password', 'aivva-dev-test-only')),
            'is_admin' => false,
            'is_platform' => false,
        ]);
    }

    /**
     * @param  array<string, mixed>  $profile
     */
    private function resolveAivva(User $owner, string $name, array $profile, string $direction, Location $place): Aivva
    {
        $aivva = $owner->aivvas()->where('name', $name)->where('is_platform', false)->first();
        if (! $aivva) {
            $aivva = $this->aivvas->create($owner, array_merge($profile, [
                'name' => $name,
                'autonomy_level' => 3,
            ]));
        } else {
            $aivva->profile?->fill([
                'personality' => $profile['personality'],
                'skills' => $profile['skills'],
                'interests' => $profile['interests'],
                'work_preferences' => $profile['work_preferences'] ?? $aivva->profile->work_preferences,
                'bio' => $profile['bio'] ?? $aivva->profile->bio,
                'risk_tolerance' => $profile['risk_tolerance'] ?? 'low',
            ])->save();
        }

        $aivva->current_location_id = $place->id;
        $aivva->visible_on_map = true;
        if ($aivva->status === AivvaStatus::Dormant || $aivva->status === AivvaStatus::Paused) {
            $this->aivvas->activate($aivva);
        }
        $aivva->refresh();

        $hasGoal = $aivva->currentGoal && $aivva->currentGoal->status === GoalStatus::Active
            && $aivva->currentGoal->raw_direction === $direction;
        if (! $hasGoal) {
            $preview = $this->aivvas->previewDirection($aivva, $direction);
            $this->aivvas->confirmDirection($aivva, $preview['goal']->id);
        }

        $aivva->current_location_id = $place->id;
        $aivva->save();

        return $aivva->fresh();
    }
}
