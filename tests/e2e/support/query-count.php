<?php

// Counts SQL queries (and wall time) for in-process GET requests as an authenticated user, without an HTTP server.
// Usage: APP_ENV=e2e DB_DATABASE=... php query-count.php <user-email> <uri> [<uri> ...]
// Output: JSON [{uri,status,queries,app_queries,ms,bytes}]; app_queries excludes framework infrastructure stored in the DB (session driver, throttle/cache store), whose count varies with warm/cold buckets. The user is attached directly to the guard, so the 2 session/user
// lookups a real browser request also pays are NOT included: add 2 to compare with a browser request.
use App\Models\User;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

$root = dirname(__DIR__, 3);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$app->instance('request', Request::create('/'));
Auth::guard('web')->setUser(User::where('email', $argv[1])->firstOrFail());
$count = 0;
$sessionQueries = 0;
DB::listen(function ($q) use (&$count, &$sessionQueries) {
    if (getenv('QC_VERBOSE')) {
        fwrite(STDERR, substr($q->sql, 0, 150)."\n");
    }
    $count++;
    if (str_contains($q->sql, '"sessions"') || str_contains($q->sql, '"cache"') || str_contains($q->sql, '"cache_locks"')) {
        $sessionQueries++;
    }
});

$out = [];
foreach (array_slice($argv, 2) as $uri) {
    $count = 0;
    $sessionQueries = 0;
    $request = Request::create($uri, 'GET', [], [], [], ['HTTP_ACCEPT' => 'application/json', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);
    $t = microtime(true);
    $response = $kernel->handle($request);
    $out[] = ['uri' => $uri, 'status' => $response->getStatusCode(), 'queries' => $count, 'app_queries' => $count - $sessionQueries, 'ms' => round((microtime(true) - $t) * 1000, 1), 'bytes' => strlen($response->getContent())];
}
echo json_encode($out);
