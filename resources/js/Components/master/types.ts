// Prop/response shapes of the dashboard, impact and master pages (docs/CONTRACTS.md §1, §7, §8; D-86).
import type { GeocodeStatus } from '@/types';

export interface PageMeta {
    current_page: number;
    last_page: number;
    total: number;
    per_page: number;
}

export interface DigestRow { id: number; name: string; score: number }
export interface Digest { date: string; new_open: DigestRow[]; hot: DigestRow[]; follow_up: DigestRow[] }

export interface DashboardKpis {
    outlets: number;
    prospects_active: number;
    new_7d: number;
    conversion_rate: number;
    deals_this_month: number;
}
export interface Funnel { found: number; contacted: number; demo: number; won: number; lost: number }
export interface PenetrationRow { province_id: number; province: string; outlets: number; prospects: number; won: number; penetration: number }
export interface TopCategory { category: string; count: number; avg_score: number }
export interface WhitespaceTop { cell_id: string; lat: number; lng: number; prospects: number; nearest_outlet_m: number | null; province: string | null }
export interface UserActivity { user_id: number; name: string; contacted: number; demo: number; won: number }
export interface SchedulerHealth { last_run_at: string | null; status: string | null; age_hours: number | null; failed_last_7d: number }

export type Assumptions = Record<string, number>;
export interface ImpactResults {
    google_calls_per_month: number;
    google_cost_usd: number;
    google_cost_idr: number;
    osm_cost_idr: number;
    prospects_found: number;
    expected_deals: number;
    expected_revenue_idr: number;
    hours_saved_per_month: number;
    hours_saved_value_idr?: number;
}

export interface DataSource {
    id: number;
    key: string;
    name: string;
    is_active: boolean;
    priority: number;
    has_key: boolean;
    key_hint: string | null;
    monthly_quota: number | null;
    calls_this_month: number | null;
    cost_search_per_1000: number | null;
    cost_details_per_1000: number | null;
    last_run_at: string | null;
    last_status: string | null;
    last_error: string | null;
    config: Record<string, unknown> | null;
}

export interface OutletRow {
    id: number;
    code: string;
    name: string;
    address: string | null;
    city: string;
    province_id: number;
    province: string | null;
    lat: number | null;
    lng: number | null;
    geocode_source: string | null;
    geocode_status: GeocodeStatus;
    geocode_score: number | null;
    is_active: boolean;
    is_simulated: boolean;
    last_synced_at: string | null;
}

export interface ProvinceOption { id: number; name: string }

export interface ImportResult {
    created: number;
    updated: number;
    geocoded: number;
    needs_review: number;
    failed: number;
    queued?: number;
    errors: { row: number; message: string }[];
}

export interface GeocodeCandidate { display_name: string; lat: number; lng: number; score: number }
export interface ReviewItem { outlet: OutletRow; candidates: GeocodeCandidate[] }

export interface CategoryRow {
    id: number;
    key: string;
    name: string;
    weight: number;
    whatsapp_template: string;
    osm_filters: unknown;
    google_types: string[] | null;
    is_active: boolean;
    sort_order: number;
}

export interface SchedulerOutletRun {
    id: number;
    outlet_id: number;
    outlet: string | null;
    provider: string | null;
    status: string;
    fetched_count: number;
    new_count: number;
    updated_count: number;
    closed_count: number;
    error: string | null;
    duration_ms: number | null;
}
export interface SchedulerRunRow {
    id: number;
    command: string;
    status: string;
    started_at: string | null;
    finished_at: string | null;
    duration_ms: number | null;
    outlets_total: number;
    outlets_ok: number;
    outlets_failed: number;
    prospects_new: number;
    prospects_updated: number;
    prospects_closed: number;
    api_calls: number;
    error: string | null;
    outlets?: SchedulerOutletRun[];
}

export interface AppSettings {
    contact: { warn_days: number; min_interval_days: number; max_overrides: number };
    digest: { email_to: string | null; hot_threshold: number; follow_up_days: number };
    sync: { max_outlets_per_run: number };
    scoring: { params: Record<string, unknown> };
    enrichment: { simulated: boolean };
    demo: { allow_simulated_whatsapp: boolean };
}
