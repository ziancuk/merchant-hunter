import * as maplibregl from 'maplibre-gl';
import 'maplibre-gl/dist/maplibre-gl.css';
import workerUrl from 'maplibre-gl/dist/maplibre-gl-worker.mjs?worker&url';
import { MapLibreOverlay } from '@deck.gl/maplibre';
import type { Layer } from '@deck.gl/core';
import { t } from '@/i18n';
import { BasemapGuard, createLocalStyle, resolveStyle, type BasemapKind } from './basemap';
import { INDONESIA_CENTER, INTRO_START } from './constants';
import { clampBbox } from './query';
import { introTargetZoom } from './geometry';
import type { Bbox, Viewport } from './types';

// maplibre-gl 6 ships the worker as a separate ES module; Vite emits it and gives us its URL (D-14).
maplibregl.setWorkerUrl(workerUrl);

export type IntroResult = 'done' | 'skipped';

export interface EngineOptions {
    container: HTMLElement;
    basemap: BasemapKind;
    /** Start as a rotating globe (intro) instead of the final Indonesia view. */
    globe: boolean;
    /** Allow flights/animation (false: every camera move is instant). */
    motion: boolean;
    onMove?: (vp: Viewport) => void;
    onMoveEnd?: (vp: Viewport) => void;
    /** Map click with whatever deck.gl layer object lies under the cursor (null: empty map). */
    onPick?: (pick: { layerId: string; index: number; x: number; y: number } | null) => void;
    onBasemapFallback?: (reason: string) => void;
    onReady?: () => void;
    onError?: (message: string) => void;
}

/**
 * Owns the MapLibre map and the deck.gl overlay. Deliberately NOT reactive (D-14): nothing here is wrapped by Vue
 * proxies. `destroy()` releases everything: animation frames, listeners, overlay (WebGL resources), map, timers.
 */
export class MapEngine {
    map: maplibregl.Map | null = null;
    overlay: MapLibreOverlay | null = null;
    private guard: BasemapGuard | null = null;
    private spinFrame = 0;
    private moveFrame = 0;
    private pendingLayers: Layer[] = [];
    private introResolve: ((r: IntroResult) => void) | null = null;
    private introActive = false;
    private destroyed = false;
    private loaded = false;
    private fellBack = false;
    private readonly opts: EngineOptions;
    private readonly listeners: { type: string; fn: (...a: never[]) => void }[] = [];
    private resizeObserver: ResizeObserver | null = null;

    constructor(opts: EngineOptions) {
        this.opts = opts;
        const initialCenter = opts.globe ? INTRO_START.center : INDONESIA_CENTER;
        const initialZoom = opts.globe ? INTRO_START.zoom : introTargetZoom(opts.container.clientWidth || 1100);
        try {
            this.map = new maplibregl.Map({
                container: opts.container,
                style: resolveStyle(opts.basemap) as never,
                center: initialCenter,
                zoom: initialZoom,
                pitch: 0,
                maxPitch: 65,
                attributionControl: false,
                dragRotate: true,
                canvasContextAttributes: { antialias: true },
                fadeDuration: opts.motion ? 300 : 0,
            } as never);
        } catch (e) {
            opts.onError?.(e instanceof Error ? e.message : t('map.create_failed'));
            return;
        }
        this.wire();
    }

    private on(type: string, fn: (...a: never[]) => void): void {
        this.map?.on(type as never, fn as never);
        this.listeners.push({ type, fn });
    }

    private wire(): void {
        const map = this.map!;
        if (this.opts.basemap === 'openfreemap') {
            this.guard = new BasemapGuard((reason) => this.fallback(reason));
            this.guard.start();
        }

        this.on('load', (() => {
            this.loaded = true;
            this.guard?.styleLoaded();
            if (this.opts.globe) map.setProjection({ type: 'globe' });
            if (!this.opts.globe) this.attachOverlay();
            this.opts.onReady?.();
            this.emitMoveEnd();
        }) as never);

        // Some styles reference sprite icons we do not ship (e.g. OpenFreeMap "circle-11"): answer with a blank pixel.
        map.setMissingStyleImageResolver((id: string) => {
            if (!map.hasImage(id)) map.addImage(id, { width: 1, height: 1, data: new Uint8Array(4) });
        });

        this.on('error', ((e: { sourceId?: string; tile?: unknown; error?: { message?: string } }) => {
            if (!this.guard) return;
            if (!this.loaded) this.guard.styleError();
            else if (e.sourceId || e.tile) this.guard.sourceError();
        }) as never);

        this.on('move', (() => {
            if (this.moveFrame) return;
            this.moveFrame = requestAnimationFrame(() => {
                this.moveFrame = 0;
                if (!this.destroyed) this.opts.onMove?.(this.getViewport());
            });
        }) as never);
        this.on('moveend', (() => this.emitMoveEnd()) as never);

        // Clicks are resolved through the overlay's pick API: it behaves the same in interleaved and overlaid modes.
        this.on('click', ((e: { point: { x: number; y: number } }) => {
            const info = this.overlay?.pickObject({ x: e.point.x, y: e.point.y, radius: 6 });
            const id = info?.layer?.id;
            if (info && id && info.index !== undefined && info.index >= 0) this.opts.onPick?.({ layerId: id, index: info.index, x: e.point.x, y: e.point.y });
            else this.opts.onPick?.(null);
        }) as never);

        // Keep the canvas matched to its container (HUD toggles, orientation changes).
        if (typeof ResizeObserver !== 'undefined') {
            this.resizeObserver = new ResizeObserver(() => this.map?.resize());
            this.resizeObserver.observe(this.opts.container);
        }
    }

