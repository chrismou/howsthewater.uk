import { initAnalyticsConsent } from './analytics';
import { initMoreRows } from './more-rows';
import { initNavMenus } from './nav';
import { initSearchToggle } from './search';
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
initSearchToggle();
initThemeToggle();
initTooltips();
initShareControls();
initMoreRows();
initAnalyticsConsent();
