import {facebookPixelPlugin} from './plugins/facebookPixel';
import {googleAnalytics4Plugin} from './plugins/googleAnalytics4';
import {googleTagManagerPlugin} from './plugins/googleTagManager';
import {tiktokPixelPlugin} from './plugins/tiktokPixel';
import {TrackingPixelPlugin, TrackingPixelConfig, PageViewData, TrackingEventData} from './types';
import {ConsentCategory} from '../cookieConsent';
import {getConfig} from '../config.ts';

export interface ResolveTrackingPixelsOptions {
    eventPixels?: TrackingPixelConfig[];
    organizerPixels?: TrackingPixelConfig[];
}

export function resolveEffectiveTrackingPixels(
    input?: TrackingPixelConfig[] | ResolveTrackingPixelsOptions
): TrackingPixelConfig[] {
    let eventPixels: TrackingPixelConfig[] | undefined;
    let organizerPixels: TrackingPixelConfig[] | undefined;

    if (Array.isArray(input)) {
        organizerPixels = input;
    } else if (input) {
        eventPixels = input.eventPixels;
        organizerPixels = input.organizerPixels;
    }

    const envGaId = getConfig('VITE_GOOGLE_ANALYTICS_ID')?.trim();
    const envMetaPixelId = getConfig('VITE_META_PIXEL_ID')?.trim();
    const pixelMap = new Map<string, TrackingPixelConfig>();

    if (envGaId) {
        pixelMap.set('google_analytics_4', {
            provider: 'google_analytics_4',
            pixel_id: envGaId,
            enabled: true,
        });
    }

    if (envMetaPixelId) {
        pixelMap.set('facebook_pixel', {
            provider: 'facebook_pixel',
            pixel_id: envMetaPixelId,
            enabled: true,
        });
    }

    if (organizerPixels) {
        for (const pixel of organizerPixels) {
            if (pixel.pixel_id?.trim()) {
                pixelMap.set(pixel.provider, pixel);
            }
        }
    }

    if (eventPixels) {
        for (const pixel of eventPixels) {
            if (pixel.pixel_id?.trim()) {
                pixelMap.set(pixel.provider, pixel);
            }
        }
    }

    return Array.from(pixelMap.values());
}

const pluginRegistry: Record<string, TrackingPixelPlugin> = {
    facebook_pixel: facebookPixelPlugin,
    google_analytics_4: googleAnalytics4Plugin,
    google_tag_manager: googleTagManagerPlugin,
    tiktok_pixel: tiktokPixelPlugin,
};

export const pixelConsentCategory: Record<string, ConsentCategory> = {
    facebook_pixel: 'advertising',
    google_analytics_4: 'analytics',
    google_tag_manager: 'advertising',
    tiktok_pixel: 'advertising',
};

let activePlugins: TrackingPixelPlugin[] = [];

export function initializeTrackingPixels(pixels: TrackingPixelConfig[]): void {
    if (typeof window === 'undefined') return;
    cleanup();

    for (const pixel of pixels) {
        if (!pixel.enabled) continue;
        const plugin = pluginRegistry[pixel.provider];
        if (!plugin) {
            console.warn(`[hi.events] Unknown tracking pixel provider: ${pixel.provider}`);
            continue;
        }
        try {
            plugin.initialize(pixel.pixel_id);
            activePlugins.push(plugin);
        } catch (error) {
            console.error(`[hi.events] Failed to initialize ${pixel.provider}:`, error);
        }
    }
}

export function trackPageView(data?: Partial<PageViewData>): void {
    if (typeof window === 'undefined') return;
    const fullData: PageViewData = {
        url: window.location.href,
        title: document.title,
        referrer: document.referrer,
        ...data,
    };
    for (const plugin of activePlugins) {
        plugin.pageView(fullData);
    }
}

export function trackPixelEvent(data: TrackingEventData): void {
    if (typeof window === 'undefined') return;
    for (const plugin of activePlugins) {
        plugin.trackEvent(data);
    }
}

export function cleanup(): void {
    if (typeof window === 'undefined') return;
    for (const plugin of activePlugins) {
        plugin.cleanup?.();
    }
    activePlugins = [];
}

export function hasActivePixels(): boolean {
    return activePlugins.length > 0;
}