    private emitMoveEnd(): void {
        if (!this.destroyed && this.map) this.opts.onMoveEnd?.(this.getViewport());
    }

    /** Remote basemap failed: swap in the offline style and tell the page (banner). */
    private fallback(reason: string): void {
        if (this.destroyed || !this.map || this.fellBack) return;
        this.fellBack = true;
        try {
            this.map.setStyle(createLocalStyle() as never);
        } catch {
            /* the map keeps whatever it had; the banner still informs the user */
        }
        this.opts.onBasemapFallback?.(reason);
    }

    getViewport(): Viewport {
        const map = this.map!;
        const b = map.getBounds();
        const bbox = clampBbox([b.getWest(), b.getSouth(), b.getEast(), b.getNorth()]);
        const c = map.getCenter();
        return { bbox, zoom: map.getZoom(), center: [c.lng, c.lat], pitch: map.getPitch(), bearing: map.getBearing() };
    }

    /** Adds the deck.gl overlay once the map is in Mercator (globe + heatmap/hexagon layers are unreliable, D-11). */
    attachOverlay(): void {
        if (this.overlay || this.destroyed || !this.map) return;
        this.overlay = new MapLibreOverlay({
            interleaved: true,
            layers: this.pendingLayers,
            pickingRadius: 6,
            onError: (error: Error) => console.warn('[deck.gl]', error.message),
        } as never);
        this.map.addControl(this.overlay as never);
    }

    setLayers(layers: Layer[]): void {
        this.pendingLayers = layers;
        this.overlay?.setProps({ layers } as never);
    }

    /** Ids of the layers deck.gl currently renders (debug/E2E only). Reads a private deck.gl field, so it is fully guarded. */
    getLayerIds(): string[] {
        try {
            const deck = (this.overlay as unknown as { _deck?: { props?: { layers?: unknown } } } | null)?._deck;
            const layers = deck?.props?.layers;
            if (!Array.isArray(layers)) return [];
            return layers.map((l) => (l as { id?: unknown })?.id).filter((id): id is string => typeof id === 'string');
        } catch {
            return [];
        }
    }

    get hasOverlay(): boolean {
        return this.overlay !== null;
    }

    setCursor(cursor: string): void {
        const canvas = this.map?.getCanvas();
        if (canvas) canvas.style.cursor = cursor;
    }

    private durationFor(ms: number): number {
        return this.opts.motion ? ms : 0;
    }

    flyTo(opts: { center: [number, number]; zoom?: number; pitch?: number; bearing?: number; padding?: { top: number; bottom: number; left: number; right: number }; duration?: number }): void {
        const map = this.map;
        if (!map) return;
        const duration = this.durationFor(opts.duration ?? 1600);
        const camera = { center: opts.center, zoom: opts.zoom, pitch: opts.pitch, bearing: opts.bearing, padding: opts.padding };
        if (duration === 0) map.jumpTo(camera as never);
        else map.flyTo({ ...camera, duration, essential: true } as never);
    }

    fitBbox(bbox: Bbox, opts: { padding?: number | { top: number; bottom: number; left: number; right: number }; maxZoom?: number; duration?: number } = {}): void {
        const map = this.map;
        if (!map) return;
        const duration = this.durationFor(opts.duration ?? 1400);
        map.fitBounds(
            [
                [bbox[0], bbox[1]],
                [bbox[2], bbox[3]],
            ],
            { padding: opts.padding ?? 80, maxZoom: opts.maxZoom ?? 15, duration, essential: true } as never,
        );
    }

