<?php
/**
 * Single source for site-wide proof numbers, so pages stop repeating hard-coded copies.
 * Corridor count is live from the DB; years is computed from the founding year.
 * The customers figure is a business claim (no DB source) — change it here only.
 */
const SITE_FOUNDED_YEAR = 2019;
const SITE_CUSTOMERS_SERVED = '1,500+';

// One response-time promise for every public form, page and success message.
// Change it here only — and keep it something the team can actually meet.
const SITE_RESPONSE_PROMISE = 'within one business day';
// Accurate wording for the RERA claim: every project page states its RERA
// status (or says none is recorded yet), but we do not claim 100% pre-verification.
const SITE_RERA_NOTE = 'RERA status is shown on every project page. Always confirm it on the Haryana RERA website before you commit.';

function site_years_in_business(): int
{
    return max(1, (int)date('Y') - SITE_FOUNDED_YEAR);
}

function site_corridor_count(): int
{
    static $n = null;
    if ($n === null) {
        try {
            $n = count(get_active_corridors());
        } catch (Throwable $e) {
            $n = 0;
        }
    }
    return $n;
}

/** "8 corridors, covered in depth" — falls back to a count-free phrase if the DB is unreachable. */
function site_corridor_phrase(): string
{
    $n = site_corridor_count();
    return $n > 0 ? $n . ' corridors, covered in depth' : 'Gurgaon corridors, covered in depth';
}
