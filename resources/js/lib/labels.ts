// Single source of UI wording for statuses, columns, score bands and activity types (D-98, D-132).
// The maps are Proxies over the i18n messages: reading `BAND_LABEL.hot` inside a render function tracks the active
// locale, so every existing `LABEL[key]` usage switches language without changes.
import { t } from '@/i18n';
import type { ActivityType, PipelineStatus, ScoreBand } from '@/types';

function labelMap<K extends string>(prefix: string): Record<K, string> {
    return new Proxy({} as Record<K, string>, {
        get: (_target, key) => (typeof key === 'string' ? t(`${prefix}.${key}`) : undefined),
        has: () => true,
    });
}

export const STATUS_LABEL = labelMap<PipelineStatus>('labels.status');

/** Pipeline column headings use the same wording as statuses. */
export const COLUMN_LABEL = STATUS_LABEL;

export const BAND_LABEL = labelMap<ScoreBand>('labels.band');

export const ACTIVITY_LABEL = labelMap<ActivityType>('labels.activity');

export function skipReasonLabel(reason: string): string {
    const key = `labels.skip.${reason}`;
    const text = t(key);
    return text === key ? reason : text;
}

/** Scheduler run / per-outlet sync statuses (B-03). */
export function runStatusLabel(status: string | null | undefined): string {
    if (!status) return t('labels.run.never');
    const key = `labels.run.${status}`;
    const text = t(key);
    return text === key ? status : text;
}
