// Types mirror docs/CONTRACTS.md (frozen). Change only via an entry in DECISIONS.md.

export type PipelineStatus = 'new' | 'contacted' | 'demo' | 'won' | 'lost';
export type LifecycleStatus = 'open' | 'closed';
export type ScoreBand = 'hot' | 'warm' | 'medium' | 'cold';
export type ActivityType = 'created' | 'status_change' | 'contacted' | 'note' | 'opt_out' | 'opt_in' | 'rejected' | 'system';
export type MapMode = 'prospects' | 'heatmap' | 'whitespace' | 'coverage';
export type GeocodeStatus = 'ok' | 'needs_review' | 'failed' | 'manual';
export type Badge = 'new_open' | 'simulated' | 'closed' | 'opt_out';

export const PIPELINE_STATUSES: PipelineStatus[] = ['new', 'contacted', 'demo', 'won', 'lost'];

export interface AuthUser { id: number; name: string; email: string }

export interface SharedProps {
    auth: { user: AuthUser | null };
    flash: { success?: string | null; error?: string | null };
    app: { name: string; basemap: 'openfreemap' | 'local'; ai_enabled: boolean; env: string };
}

export interface ProspectFilter {
    province_id?: number;
    city?: string;
    category_id?: number[];
    score_min?: number;
    score_max?: number;
    rating_min?: number;
    has_phone?: 0 | 1;
    pipeline_status?: PipelineStatus[];
    max_distance_m?: number;
    new_open_only?: 0 | 1;
    include_closed?: 0 | 1;
}

export interface ScoreComponent { key: string; label: string; value: number; points: number; max: number; reason: string }

export interface Activity {
    id: number;
    type: ActivityType;
    user: { id: number; name: string } | null;
    body: string | null;
    created_at: string;
    from_status?: PipelineStatus | null;
    to_status?: PipelineStatus | null;
}

export interface ContactWarning { type: 'recent_contact'; by: string; at: string; days_ago: number }

export interface ProspectDetail {
    id: number;
    name: string;
    category: { id: number; key: string; name: string };
    address: string | null;
    address_source: 'source' | 'reverse_geocode' | 'none';
    lat: number;
    lng: number;
    phone: string | null;
    phone_simulated: boolean;
    whatsapp_url: string | null;
    website: string | null;
    rating: number | null;
    rating_simulated: boolean;
    review_count: number | null;
    reviews_simulated: boolean;
    data_updated_at: string;
    first_seen_at: string;
    score: number;
    score_band: ScoreBand;
    score_breakdown: ScoreComponent[];
    nearest_outlet: { id: number; name: string; distance_m: number } | null;
    neighbor_count_500m: number;
    area_density: number;
    pipeline_status: PipelineStatus;
    rejection_reason: { id: number; key: string; label: string } | null;
    handled_by: { id: number; name: string } | null;
    last_contacted_at: string | null;
    last_contacted_by: { id: number; name: string } | null;
    contact_count: number;
    do_not_contact: boolean;
    badges: Badge[];
    lifecycle_status: LifecycleStatus;
    latest_note: string | null;
    activities: Activity[];
    contact_warning: ContactWarning | null;
}

export interface ContactCheck {
    allowed: boolean;
    blocked_reason: 'do_not_contact' | 'no_phone' | 'invalid_phone' | 'closed' | null;
    warning: ContactWarning | null;
    min_interval_violation: boolean;
    phone_normalized: string | null;
    message: string;
    template_category: string;
    override_count: number;
    max_overrides: number;
}

export interface PipelineCard {
    id: number;
    name: string;
    category: string;
    city: string | null;
    score: number;
    score_band: ScoreBand;
    phone_present: boolean;
    last_contacted_at: string | null;
    handled_by: string | null;
    badges: Badge[];
    do_not_contact: boolean;
    /** Not in the frozen Card contract; the pipeline table shows it only when the server sends it. */
    pipeline_status?: PipelineStatus;
}

export type MapPointsResponse =
    | { type: 'points'; count: number; truncated: boolean; ids: number[]; lng: number[]; lat: number[]; score: number[]; status: number[]; flags: number[] }
    | { type: 'grid'; cell_size_deg: number; cells: { lng: number; lat: number; count: number; avg_score: number; hot: number }[] };

/** flags bitmask of the map payload */
export const MAP_FLAG = { NEW_OPEN: 1, SIMULATED: 2, CLOSED: 4, OPT_OUT: 8 } as const;

export interface RoutePlan {
    order: number[];
    stops: { id: number; name: string; lat: number; lng: number; leg_km: number }[];
    total_km: number;
    estimate_note: string;
    path: [number, number][];
    google_maps_urls: string[];
}
