import { describe, expect, it } from 'vitest';
import { ACTIVITY_LABEL, COLUMN_LABEL, STATUS_LABEL, runStatusLabel, skipReasonLabel } from './labels';

describe('labels (D-98)', () => {
    it('uses Deal/Ditolak for won/lost everywhere', () => {
        expect(STATUS_LABEL.won).toBe('Deal');
        expect(STATUS_LABEL.lost).toBe('Ditolak');
        expect(COLUMN_LABEL).toBe(STATUS_LABEL);
        expect(ACTIVITY_LABEL.rejected).toBe('Ditolak');
    });
    it('translates scheduler run statuses (B-03)', () => {
        expect(runStatusLabel('success')).toBe('Berhasil');
        expect(runStatusLabel('partial')).toBe('Sebagian gagal');
        expect(runStatusLabel('failed')).toBe('Gagal');
        expect(runStatusLabel(null)).toBe('Belum pernah');
        expect(runStatusLabel('weird')).toBe('weird');
    });
    it('falls back to the raw skip reason', () => {
        expect(skipReasonLabel('same_status')).toBe('Status sudah sama');
        expect(skipReasonLabel('x')).toBe('x');
    });
});
