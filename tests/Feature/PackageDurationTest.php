<?php

namespace Tests\Feature;

use App\Models\Grade;
use App\Models\LessonAccess;
use App\Models\Package;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PackageDurationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'package_testing', 'database.connections.package_testing' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
        ], 'cache.default' => 'array', 'session.driver' => 'array']);
        foreach ([
            '2014_10_12_000000_create_users_table.php',
            '2026_03_24_163241_create_grades_table.php',
            '2026_03_24_163313_create_subjects_table.php',
            '2026_03_26_185603_create_lesson_access_table.php',
            '2026_06_04_000001_add_duration_price_and_expiry_to_lesson_access_table.php',
            '2026_04_16_161656_create_package_table.php',
            '2026_04_16_161737_create_package_items_table.php',
            '2026_06_04_000002_add_duration_price_multipliers_to_packages_table.php',
            '2026_06_04_000003_replace_package_duration_multipliers_with_prices.php',
            '2026_06_04_000004_replace_twelve_month_price_with_nine_month_price.php',
        ] as $migration) {
            (require database_path('migrations/'.$migration))->up();
        }
        Schema::table('users', fn (Blueprint $table) => $table->string('role')->default('student'));
        $this->travelTo(now()->startOfSecond());
    }

    protected function tearDown(): void
    {
        DB::purge('package_testing');
        parent::tearDown();
    }

    public function test_migration_preserves_three_term_prices_and_existing_access(): void
    {
        $grade = Grade::create(['name' => 'Grade 10']);
        $subject = Subject::create(['name' => 'Mathematics']);
        $id = DB::table('packages')->insertGetId([
            'name' => 'Full course', 'grade_id' => $grade->id, 'subject_id' => $subject->id,
            'nine_month_price' => 9750, 'three_month_price' => 3500, 'six_month_price' => 6600,
        ]);
        $access = LessonAccess::create([
            'user_id' => User::factory()->create()->id, 'grade_id' => $grade->id, 'subject_id' => $subject->id,
            'status' => 'accepted', 'duration_months' => 9, 'expires_at' => now()->addMonths(9),
        ]);
        $original = $access->getRawOriginal();
        (require database_path('migrations/2026_11_19_000001_restore_twelve_month_package_price.php'))->up();
        $package = Package::findOrFail($id);
        $this->assertSame('9750.00', $package->twelve_month_price);
        $this->assertSame('3500.00', $package->three_month_price);
        $this->assertSame('6600.00', $package->six_month_price);
        $this->assertEquals($original, $access->fresh()->getRawOriginal());
    }

    public function test_new_requests_support_twelve_months_and_expire_twelve_months_after_approval(): void
    {
        [$package, $item] = $this->package();
        $student = User::factory()->create();
        $grade = $package->grade;
        $subject = $package->subject;
        Sanctum::actingAs($student);
        $this->postJson('/api/lesson-access/request', [
            'subject_id' => $subject->id, 'grade_id' => $grade->id,
            'package_id' => $package->id, 'package_item_id' => $item->id,
            'duration_months' => 12, 'requested_price' => 1,
        ])->assertOk();
        $access = LessonAccess::firstOrFail();
        $this->assertEquals(12, $access->duration_months);
        $this->assertNull($access->expires_at);
        $this->assertSame('8775.00', $access->requested_price);
        Sanctum::actingAs(User::factory()->create(['role' => 'tutor']));
        $this->postJson('/api/lesson-access/accept', ['id' => $access->id])->assertOk();
        $this->assertTrue($access->fresh()->expires_at->equalTo(now()->addMonths(12)));
    }
    private function package(): array
    {
        (require database_path('migrations/2026_11_19_000001_restore_twelve_month_package_price.php'))->up();
        $grade = Grade::create(['name' => 'Grade 10']);
        $subject = Subject::create(['name' => 'Mathematics']);
        $package = Package::create([
            'name' => 'Full course', 'grade_id' => $grade->id, 'subject_id' => $subject->id,
            'base_price' => 0, 'three_month_price' => 3500, 'six_month_price' => 6600,
            'twelve_month_price' => 9750, 'twelve_month_discount_percent' => 10,
        ]);
        $item = $package->items()->create(['subject_id' => $subject->id, 'grade_id' => $grade->id,
            'price' => 3500, 'type' => 'subject']);
        return [$package, $item];
    }

    private function payload(Package $package): array
    {
        return ['name' => 'Updated package', 'grade_id' => $package->grade_id,
            'subject_id' => $package->subject_id, 'base_price' => 0,
            'three_month_price' => 1000, 'six_month_price' => 2000, 'twelve_month_price' => 3000,
            'three_month_discount_percent' => 5, 'six_month_discount_percent' => 10,
            'twelve_month_discount_percent' => 20,
            'items' => [['price' => 1000, 'grade_id' => null, 'subjects' => [$package->subject_id]]]];
    }

    public function test_tutor_can_save_and_reopen_independent_term_discounts(): void
    {
        [$package] = $this->package();
        Sanctum::actingAs(User::factory()->create(['role' => 'tutor']));
        $payload = $this->payload($package);
        $this->putJson('/api/packages/update/'.$package->id, $payload)->assertOk()
            ->assertJsonPath('three_month_discount_percent', '5.00')
            ->assertJsonPath('six_month_discount_percent', '10.00')
            ->assertJsonPath('twelve_month_discount_percent', '20.00');
        $saved = $package->fresh('items');
        $this->assertEquals([950.0], array_values($saved->pricesForDuration(3)));
        $this->assertEquals([1800.0], array_values($saved->pricesForDuration(6)));
        $this->assertEquals([2400.0], array_values($saved->pricesForDuration(12)));
        $this->assertEquals($package->grade_id, $saved->items->first()->grade_id);
        $this->postJson('/api/packages/store', $payload)->assertOk()
            ->assertJsonPath('twelve_month_price', '3000.00');
    }

    public function test_guests_and_students_cannot_change_package_prices(): void
    {
        [$package] = $this->package();
        $payload = $this->payload($package);
        $this->postJson('/api/packages/store', $payload)->assertUnauthorized();
        $this->putJson('/api/packages/update/'.$package->id, $payload)->assertUnauthorized();
        Sanctum::actingAs(User::factory()->create(['role' => 'student']));
        $this->postJson('/api/packages/store', $payload)->assertForbidden();
        $this->putJson('/api/packages/update/'.$package->id, $payload)->assertForbidden();
    }

    public function test_invalid_prices_and_discounts_do_not_modify_the_package(): void
    {
        [$package] = $this->package();
        Sanctum::actingAs(User::factory()->create(['role' => 'tutor']));
        foreach (['three_month_discount_percent' => -1, 'six_month_discount_percent' => 101,
            'twelve_month_discount_percent' => 'invalid', 'three_month_price' => -1] as $key => $value) {
            $payload = array_replace($this->payload($package), [$key => $value]);
            $this->putJson('/api/packages/update/'.$package->id, $payload)->assertUnprocessable()->assertJsonValidationErrors($key);
        }
        $this->assertSame('9750.00', $package->fresh()->twelve_month_price);
    }

    public function test_subject_shares_round_to_the_discounted_total_and_handle_zero_weights(): void
    {
        [$package] = $this->package();
        $package->items()->update(['price' => 0]);
        foreach (['Physics', 'Chemistry'] as $name) {
            $package->items()->create(['subject_id' => Subject::create(['name' => $name])->id,
                'grade_id' => $package->grade_id, 'price' => 0, 'type' => 'subject']);
        }
        $package->update(['three_month_price' => 100, 'three_month_discount_percent' => 0]);
        $this->assertEquals([33.33, 33.34, 33.33], array_values($package->fresh('items')->pricesForDuration(3)));
        $package->update(['three_month_discount_percent' => 100]);
        $this->assertEquals([0, 0, 0], array_values($package->fresh('items')->pricesForDuration(3)));
    }

    public function test_outdated_duration_and_foreign_item_are_rejected(): void
    {
        [$package, $item] = $this->package();
        Sanctum::actingAs(User::factory()->create());
        $payload = ['package_id' => $package->id, 'package_item_id' => $item->id, 'duration_months' => 9];
        $this->postJson('/api/lesson-access/request', [$payload])->assertUnprocessable();
        $payload['duration_months'] = 12;
        $payload['package_item_id'] = 9999;
        $this->postJson('/api/lesson-access/request', [$payload])->assertUnprocessable();
        $this->postJson('/api/lesson-access/request', ['subject_id' => $item->subject_id,
            'grade_id' => $item->grade_id, 'duration_months' => 12, 'requested_price' => 1])->assertUnprocessable();
        $this->assertSame(0, LessonAccess::count());
    }

    public function test_bulk_approval_keeps_each_duration_and_students_cannot_approve(): void
    {
        [$package, $item] = $this->package();
        $ids = [];
        foreach ([3, 6, 12] as $months) {
            $student = User::factory()->create();
            Sanctum::actingAs($student);
            $this->postJson('/api/lesson-access/request', [[
                'package_id' => $package->id, 'package_item_id' => $item->id, 'duration_months' => $months,
            ]])->assertOk();
            $ids[] = LessonAccess::where('user_id', $student->id)->firstOrFail()->id;
        }
        $this->postJson('/api/lesson-access/accept-multiple', ['ids' => $ids])->assertForbidden();
        $this->postJson('/api/lesson-access/accept', ['id' => $ids[0]])->assertForbidden();
        Sanctum::actingAs(User::factory()->create(['role' => 'tutor']));
        $this->postJson('/api/lesson-access/accept-multiple', ['ids' => $ids])->assertOk();
        foreach ($ids as $index => $id) {
            $months = [3, 6, 12][$index];
            $this->assertTrue(LessonAccess::findOrFail($id)->expires_at->equalTo(now()->addMonths($months)));
        }
    }

    public function test_one_request_can_mix_subject_durations_and_prices(): void
    {
        [$package, $math] = $this->package();
        $physics = $package->items()->create([
            'subject_id' => Subject::create(['name' => 'Physics'])->id,
            'grade_id' => $package->grade_id, 'price' => 3500, 'type' => 'subject',
        ]);
        Sanctum::actingAs(User::factory()->create());
        $this->postJson('/api/lesson-access/request', [
            ['package_id' => $package->id, 'package_item_id' => $math->id, 'duration_months' => 3, 'requested_price' => 1],
            ['package_id' => $package->id, 'package_item_id' => $physics->id, 'duration_months' => 12, 'requested_price' => 1],
        ])->assertOk();
        $requests = LessonAccess::orderBy('subject_id')->get();
        $this->assertCount(2, $requests);
        $this->assertEquals(3, $requests[0]->duration_months);
        $this->assertSame('1750.00', $requests[0]->requested_price);
        $this->assertEquals(12, $requests[1]->duration_months);
        $this->assertSame('4387.50', $requests[1]->requested_price);
    }

}
