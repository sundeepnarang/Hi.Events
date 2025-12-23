import {useLoaderData} from "react-router";
import EventHomepage from "../EventHomepage";
import {Event} from "../../../types";
import {useEffect} from "react";
import {getConfig} from "../../../utilites/config.ts";
import {initMetaPixel} from "../../../utilites/metaPixel.ts";

export const PublicEvent = () => {

    useEffect(() => {
        const pixelId = getConfig('VITE_META_PIXEL_ID');
        initMetaPixel(pixelId);
    }, []);
    const loaderData = useLoaderData();

    const {event, promoCodeValid, promoCode} = loaderData as {
        event?: Event;
        promoCodeValid?: boolean;
        promoCode?: string;
    };

    return (
        <EventHomepage
            event={event}
            promoCodeValid={promoCodeValid}
            promoCode={promoCode}
        />
    );
};

export default PublicEvent;
