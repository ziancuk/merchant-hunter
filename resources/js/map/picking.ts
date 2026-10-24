export type HoverKind = 'prospect' | 'outlet' | 'grid' | 'whitespace';

export interface MapPick {
    kind: HoverKind;
    index: number;
    x: number;
    y: number;
}

const LAYER_KINDS: Record<string, HoverKind> = {
    'prospect-columns': 'prospect',
    'prospect-discs': 'prospect',
    'outlet-core': 'outlet',
    'grid-bubbles': 'grid',
    'whitespace-dots': 'whitespace',
};

/** Which kind of object a deck.gl layer id represents (routes map clicks); null for non-interactive layers. */
export function kindOfLayer(layerId: string): HoverKind | null {
    return LAYER_KINDS[layerId] ?? null;
}

/** deck.gl click result → MapPick, or null when nothing interactive was hit. */
export function toMapPick(p: { layerId: string; index: number; x: number; y: number } | null): MapPick | null {
    if (!p || p.index < 0) return null;
    const kind = kindOfLayer(p.layerId);
    return kind ? { kind, index: p.index, x: p.x, y: p.y } : null;
}
