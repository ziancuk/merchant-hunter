import { describe, expect, it } from 'vitest';
import { daysAgoText, formatCoordinates, formatDate, formatDateTime, formatDistance, formatNumber, formatPercent, formatRupiah, relativeTime } from './format';

describe('format', () => {
    it('formats numbers with Indonesian grouping', () => {
        expect(formatNumber(12345)).toBe('12.345');
        expect(formatNumber(4.6, 1)).toBe('4,6');
        expect(formatNumber(null)).toBe('–');
    });

    it('formats percent', () => {
        expect(formatPercent(0.034)).toBe('3,4%');
        expect(formatPercent(undefined)).toBe('–');
    });

    it('formats distance in m and km', () => {
        expect(formatDistance(640)).toBe('640 m');
        expect(formatDistance(999.6)).toBe('1000 m');
        expect(formatDistance(1450)).toBe('1,5 km');
        expect(formatDistance(12000)).toBe('12 km');
        expect(formatDistance(null)).toBe('–');
    });

    it('formats dates in Asia/Jakarta', () => {
        // 2026-10-24T18:00Z is 25 Oct 01:00 WIB
        expect(formatDate('2026-10-24T18:00:00Z')).toBe('25 Okt 2026');
        expect(formatDateTime('2026-10-24T18:00:00Z')).toBe('25 Okt 2026, 01.00 WIB');
        expect(formatDateTime('2026-10-24T01:05:00Z')).toBe('24 Okt 2026, 08.05 WIB');
        expect(formatDate('not a date')).toBe('–');
        expect(formatDate(null)).toBe('–');
    });

    it('formats whole days ago without "0 hari lalu"', () => {
        expect(daysAgoText(0)).toBe('hari ini');
        expect(daysAgoText(1)).toBe('kemarin');
        expect(daysAgoText(2)).toBe('2 hari lalu');
    });

    it('shows a tiny non-zero share as "<0,1%" instead of "0,0%"', () => {
        expect(formatPercent(0.0004)).toBe('<0,1%');
        expect(formatPercent(0)).toBe('0,0%');
        expect(formatPercent(0.0006)).toBe('0,1%');
        expect(formatPercent(0.034)).toBe('3,4%');
    });

    it('formats relative time in Indonesian', () => {
        const now = Date.parse('2026-10-24T12:00:00Z');
        const ago = (ms: number) => new Date(now - ms).toISOString();
        expect(relativeTime(ago(10_000), now)).toBe('baru saja');
        expect(relativeTime(ago(5 * 60_000), now)).toBe('5 menit lalu');
        expect(relativeTime(ago(3 * 3_600_000), now)).toBe('3 jam lalu');
        expect(relativeTime(ago(30 * 3_600_000), now)).toBe('kemarin');
        expect(relativeTime(ago(3 * 86_400_000), now)).toBe('3 hari lalu');
        expect(relativeTime(ago(14 * 86_400_000), now)).toBe('2 minggu lalu');
        expect(relativeTime(ago(90 * 86_400_000), now)).toBe('3 bulan lalu');
        expect(relativeTime(new Date(now + 2 * 3_600_000).toISOString(), now)).toBe('dalam 2 jam');
        expect(relativeTime(null, now)).toBe('–');
    });

    it('formats rupiah and treats a missing amount as zero', () => {
        expect(formatRupiah(1250000)).toBe('Rp 1.250.000');
        expect(formatRupiah(undefined)).toBe('Rp 0');
    });

    it('formats coordinates', () => {
        expect(formatCoordinates(-6.21, 106.81)).toBe('-6.21000, 106.81000');
    });
});
