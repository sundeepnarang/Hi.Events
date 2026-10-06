import {useParams} from "react-router";
import {useForm} from "@mantine/form";
import {useFormErrorResponseHandler} from "../../../../../../hooks/useFormErrorResponseHandler.tsx";
import {useEffect} from "react";
import {showSuccess} from "../../../../../../utilites/notifications.tsx";
import {t} from "@lingui/macro";
import {Card} from "../../../../../common/Card";
import {HeadingWithDescription} from "../../../../../common/Card/CardHeading";
import {Button, Switch, Text, TextInput} from "@mantine/core";
import {
    IconBrandFacebook,
    IconBrandGoogle,
    IconBrandTiktok,
    IconTag,
} from "@tabler/icons-react";
import {useGetEventSettings} from "../../../../../../queries/useGetEventSettings.ts";
import {useUpdateEventSettings} from "../../../../../../mutations/useUpdateEventSettings.ts";
import {useGetAccount} from "../../../../../../queries/useGetAccount.ts";
import {TrackingPixelConfig} from "../../../../../../types.ts";
import React from "react";

interface ProviderDef {
    key: string;
    label: string;
    icon: React.ComponentType<{size?: number; style?: React.CSSProperties}>;
    placeholder: string;
    description: string;
    pattern: RegExp;
    formatHint: string;
}

/* eslint-disable lingui/no-unlocalized-strings */
const PROVIDERS: ProviderDef[] = [
    {
        key: 'facebook_pixel',
        label: 'Facebook Pixel',
        icon: IconBrandFacebook,
        placeholder: '1234567890',
        description: 'Pixel ID (numeric)',
        pattern: /^\d{9,20}$/,
        formatHint: 'Must be 9-20 digits',
    },
    {
        key: 'google_analytics_4',
        label: 'Google Analytics 4',
        icon: IconBrandGoogle,
        placeholder: 'G-XXXXXXXXXX',
        description: 'Measurement ID (overrides organizer and site defaults)',
        pattern: /^G-[a-zA-Z0-9]{6,20}$/,
        formatHint: 'Must start with G- followed by 6-20 characters',
    },
    {
        key: 'google_tag_manager',
        label: 'Google Tag Manager',
        icon: IconTag,
        placeholder: 'GTM-XXXXXXX',
        description: 'Container ID',
        pattern: /^GTM-[a-zA-Z0-9]{4,20}$/,
        formatHint: 'Must start with GTM- followed by 4-20 characters',
    },
    {
        key: 'tiktok_pixel',
        label: 'TikTok Pixel',
        icon: IconBrandTiktok,
        placeholder: 'CXXXXXXXXXX',
        description: 'Pixel ID',
        pattern: /^[a-zA-Z0-9]{6,30}$/,
        formatHint: 'Must be 6-30 alphanumeric characters',
    },
];
/* eslint-enable lingui/no-unlocalized-strings */

function pixelsToFormState(pixels: TrackingPixelConfig[] | undefined): Record<string, { enabled: boolean; pixel_id: string }> {
    const state: Record<string, { enabled: boolean; pixel_id: string }> = {};
    for (const p of PROVIDERS) {
        state[p.key] = {enabled: false, pixel_id: ''};
    }
    if (pixels) {
        for (const pixel of pixels) {
            if (state[pixel.provider]) {
                state[pixel.provider] = {enabled: pixel.enabled, pixel_id: pixel.pixel_id};
            }
        }
    }
    return state;
}

function formStateToPixels(state: Record<string, { enabled: boolean; pixel_id: string }>): TrackingPixelConfig[] {
    return Object.entries(state)
        .filter(([, v]) => v.pixel_id.trim() !== '')
        .map(([provider, v]) => ({
            provider,
            pixel_id: v.pixel_id.trim(),
            enabled: v.enabled,
        }));
}

const GTM_PROVIDER_KEY = 'google_tag_manager';

