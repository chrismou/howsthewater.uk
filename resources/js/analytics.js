const STORAGE_KEY = 'analytics-consent';

/**
 * Google Analytics sets cookies, so it only loads once the visitor accepts
 * them in the consent banner. The choice is remembered in this browser, and
 * the footer's "Cookie settings" link reopens the banner to change it.
 */
export function initAnalyticsConsent() {
    const banner = document.querySelector('[data-cookie-banner]');

    if (!banner) {
        return;
    }

    const measurementId = banner.dataset.measurementId;
    const consent = readConsent();

    if (consent === 'granted') {
        loadAnalytics(measurementId);
    } else if (consent === null) {
        banner.hidden = false;
    }

    banner.querySelector('[data-cookie-accept]').addEventListener('click', () => {
        saveConsent('granted');
        banner.hidden = true;
        loadAnalytics(measurementId);
    });

    banner.querySelector('[data-cookie-reject]').addEventListener('click', () => {
        const wasGranted = readConsent() === 'granted';

        saveConsent('denied');
        banner.hidden = true;

        // Analytics is already running on this page, so clear its cookies and reload without it.
        if (wasGranted) {
            clearAnalyticsCookies();
            window.location.reload();
        }
    });

    document.querySelectorAll('[data-cookie-settings]').forEach((link) => {
        link.addEventListener('click', (event) => {
            event.preventDefault();
            banner.hidden = false;
        });
    });
}

function loadAnalytics(measurementId) {
    if (window.gtag) {
        return;
    }

    window.dataLayer = window.dataLayer || [];
    window.gtag = function gtag() {
        window.dataLayer.push(arguments);
    };
    window.gtag('js', new Date());
    window.gtag('config', measurementId);

    const script = document.createElement('script');
    script.async = true;
    script.src = `https://www.googletagmanager.com/gtag/js?id=${encodeURIComponent(measurementId)}`;
    document.head.appendChild(script);
}

/**
 * Google sets its cookies on the top-level domain, so expire them there and on the current host.
 */
function clearAnalyticsCookies() {
    const domains = [window.location.hostname, `.${window.location.hostname.split('.').slice(-2).join('.')}`];

    document.cookie.split(';').map((cookie) => cookie.split('=')[0].trim()).filter((name) => name.startsWith('_ga')).forEach((name) => {
        domains.forEach((domain) => {
            document.cookie = `${name}=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/; domain=${domain}`;
        });
        document.cookie = `${name}=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/`;
    });
}

function readConsent() {
    try {
        return localStorage.getItem(STORAGE_KEY);
    } catch {
        return null;
    }
}

function saveConsent(value) {
    try {
        localStorage.setItem(STORAGE_KEY, value);
    } catch {
        // Storage can be unavailable in private windows; the choice then lasts for this page only.
    }
}
