import type { PipelineStatus, ScoreBand } from '@/types';

/** Band thresholds are frozen in CONTRACTS.md: hot >= 75, warm 55-74, medium 35-54, cold < 35. */
export function scoreBand(score: number): ScoreBand {
    if (score >= 75) return 'hot';
    if (score >= 55) return 'warm';
    if (score >= 35) return 'medium';
    return 'cold';
}

/** CSS variable references (theme-aware). */
export const BAND_VAR: Record<ScoreBand, string> = {
    hot: 'var(--c-hot)',
    warm: 'var(--c-warm)',
    medium: 'var(--c-medium)',
    cold: 'var(--c-cold)',
};

export const STATUS_VAR: Record<PipelineStatus, string> = {
    new: 'var(--c-st-new)',
    contacted: 'var(--c-st-contacted)',
    demo: 'var(--c-st-demo)',
    won: 'var(--c-st-won)',
    lost: 'var(--c-st-lost)',
};

/** Raw hex for canvas/WebGL layers (deck.gl cannot read CSS variables). */
export const BAND_HEX = {
    dark: { hot: '#ff6a2b', warm: '#f2b63a', medium: '#4fc3a9', cold: '#8ca4b8' },
    light: { hot: '#c23b00', warm: '#8a5f00', medium: '#08705d', cold: '#4a6277' },
} as const;

export const STATUS_HEX = {
    dark: { new: '#8fa3b0', contacted: '#4db6e8', demo: '#e6a93b', won: '#5fd07a', lost: '#e0768a' },
    light: { new: '#52667a', contacted: '#0b6e99', demo: '#8a5f00', won: '#1b7a38', lost: '#b0304b' },
} as const;

/** Tailwind class sets (literal strings so the scanner keeps them). */
export const BAND_CLASSES: Record<ScoreBand, { text: string; bg: string; border: string; soft: string }> = {
    hot: { text: 'text-hot', bg: 'bg-hot', border: 'border-hot/50', soft: 'bg-hot/15' },
    warm: { text: 'text-warm', bg: 'bg-warm', border: 'border-warm/50', soft: 'bg-warm/15' },
    medium: { text: 'text-medium', bg: 'bg-medium', border: 'border-medium/50', soft: 'bg-medium/15' },
    cold: { text: 'text-cold', bg: 'bg-cold', border: 'border-cold/50', soft: 'bg-cold/15' },
};

export const STATUS_CLASSES: Record<PipelineStatus, { text: string; bg: string; border: string; soft: string }> = {
    new: { text: 'text-st-new', bg: 'bg-st-new', border: 'border-st-new/50', soft: 'bg-st-new/15' },
    contacted: { text: 'text-st-contacted', bg: 'bg-st-contacted', border: 'border-st-contacted/50', soft: 'bg-st-contacted/15' },
    demo: { text: 'text-st-demo', bg: 'bg-st-demo', border: 'border-st-demo/50', soft: 'bg-st-demo/15' },
    won: { text: 'text-st-won', bg: 'bg-st-won', border: 'border-st-won/50', soft: 'bg-st-won/15' },
    lost: { text: 'text-st-lost', bg: 'bg-st-lost', border: 'border-st-lost/50', soft: 'bg-st-lost/15' },
};
