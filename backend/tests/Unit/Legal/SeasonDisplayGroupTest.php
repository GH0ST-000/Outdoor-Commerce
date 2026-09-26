<?php

declare(strict_types=1);

use App\Domains\Legal\Support\DisputedOpeningWindow;
use App\Domains\Legal\Support\SeasonDisplayGroup;

it('groups annex birds by kind', function (): void {
    expect(SeasonDisplayGroup::key('Anas acuta'))->toBe('waterfowl')
        ->and(SeasonDisplayGroup::key('Fulica atra'))->toBe('waterfowl')
        ->and(SeasonDisplayGroup::key('Coturnix coturnix'))->toBe('quail')
        ->and(SeasonDisplayGroup::key('Gallinago gallinago'))->toBe('snipe')
        ->and(SeasonDisplayGroup::key('Scolopax rusticola'))->toBe('woodcock')
        ->and(SeasonDisplayGroup::key('Columba palumbus'))->toBe('pigeons')
        ->and(SeasonDisplayGroup::key('Streptopelia turtur'))->toBe('pigeons')
        ->and(SeasonDisplayGroup::rank('quail'))->toBeLessThan(SeasonDisplayGroup::rank('pigeons'));
});

it('includes september in the disputed opening union and excludes july', function (): void {
    expect(DisputedOpeningWindow::overlaps('2026-09-21', '2026-09-27'))->toBeTrue()
        ->and(DisputedOpeningWindow::overlaps('2026-07-01', '2026-07-07'))->toBeFalse()
        ->and(DisputedOpeningWindow::overlaps('2027-02-20', '2027-02-27'))->toBeTrue()
        ->and(DisputedOpeningWindow::overlaps('2027-04-01', '2027-04-07'))->toBeFalse();
});
