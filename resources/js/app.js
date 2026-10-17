import { initAnalyticsConsent } from './analytics';
import { initNavMenus } from './nav';
import { initShareControls } from './share';
import { initSparklines } from './sparkline';
import { initThemeToggle } from './theme';
import { initTooltips } from './tooltip';

// Leaflet (and its CSS) only loads on pages with a map, after the page's own scripts.
if (document.querySelector('[data-map]')) {
    import('./map').then(({ initMaps }) => initMaps());
}
initSparklines();
initNavMenus();
initThemeToggle();
initTooltips();
initShareControls();
initAnalyticsConsent();
