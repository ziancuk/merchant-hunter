<?php

use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(fn () => RateLimiter::clear('ayu@hunter.test|127.0.0.1'));

it('redirects guests to the login page', function (string $path) {
    $this->get($path)->assertRedirect('/login');
})->with(['/']);

it('shows the login page to guests', function () {
    $this->get('/login')->assertOk()->assertInertia(fn ($page) => $page->component('Auth/Login'));
});

it('lets a user log in and reach the map with shared props', function () {
    $user = User::factory()->create(['password' => 'secret-pass-1']);

    $this->post('/login', ['email' => $user->email, 'password' => 'secret-pass-1'])->assertRedirect('/');

    $this->get('/')->assertOk()->assertInertia(fn ($page) => $page
        ->component('Map/Index')
        ->where('auth.user.email', $user->email)
        ->has('app.basemap')
        ->where('app.ai_enabled', false));
});

it('rejects bad credentials with a validation error', function () {
    $user = User::factory()->create();

    $this->from('/login')->post('/login', ['email' => $user->email, 'password' => 'wrong'])
        ->assertRedirect('/login')->assertSessionHasErrors('email');
    $this->assertGuest();
});

it('rate limits login to 5 attempts per minute', function () {
    $user = User::factory()->create();

    foreach (range(1, 5) as $_) {
        $this->post('/login', ['email' => $user->email, 'password' => 'wrong']);
    }

    $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])->assertStatus(429);
});

it('logs out', function () {
    $this->actingAs(User::factory()->create())->post('/logout')->assertRedirect('/login');
    $this->assertGuest();
});

it('never exposes the password hash in shared props', function () {
    $this->actingAs(User::factory()->create())->get('/')
        ->assertInertia(fn ($page) => $page->missing('auth.user.password'));
});
