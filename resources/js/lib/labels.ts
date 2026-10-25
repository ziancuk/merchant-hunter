// Single source of Indonesian UI wording for statuses, columns, score bands and activity types (D-98).
import type { ActivityType, PipelineStatus, ScoreBand } from '@/types';

export const STATUS_LABEL: Record<PipelineStatus, string> = {
    new: 'Baru',
    contacted: 'Dihubungi',
    demo: 'Demo',
    won: 'Deal',
    lost: 'Ditolak',
};

/** Pipeline column headings use the same wording as statuses. */
export const COLUMN_LABEL: Record<PipelineStatus, string> = STATUS_LABEL;

export const BAND_LABEL: Record<ScoreBand, string> = { hot: 'Panas', warm: 'Hangat', medium: 'Sedang', cold: 'Dingin' };

export const SKIP_REASON_LABEL: Record<string, string> = {
    not_found: 'Prospek tidak ditemukan',
    already_opted_out: 'Sudah ditandai jangan hubungi',
    same_status: 'Status sudah sama',
};

export function skipReasonLabel(reason: string): string {
    return SKIP_REASON_LABEL[reason] ?? reason;
}

/** `rejected` is labelled "Ditolak" (same as the status), not an ambiguous variant. */
export const ACTIVITY_LABEL: Record<ActivityType, string> = {
    created: 'Ditemukan',
    status_change: 'Status berubah',
    contacted: 'Dihubungi',
    note: 'Catatan',
    opt_out: 'Opt-out',
    opt_in: 'Opt-in kembali',
    rejected: 'Ditolak',
    system: 'Sistem',
};

/** Scheduler run / per-outlet sync statuses (B-03). */
export const RUN_STATUS_LABEL: Record<string, string> = {
    success: 'Berhasil',
    completed: 'Selesai',
    ok: 'Berhasil',
    cached: 'Dari cache',
    partial: 'Sebagian gagal',
    failed: 'Gagal',
    running: 'Berjalan',
    skipped: 'Dilewati',
};

export function runStatusLabel(status: string | null | undefined): string {
    if (!status) return 'Belum pernah';
    return RUN_STATUS_LABEL[status] ?? status;
}
