export const initMetaPixel = (pixelId?: string) => {
    if (!pixelId) {
        console.warn("Meta Pixel ID is missing.");
        return;
    }

    console.log("Initializing Meta Pixel");
    // Prevent reinitialization
    if ((window as any).fbq) return;

    (function (f: any, b: Document, e: string, v: string, n?: any, t?: any, s?: any) {
        if (f.fbq) return;
        n = f.fbq = function () {
            n.callMethod ? n.callMethod.apply(n, arguments) : n.queue.push(arguments);
        };
        if (!f._fbq) {
            f._fbq=n;
            n.push=n;
            n.loaded=!0;
            n.version='2.0';
        }
        n.queue = [];
        t = b.createElement(e);
        t.async = true;
        t.src = v;
        s = b.getElementsByTagName(e)[0];
        s?.parentNode?.insertBefore(t, s);
    })(window, document, "script", "https://connect.facebook.net/en_US/fbevents.js");

    (window as any).fbq("init", pixelId);
    (window as any).fbq("track", "PageView");
};

export const trackMetaPixel = (eventName: string, eventData: any) => {
    if (!(window as any).fbq){
        console.log("Meta Pixel not initialized")
        return;
    }
    (window as any).fbq("track", eventName, eventData);
};