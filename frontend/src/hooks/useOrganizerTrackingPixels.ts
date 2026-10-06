import {useEffect, useMemo} from 'react';
import {TrackingPixelConfig} from '../types';
import {
    cleanup,
    initializeTrackingPixels,
    pixelConsentCategory,
    resolveEffectiveTrackingPixels,
    ResolveTrackingPixelsOptions,
    trackPageView,
} from '../utilites/trackingPixels';
import {PageViewData} from '../utilites/trackingPixels/types';
import {useCookieConsent} from './useCookieConsent';

interface UseOrganizerTrackingPixelsReturn {
    pixelsReady: boolean;
}

export function useOrganizerTrackingPixels(
    trackingPixelsInput?: TrackingPixelConfig[] | ResolveTrackingPixelsOptions,
    pageViewData?: Partial<PageViewData>,
): UseOrganizerTrackingPixelsReturn {
    const prefs = useCookieConsent();

    const effectivePixels = useMemo(() => {
        return resolveEffectiveTrackingPixels(trackingPixelsInput);
    }, [trackingPixelsInput]);

    const allowedKey = JSON.stringify((effectivePixels ?? []).filter((pixel) => {
        const category = pixelConsentCategory[pixel.provider];
        return pixel.enabled && category !== undefined && !!prefs?.[category];
    }));
    const allowed = useMemo<TrackingPixelConfig[]>(() => JSON.parse(allowedKey), [allowedKey]);

    const pageViewKey = JSON.stringify(pageViewData ?? {});
    const memoizedPageViewData = useMemo<Partial<PageViewData> | undefined>(
        () => (pageViewKey !== '{}' ? JSON.parse(pageViewKey) : undefined),
        [pageViewKey]
    );

    useEffect(() => {
        if (allowed.length === 0) return;

        initializeTrackingPixels(allowed);
        const timer = setTimeout(() => trackPageView(memoizedPageViewData), 100);

        return () => {
            clearTimeout(timer);
            cleanup();
        };
    }, [allowed, memoizedPageViewData]);

    return {pixelsReady: allowed.length > 0};
}
