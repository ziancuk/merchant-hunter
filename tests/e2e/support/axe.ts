import AxeBuilder from '@axe-core/playwright';
import type { Page } from '@playwright/test';

/**
 * The single place for accessibility exceptions (TEST-PLAN layer 8: "pengecualian dicatat dengan alasan").
 * Keep this list empty unless a violation is a documented false positive; real app violations are reported in
 * tests/e2e/FINDINGS.md instead of being hidden here.
 */
export const DISABLED_RULES: { id: string; reason: string }[] = [];

/** CSS selectors excluded from scanning, each with a reason. */
export const EXCLUDED_SELECTORS: { selector: string; reason: string }[] = [
    {
        selector: '[data-testid="map-canvas"] canvas',
        reason: 'WebGL canvas (MapLibre/deck.gl) has no DOM semantics; the map is exposed through the HUD, the live region and the drawer, which ARE scanned.',
    },
];

export interface AxeViolation {
    id: string;
    impact: string | null | undefined;
    help: string;
    nodes: string[];
}

/** Scans the current page and returns serious/critical violations only (moderate/minor are logged by callers if wanted). */
export async function scan(page: Page, opts: { onlyRules?: string[] } = {}): Promise<{ blocking: AxeViolation[]; minor: AxeViolation[] }> {
    let builder = new AxeBuilder({ page });
    if (opts.onlyRules) builder = builder.withRules(opts.onlyRules);
    else builder = builder.withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'best-practice']).disableRules(DISABLED_RULES.map((r) => r.id));
    for (const e of EXCLUDED_SELECTORS) builder = builder.exclude(e.selector);
    const result = await builder.analyze();
    const map = (v: (typeof result.violations)[number]): AxeViolation => ({ id: v.id, impact: v.impact, help: v.help, nodes: v.nodes.slice(0, 5).map((n) => `${n.target.join(' ')} :: ${n.failureSummary?.split('\n')[1]?.trim() ?? ''}`) });
    const all = result.violations.map(map);
    return { blocking: all.filter((v) => v.impact === 'serious' || v.impact === 'critical'), minor: all.filter((v) => v.impact !== 'serious' && v.impact !== 'critical') };
}