    zoomBy(delta: number): void {
        this.map?.easeTo({ zoom: (this.map.getZoom() ?? 0) + delta, duration: this.durationFor(250), essential: true });
    }

    /** Tilts for the 3D columns, or back to a flat top-down view. */
    toggleTilt(): boolean {
        const map = this.map;
        if (!map) return false;
        const tilt = map.getPitch() < 10;
        map.easeTo({ pitch: tilt ? 55 : 0, bearing: tilt ? map.getBearing() : 0, duration: this.durationFor(500), essential: true });
        return tilt;
    }

    resetView(): void {
        const map = this.map;
        if (!map) return;
        const duration = this.durationFor(1200);
        const target = { center: INDONESIA_CENTER, zoom: introTargetZoom(this.opts.container.clientWidth || 1100), pitch: 0, bearing: 0 };
        if (duration === 0) map.jumpTo(target);
        else map.flyTo({ ...target, duration, essential: true });
    }

    // --- intro -----------------------------------------------------------------------------------------------------
    /**
     * Rotates the globe slowly, then flies to Indonesia and settles into the Mercator map.
     * Resolves `'done'` when the flight ends or `'skipped'` when `skipIntro()` is called.
     */
    runIntro(spinMs = 3200, flightMs = 4200): Promise<IntroResult> {
        const map = this.map;
        if (!map || this.destroyed) return Promise.resolve('skipped');
        this.introActive = true;
        return new Promise<IntroResult>((resolve) => {
            this.introResolve = resolve;
            const startedAt = performance.now();
            let last = startedAt;
            let lng: number = INTRO_START.center[0];
            const spin = (now: number) => {
                if (!this.introActive || this.destroyed) return;
                const dt = (now - last) / 1000;
                last = now;
                lng += dt * 9;
                map.setCenter([lng, INTRO_START.center[1]]);
                if (now - startedAt >= spinMs) {
                    this.spinFrame = 0;
                    this.flyToIndonesia(flightMs);
                    return;
                }
                this.spinFrame = requestAnimationFrame(spin);
            };
            this.spinFrame = requestAnimationFrame(spin);
        });
    }

    private flyToIndonesia(flightMs: number): void {
        const map = this.map;
        if (!map) return;
        const zoom = introTargetZoom(this.opts.container.clientWidth || 1100);
        const done = () => {
            map.off('moveend', done);
            this.finishIntro('done');
        };
        map.once('moveend', done);
        map.flyTo({ center: INDONESIA_CENTER, zoom, pitch: 0, bearing: 0, duration: flightMs, essential: true, curve: 1.6 } as never);
    }

    skipIntro(): void {
        if (!this.introActive || !this.map) return;
        if (this.spinFrame) cancelAnimationFrame(this.spinFrame);
        this.spinFrame = 0;
        this.map.stop();
        this.map.jumpTo({ center: INDONESIA_CENTER, zoom: introTargetZoom(this.opts.container.clientWidth || 1100), pitch: 0, bearing: 0 });
        this.finishIntro('skipped');
    }

    private finishIntro(result: IntroResult): void {
        if (!this.introActive) return;
        this.introActive = false;
        const map = this.map;
        if (map) {
            map.setProjection({ type: 'mercator' });
            this.attachOverlay();
            this.emitMoveEnd();
        }
        const resolve = this.introResolve;
        this.introResolve = null;
        resolve?.(result);
    }

    get isIntroActive(): boolean {
        return this.introActive;
    }

    /** Full teardown (D-14). Safe to call twice. */
    destroy(): void {
        if (this.destroyed) return;
        this.destroyed = true;
        this.introActive = false;
        if (this.spinFrame) cancelAnimationFrame(this.spinFrame);
        if (this.moveFrame) cancelAnimationFrame(this.moveFrame);
        this.guard?.dispose();
        this.resizeObserver?.disconnect();
        this.resizeObserver = null;
        const map = this.map;
        for (const l of this.listeners) map?.off(l.type as never, l.fn as never);
        this.listeners.length = 0;
        try {
            if (this.overlay) {
                map?.removeControl(this.overlay as never);
                this.overlay.finalize();
            }
        } catch {
            /* already detached */
        }
        this.overlay = null;
        try {
            map?.remove();
        } catch {
            /* WebGL context already lost */
        }
        this.map = null;
        this.introResolve?.('skipped');
        this.introResolve = null;
    }
}

// One dynamic chunk for MapLibre + deck.gl: the page imports both through this module.
export { buildLayers } from './layers';
