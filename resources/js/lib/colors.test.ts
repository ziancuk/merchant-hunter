import { describe, expect, it } from 'vitest';
import { BAND_CLASSES, BAND_HEX, BAND_VAR, STATUS_CLASSES, scoreBand } from './colors';
import { BAND_LABEL, STATUS_LABEL } from './labels';
import { PIPELINE_STATUSES } from '@/types';

describe('colors', () => {
    it.each([
        [100, 'hot'],
        [75, 'hot'],
        [74, 'warm'],
        [55, 'warm'],
        [54, 'medium'],
        [35, 'medium'],
        [34, 'cold'],
        [0, 'cold'],
    ] as const)('score %i is %s', (score, band) => {
        expect(scoreBand(score)).toBe(band);
    });

    it('has tokens for every band and status', () => {
        for (const band of ['hot', 'warm', 'medium', 'cold'] as const) {
            expect(BAND_VAR[band]).toBe(`var(--c-${band})`);
            expect(BAND_CLASSES[band].text).toBe(`text-${band}`);
            expect(BAND_HEX.dark[band]).toMatch(/^#[0-9a-f]{6}$/);
            expect(BAND_HEX.light[band]).toMatch(/^#[0-9a-f]{6}$/);
            expect(BAND_LABEL[band]).toBeTruthy();
        }
        for (const s of PIPELINE_STATUSES) {
            expect(STATUS_LABEL[s]).toBeTruthy();
            expect(STATUS_CLASSES[s].text).toBe(`text-st-${s}`);
        }
    });
});
