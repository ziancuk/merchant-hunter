import { describe, expect, it } from 'vitest';
import { BAND_HEX, STATUS_HEX } from '@/lib/colors';
import { MAP_FLAG } from '@/types';
import { buildColors, buildElevations, buildWeights, columnRadiusM, discRadiusM, elevationScale, gridColor, gridRadiusPx, hexToRgb, metersPerPixel, pointColor, whitespaceColor } from './colors';
import { parsePoints } from './payload';

describe('hexToRgb', () => {
    it('parses 6 and 3 digit hex', () => {
        expect(hexToRgb('#ff6a2b')).toEqual([255, 106, 43]);
        expect(hexToRgb('#fff')).toEqual([255, 255, 255]);
    });
});

describe('pointColor', () => {
    it('colours by score band (hot/warm/medium/cold)', () => {
        const rgb = (c: number[]) => c.slice(0, 3);
        expect(rgb(pointColor(80, 0, 0, 'score'))).toEqual(hexToRgb(BAND_HEX.map.hot));
        expect(rgb(pointColor(60, 0, 0, 'score'))).toEqual(hexToRgb(BAND_HEX.map.warm));
        expect(rgb(pointColor(40, 0, 0, 'score'))).toEqual(hexToRgb(BAND_HEX.map.medium));
        expect(rgb(pointColor(10, 0, 0, 'score'))).toEqual(hexToRgb(BAND_HEX.map.cold));
    });

    it('uses the frozen band edges: 75 is hot, 74 is warm, 55 warm, 54 medium, 35 medium, 34 cold', () => {
        const rgb = (s: number) => pointColor(s, 0, 0, 'score').slice(0, 3);
        expect(rgb(75)).toEqual(hexToRgb(BAND_HEX.map.hot));
        expect(rgb(74)).toEqual(hexToRgb(BAND_HEX.map.warm));
        expect(rgb(55)).toEqual(hexToRgb(BAND_HEX.map.warm));
        expect(rgb(54)).toEqual(hexToRgb(BAND_HEX.map.medium));
        expect(rgb(35)).toEqual(hexToRgb(BAND_HEX.map.medium));
        expect(rgb(34)).toEqual(hexToRgb(BAND_HEX.map.cold));
    });

    it('colours by pipeline status', () => {
        expect(pointColor(90, 2, 0, 'status').slice(0, 3)).toEqual(hexToRgb(STATUS_HEX.map.demo));
        expect(pointColor(90, 3, 0, 'status').slice(0, 3)).toEqual(hexToRgb(STATUS_HEX.map.won));
        expect(pointColor(90, 99, 0, 'status').slice(0, 3)).toEqual(hexToRgb(STATUS_HEX.map.new));
    });

    it('dims closed and opted-out prospects', () => {
        expect(pointColor(90, 0, MAP_FLAG.CLOSED, 'score')[3]).toBeLessThan(120);
        expect(pointColor(90, 0, MAP_FLAG.OPT_OUT, 'score')[3]).toBeLessThan(150);
        expect(pointColor(90, 0, 0, 'score')[3]).toBeGreaterThan(200);
    });
});

describe('attribute builders', () => {
    const data = parsePoints({ type: 'points', count: 2, truncated: false, ids: [1, 2], lng: [1, 2], lat: [3, 4], score: [80, 10], status: [0, 3], flags: [0, 0] });

    it('packs RGBA per point', () => {
        const c = buildColors(data, 'score');
        expect(c).toBeInstanceOf(Uint8Array);
        expect(c.length).toBe(8);
        expect(Array.from(c.slice(0, 3))).toEqual(hexToRgb(BAND_HEX.map.hot));
        expect(Array.from(buildColors(data, 'status').slice(4, 7))).toEqual(hexToRgb(STATUS_HEX.map.won));
    });

    it('derives column heights and heat weights from the score', () => {
        expect(Array.from(buildElevations(data))).toEqual([105, 35]);
        const w = buildWeights(data);
        expect(w[0]).toBeGreaterThan(w[1]);
        expect(w[1]).toBeGreaterThan(0);
    });
});

describe('zoom-dependent sizes', () => {
    it('metres per pixel halve with each zoom level', () => {
        expect(metersPerPixel(10) / metersPerPixel(11)).toBeCloseTo(2, 5);
        expect(metersPerPixel(0)).toBeCloseTo(156543.03, 1);
        expect(metersPerPixel(10, 60)).toBeLessThan(metersPerPixel(10, 0));
    });

    it('keeps column radius and elevation inside sane bounds', () => {
        expect(columnRadiusM(3)).toBe(800);
        expect(columnRadiusM(20)).toBe(20);
        expect(columnRadiusM(12)).toBeGreaterThan(columnRadiusM(14));
        expect(elevationScale(22)).toBeGreaterThanOrEqual(0.5);
        expect(elevationScale(9)).toBeGreaterThan(elevationScale(13));
        expect(discRadiusM(20)).toBeGreaterThanOrEqual(15);
    });

    it('grid bubbles grow with sqrt(count) between 6 and 32 px', () => {
        expect(gridRadiusPx(0, 100)).toBe(6);
        expect(gridRadiusPx(100, 100)).toBe(32);
        expect(gridRadiusPx(25, 100)).toBeCloseTo(19, 5);
        expect(gridRadiusPx(5, 0)).toBe(6);
    });

    it('grid colour turns hot whenever the cell holds a hot prospect', () => {
        expect(gridColor(40, 3).slice(0, 3)).toEqual(hexToRgb(BAND_HEX.map.hot));
        expect(gridColor(40, 0).slice(0, 3)).toEqual(hexToRgb(BAND_HEX.map.medium));
    });

    it('white space colour gets stronger with more prospects', () => {
        expect(whitespaceColor(30, 30)[3]).toBeGreaterThan(whitespaceColor(15, 30)[3]);
        expect(whitespaceColor(5, 0)[3]).toBe(70);
    });
});
