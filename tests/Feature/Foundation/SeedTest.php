<?php

use App\Models\Category;
use App\Models\DataSource;
use App\Models\Prospect;
use App\Models\Province;
use App\Models\RejectionReason;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoUserSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

it('seeds reference data', function () {
    $this->seed(DatabaseSeeder::class);

    expect(Province::count())->toBe(38)
        ->and(Category::count())->toBe(12)
        ->and(RejectionReason::count())->toBe(6)
        ->and(DataSource::count())->toBe(3)
        ->and(User::count())->toBe(3);
    expect(DataSource::where('key', 'google')->first()->is_active)->toBeFalse();
    expect(DataSource::where('key', 'mock')->first()->is_active)->toBeFalse();
});

it('is idempotent', function () {
    $this->seed(DatabaseSeeder::class);
    $this->seed(DatabaseSeeder::class);

    expect(Province::count())->toBe(38)->and(User::count())->toBe(3);
});

it('does not create demo users in production', function () {
    app()->detectEnvironment(fn () => 'production');
    (new DemoUserSeeder)->run();

    expect(User::count())->toBe(0);
});

it('encrypts data source api keys at rest and hides them from serialisation', function () {
    $this->seed(DatabaseSeeder::class);
    $source = DataSource::where('key', 'google')->first();
    $source->update(['api_key' => 'AIzaSECRETKEY1234']);

    $raw = DB::table('data_sources')->where('id', $source->id)->value('api_key');

    expect($raw)->not->toContain('AIzaSECRETKEY1234')
        ->and($source->fresh()->api_key)->toBe('AIzaSECRETKEY1234')
        ->and($source->fresh()->keyHint())->toBe('…1234')
        ->and($source->fresh()->toArray())->not->toHaveKey('api_key');
});

it('keeps pipeline fields out of prospect mass assignment', function () {
    $prospect = new Prospect;

    expect($prospect->isFillable('pipeline_status'))->toBeFalse()
        ->and($prospect->isFillable('do_not_contact'))->toBeFalse()
        ->and($prospect->isFillable('handled_by'))->toBeFalse();
});

it('creates users via the artisan command', function () {
    $this->artisan('hunter:create-user', ['email' => 'x@y.test', 'name' => 'X', '--password' => 'longenough1'])
        ->assertSuccessful();

    expect(User::where('email', 'x@y.test')->exists())->toBeTrue();
});

it('registers the full schedule', function () {
    $this->artisan('schedule:list')->expectsOutputToContain('hunter:sync')->assertSuccessful();
});

it('uses the documented default demo password only on local, e2e and testing', function () {
    config(['hunter.demo_user_password' => '']);
    (new DemoUserSeeder)->run();

    expect(Hash::check('demo-password-123', User::where('email', 'ayu@hunter.test')->value('password')))->toBeTrue();
});

it('refuses to seed demo users without DEMO_USER_PASSWORD outside local, e2e and testing', function () {
    config(['hunter.demo_user_password' => '']);
    app()->detectEnvironment(fn () => 'staging');

    expect(fn () => (new DemoUserSeeder)->run())->toThrow(RuntimeException::class, 'DEMO_USER_PASSWORD wajib diisi');
    expect(User::count())->toBe(0);
});

it('uses an explicit DEMO_USER_PASSWORD in any non-production environment', function () {
    config(['hunter.demo_user_password' => 'a-strong-demo-secret']);
    app()->detectEnvironment(fn () => 'staging');
    (new DemoUserSeeder)->run();

    expect(Hash::check('a-strong-demo-secret', User::where('email', 'budi@hunter.test')->value('password')))->toBeTrue();
});
