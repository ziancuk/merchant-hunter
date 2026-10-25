<?php

use App\Models\DataSource;
use App\Services\Master\ProviderConnectionTester;
use App\Services\Providers\GoogleProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    $this->source = DataSource::create([
        'key' => 'google', 'name' => 'Google', 'is_active' => true, 'priority' => 5,
        'api_key' => 'AIza-secret-key-0001', 'monthly_quota' => 100, 'cost_search_per_1000' => 32.0,
    ]);
});

it('returns a generic message to the client and logs the detail without the key', function () {
    Http::fake(['places.googleapis.com/*' => Http::response(['error' => ['message' => 'Upstream said: AIza-secret-key-0001 is not valid']], 403)]);
    Log::spy();

    $status = app(GoogleProvider::class)->testConnection();

    expect($status->ok)->toBeFalse()
        ->and($status->message)->not->toContain('Upstream')->not->toContain('AIza')->not->toContain('403');
    Log::shouldHaveReceived('warning')->once()->withArgs(function (string $message, array $context) {
        return str_contains($message, 'Google') && str_contains($context['reason'], 'Upstream said') && ! str_contains(json_encode($context), 'AIza-secret-key-0001');
    });
});

it('hides transport errors from the client', function () {
    Http::fake(fn () => throw new ConnectionException('cURL error 28: https://places.googleapis.com timed out'));
    Log::spy();

    $status = app(GoogleProvider::class)->testConnection();

    expect($status->ok)->toBeFalse()->and($status->message)->not->toContain('cURL')->not->toContain('googleapis');
    Log::shouldHaveReceived('warning')->once();
});

it('reports quota exhaustion with a fixed message', function () {
    $this->source->forceFill(['calls_this_month' => 100])->save();

    $status = app(GoogleProvider::class)->testConnection();

    expect($status->ok)->toBeFalse()->and($status->message)->toBe('Kuota bulanan Google sudah habis.');
});

it('does not echo unexpected exception text from the connection tester', function () {
    $this->mock(GoogleProvider::class)->shouldReceive('testConnection')->andThrow(new RuntimeException('SQLSTATE secret internals'));
    app()->forgetInstance(ProviderConnectionTester::class);
    Log::spy();

    $status = app(ProviderConnectionTester::class)->test($this->source);

    expect($status->ok)->toBeFalse()->and($status->message)->not->toContain('SQLSTATE');
    Log::shouldHaveReceived('warning')->once();
});
