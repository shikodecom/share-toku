<?php

namespace Tests\Feature;

use App\Models\ReferralOffer;
use App\Models\ReferralProgram;
use App\Models\Service;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PeriodValidationTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(string $kind): array
    {
        $user = User::factory()->create(['is_system_admin' => true]);
        $workspace = Workspace::create(['public_id' => (string) Str::ulid(), 'name' => 'Test', 'owner_user_id' => $user->id]);
        WorkspaceMember::create(['workspace_id' => $workspace->id, 'user_id' => $user->id, 'role' => 'administrator']);
        $service = Service::create(['public_id' => (string) Str::ulid(), 'name' => 'Service', 'slug' => 'test-'.$kind]);
        $this->actingAs($user);
        if ($kind === 'program') {
            return ['/master/programs', ['service_id' => $service->id, 'name' => 'Program',
                'public_listing_policy' => 'approved', 'external_distribution_policy' => 'approved'], ReferralProgram::class];
        }
        $program = ReferralProgram::create(['public_id' => (string) Str::ulid(), 'service_id' => $service->id, 'name' => 'Program']);

        return ['/workspaces/'.$workspace->public_id.'/offers', ['referral_program_id' => $program->id, 'referral_code' => 'CODE'], ReferralOffer::class];
    }

    public static function updates(): array
    {
        $cases = [
            'end before saved start' => [['ends_at' => '2026-10-09 00:00:00'], false],
            'start after saved end' => [['starts_at' => '2026-10-13 00:00:00'], false],
            'valid end only' => [['ends_at' => '2026-10-13 00:00:00'], true],
            'valid start only' => [['starts_at' => '2026-10-09 00:00:00'], true],
            'equal boundary' => [['ends_at' => '2026-10-10 00:00:00'], true],
            'both shifted' => [['starts_at' => '2026-10-14 00:00:00', 'ends_at' => '2026-10-15 00:00:00'], true],
            'both reversed' => [['starts_at' => '2026-10-15 00:00:00', 'ends_at' => '2026-10-14 00:00:00'], false],
            'clear start' => [['starts_at' => null, 'ends_at' => '2026-10-09 00:00:00'], true],
            'clear end' => [['ends_at' => null, 'starts_at' => '2026-10-13 00:00:00'], true],
        ];
        $data = [];
        foreach (['offer', 'program'] as $kind) {
            foreach ($cases as $name => [$period, $valid]) {
                $data[$kind.': '.$name] = [$kind, $period, $valid];
            }
        }

        return $data;
    }

    #[DataProvider('updates')]
    public function test_update_validates_the_resulting_period(string $kind, array $period, bool $valid): void
    {
        [$path, $base, $model] = $this->fixture($kind);
        $original = ['starts_at' => '2026-10-10 00:00:00', 'ends_at' => '2026-10-12 00:00:00'];
        $publicId = $this->postJson($path, $base + $original)->assertCreated()->json('public_id');
        $response = $this->patchJson($path.'/'.$publicId, $base + $period);
        if ($valid) {
            $response->assertOk();
        } else {
            $response->assertUnprocessable()->assertJsonValidationErrors('ends_at');
        }
        $record = $model::where('public_id', $publicId)->firstOrFail();
        $expected = $valid ? array_replace($original, $period) : $original;
        foreach ($expected as $field => $value) {
            $this->assertSame($value, $record->{$field}?->format('Y-m-d H:i:s'));
        }
    }

    public function test_store_allows_open_bounds_and_rejects_reversed_dates(): void
    {
        foreach (['offer', 'program'] as $kind) {
            [$path, $base] = $this->fixture($kind);
            $this->postJson($path, $base + ['starts_at' => null, 'ends_at' => '2026-10-10 00:00:00'])->assertCreated();
            $this->postJson($path, $base + ['starts_at' => '2026-10-10 00:00:00', 'ends_at' => null])->assertCreated();
            $this->postJson($path, $base + ['starts_at' => '2026-10-12 00:00:00', 'ends_at' => '2026-10-10 00:00:00'])
                ->assertUnprocessable()->assertJsonValidationErrors('ends_at');
        }
    }
}