export const EventTrackingPixelSettings = () => {
    const {eventId} = useParams();
    const {data: account} = useGetAccount();
    const isSaasMode = account?.is_saas_mode_enabled;
    const availableProviders = isSaasMode
        ? PROVIDERS.filter(p => p.key !== GTM_PROVIDER_KEY)
        : PROVIDERS;
    const eventSettingsQuery = useGetEventSettings(eventId);
    const updateMutation = useUpdateEventSettings();
    const formErrorHandle = useFormErrorResponseHandler();

    const form = useForm<{
        pixels: Record<string, { enabled: boolean; pixel_id: string }>;
    }>({
        initialValues: {
            pixels: pixelsToFormState(undefined),
        },
    });

    useEffect(() => {
        if (eventSettingsQuery?.isFetched && eventSettingsQuery?.data) {
            form.setValues({
                pixels: pixelsToFormState(eventSettingsQuery.data.tracking_pixels),
            });
        }
    }, [eventSettingsQuery.isFetched]);

    const handleSubmit = (values: typeof form.values) => {
        let hasErrors = false;

        for (const provider of availableProviders) {
            const pixelId = values.pixels[provider.key]?.pixel_id.trim();
            if (pixelId && !provider.pattern.test(pixelId)) {
                form.setFieldError(`pixels.${provider.key}.pixel_id`, provider.formatHint);
                hasErrors = true;
            }
        }

        let trackingPixels = formStateToPixels(values.pixels);
        if (isSaasMode) {
            trackingPixels = trackingPixels.filter(p => p.provider !== GTM_PROVIDER_KEY);
        }

        if (hasErrors) return;

        updateMutation.mutate({
            eventSettings: {
                tracking_pixels: trackingPixels,
            },
            eventId: eventId,
        }, {
            onSuccess: () => {
                showSuccess(t`Successfully Updated Tracking Settings`);
            },
            onError: (error) => {
                formErrorHandle(form, error);
            }
        });
    };

    return (
        <Card>
            <HeadingWithDescription
                heading={t`Tracking & Analytics`}
                description={t`Configure tracking pixels specifically for this event. If not set here, defaults from your Organizer Settings or site environment will be used.`}
            />
            <form onSubmit={form.onSubmit(handleSubmit)}>
                <fieldset disabled={eventSettingsQuery.isLoading || updateMutation.isPending}>
                    <div style={{display: 'flex', flexDirection: 'column', gap: '12px'}}>
                        {availableProviders.map((provider) => {
                            const Icon = provider.icon;
                            const pixelState = form.values.pixels[provider.key];
                            const hasValue = pixelState?.pixel_id.trim() !== '';

                            return (
                                <div
                                    key={provider.key}
                                    style={{
                                        padding: '14px 16px',
                                        borderRadius: '8px',
                                        border: `1px solid var(--mantine-color-${hasValue && pixelState?.enabled ? 'primary-3' : 'gray-3'})`,
                                        backgroundColor: hasValue && pixelState?.enabled ? 'var(--mantine-color-primary-0)' : 'transparent',
                                        transition: 'all 0.15s ease',
                                    }}
                                >
                                    <div style={{
                                        display: 'flex',
                                        alignItems: 'center',
                                        justifyContent: 'space-between',
                                        marginBottom: hasValue ? '10px' : 0,
                                    }}>
                                        <div style={{display: 'flex', alignItems: 'center', gap: '10px'}}>
                                            <Icon size={20} style={{opacity: 0.7}}/>
                                            <div>
                                                <Text size="sm" fw={500}>{provider.label}</Text>
                                                {!hasValue && (
                                                    <Text size="xs" c="dimmed">{provider.description}</Text>
                                                )}
                                            </div>
                                        </div>
                                        {hasValue && (
                                            <Switch
                                                size="sm"
                                                checked={pixelState?.enabled ?? false}
                                                onChange={(e) => form.setFieldValue(
                                                    `pixels.${provider.key}.enabled`,
                                                    e.currentTarget.checked
                                                )}
                                            />
                                        )}
                                    </div>
                                    <TextInput
                                        {...form.getInputProps(`pixels.${provider.key}.pixel_id`)}
                                        placeholder={provider.placeholder}
                                        size="sm"
                                        description={hasValue ? provider.description : undefined}
                                        onChange={(e) => {
                                            form.setFieldValue(`pixels.${provider.key}.pixel_id`, e.currentTarget.value);
                                            if (e.currentTarget.value.trim() !== '' && !pixelState?.enabled) {
                                                form.setFieldValue(`pixels.${provider.key}.enabled`, true);
                                            }
                                        }}
                                        styles={{
                                            input: {
                                                fontFamily: 'monospace',
                                                fontSize: '13px',
                                            },
                                        }}
                                    />
                                </div>
                            );
                        })}
                    </div>

                    <Button
                        loading={updateMutation.isPending}
                        type="submit"
                        mt="md"
                    >
                        {t`Save`}
                    </Button>
                </fieldset>
            </form>
        </Card>
    );
};
