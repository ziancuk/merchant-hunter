<?php

use App\Actions\ContactProspectAction;
use App\Exceptions\BusinessRuleException;
use App\Models\ProspectActivity;
use App\Services\Contact\ContactPolicy;
use Illuminate\Support\Facades\DB;

require_once __DIR__.'/helpers.php';

beforeEach(function () {
    $this->user = dm_login(['name' => 'Ayu']);
});

function cc_activity(int $prospectId, array $meta, string $type = 'contacted'): void
{
    ProspectActivity::forceCreate(['prospect_id' => $prospectId, 'type' => $type, 'meta' => $meta, 'created_at' => now()]);
}

it('counts overrides in SQL: only contacted activities flagged override=true', function () {
    $p = dm_prospect(['phone' => '081255550101']);
    $other = dm_prospect(['phone' => '081255550102']);
    cc_activity($p->id, ['override' => true, 'override_reason' => 'a']);
    cc_activity($p->id, ['override' => true, 'override_reason' => 'b']);
    cc_activity($p->id, ['override' => false]);
    cc_activity($p->id, ['message' => 'plain']);
    cc_activity($p->id, ['override' => true], 'note');
    cc_activity($other->id, ['override' => true]);

    expect(app(ContactPolicy::class)->overrideCount($p))->toBe(2);
});

it('does not load activity rows to count overrides', function () {
    $p = dm_prospect(['phone' => '081255550101']);
    cc_activity($p->id, ['override' => true]);
    DB::flushQueryLog();
    DB::enableQueryLog();

    app(ContactPolicy::class)->overrideCount($p);

    $sql = collect(DB::getQueryLog())->pluck('query')->implode(' | ');
    expect($sql)->toContain('count(*)')->and($sql)->not->toContain('select "meta"');
});

it('increments contact_count from the stored value even when the passed model is stale', function () {
    $p = dm_prospect(['phone' => '081255550101', 'contact_count' => 1, 'pipeline_status' => 'contacted', 'contacted_at' => now()->subDays(40), 'last_contacted_at' => now()->subDays(30)]);
    $stale = $p->fresh();
    DB::table('prospects')->where('id', $p->id)->update(['contact_count' => 5]); // another request got there first

    $result = app(ContactProspectAction::class)->handle($stale, $this->user, 'Halo');

    expect($result->contact_count)->toBe(6)->and(DB::table('prospects')->where('id', $p->id)->value('contact_count'))->toBe(6);
});

it('re-checks the interval against the locked fresh row, not the stale model', function () {
    $p = dm_prospect(['phone' => '081255550101']);
    $stale = $p->fresh(); // looks never-contacted
    DB::table('prospects')->where('id', $p->id)->update(['last_contacted_at' => now()->subDay(), 'contact_count' => 1]);

    expect(fn () => app(ContactProspectAction::class)->handle($stale, $this->user, 'Halo'))
        ->toThrow(BusinessRuleException::class);
    expect(DB::table('prospects')->where('id', $p->id)->value('contact_count'))->toBe(1);
});

it('persists the contact fields and enum status through the atomic increment', function () {
    $p = dm_prospect(['phone' => '081255550101']);

    $result = app(ContactProspectAction::class)->handle($p, $this->user, 'Halo');

    expect($result->pipeline_status->value)->toBe('contacted')->and($result->contact_count)->toBe(1)
        ->and($result->last_contacted_at)->not->toBeNull()->and($result->handled_by)->toBe($this->user->id)
        ->and(ProspectActivity::query()->where('prospect_id', $p->id)->where('type', 'contacted')->count())->toBe(1);
});
