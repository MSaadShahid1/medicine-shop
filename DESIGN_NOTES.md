# Design versions

## Approved green workspace — preserved

Full project backup saved before the Lisso-inspired redesign:

`C:\xampp\htdocs\Farms-approved-green-design-20261024-112332`

This backup contains the approved dark-green navigation, lime accents, warm workspace, dashboard, and customer ledger. It is a file backup, not a live MySQL backup. No database records were changed during either redesign.

The earlier pre-green backup is at:

`C:\xampp\htdocs\Farms-backup-20261024-111609`

## Current: Lisso-inspired design

User-provided visual reference:
https://dribbble.com/shots/27207466-Task-Management-Dashboard-UI-Design-Lisso

The current interface adapts the reference's warm blush canvas, floating white pill navigation, oversized type, white rounded cards, sunny yellow statistics, and lavender accents to the existing medicine-shop business. The reference artwork is not bundled, and no mock AI, task management, or voice features were added.

- `sidebar.php`: shared top navigation, dropdowns, accessible all-pages drawer.
- `css/style.css`: original component foundation.
- `css/workspace.css`: shared component/layout foundation from the approved version.
- `css/lisso.css`: current application-wide visual layer.
- `dashboard.php`: read-only, real-data dashboard and its interactions.
- `css/lisso-dashboard.css`: dashboard-specific editorial layout.

Stylesheets use file modification timestamps as cache versions. Standalone PDF/print views remain independent of the app navigation.

To return to the approved design, first save the current project separately, then restore the files from the approved backup into the original `Farms` folder. Restoring these files does not roll back the live MySQL database.

## Non-dashboard pages and print polish

The working Lisso version was backed up before the page/print improvements:

`C:\xampp\htdocs\Farms-before-page-and-print-polish-20261024-134144`

The dashboard and its two design stylesheets were left unchanged. Directory pages now use real-data overview modules; entry forms have field groups and contextual guides; billing, profiles, ledgers and inventory have dedicated content compositions.

- `page_overview.php`: escaped, server-rendered `renderPageOverview()` and `renderPageGuide()` helpers.
- `css/page-polish.css`: shared overview, directory and form modules.
- `css/billing-polish.css`: billing workflows, profiles, ledgers and report layouts.
- `css/application-print.css`: direct browser-print rules for all application screens. Navigation, form guides and action controls are excluded; table headings repeat and rows can paginate.
- `print_controls.php` and `pdf_style.php`: independent, consistently branded document previews with explicit Print / Save PDF controls. Invoices use portrait A4 and the inventory report uses landscape A4. These are browser-print views, not a server-side PDF library.

Print previews do not open a print dialog automatically. Inventory preview filtering uses the search term passed by the report's Print Report action. No placeholder shop address/telephone/email or invented tax details are printed.

Validation included real headless-Chrome PDFs: short sales/purchase invoices, the opening-balance bill, the supplier directory, customer ledger and inventory report fit a single A4 sheet for the current records. An 80-row temporary report fixture paginated across five A4 sheets without modifying database data. Test outputs were saved outside the app, under the Windows temporary directory.

## Dashboard Studio and workspace experience

Pre-upgrade project backup:

`C:\xampp\htdocs\Farms-before-cinematic-dashboard-20261024-163332`

The current dashboard adds a pearlescent cinematic cover, data-derived orbital stock gauge, pastel KPI cards, a dark financial card, restrained pointer highlights, one-time scroll arrivals and hover details. Existing Lisso/page layers remain the foundation.

- `css/dashboard-studio.css`: dashboard-only layer, activated by `body.lisso-dashboard.cx-dashboard`; loaded last by the shared shell only on the dashboard.
- `js/dashboard-studio.js`: combines type/search/sort for the latest eight transactions; changes the chart/table/summary between three and six calendar months using one server-rendered JSON snapshot. No polling or API requests. If the chart library fails, the accessible table and controls still work.
- `workspace_experience.php`, `css/workspace-experience.css`, `js/workspace-experience.js`: global local-only page finder (Ctrl/Cmd + K), Pearl/Lilac/Solar atmosphere preferences and motion controls. The search finds application pages, not private business records. Native modal focus is restored on close; the fallback has its own focus handling.
- `js/vendor/`: the existing Lucide 0.468.0 and ApexCharts 3.45.2 libraries, self-hosted with upstream license files. Bootstrap, Font Awesome and the existing font import retain their current loading approach.

Motion respects device reduced-motion settings, can be paused in the page finder, and stops when the document is hidden. Storage is optional: blocked localStorage does not prevent navigation. No custom cursor, sound, flashes, hidden-on-load figures, or continuous canvas/JavaScript particle loop was added. Effects and modal controls are excluded from print, and dashboard paper output uses exact tabular chart data rather than duplicating the animated view.

New KPIs/insights are read-only queries. Figures reflect the database when the page loaded; they are not presented as live polling. Demo records are explicitly acknowledged in the dashboard footer. No API keys were accessed, no paid AI integration was added, and this upgrade does not change database records or the standalone invoice/report templates.

## Dashboard motion layer

Added on 3 November 2026. Motion references: gsap.com (kinetic split type, scroll-triggered arrivals), lenis.dev (smooth, scroll-linked motion) and a React Three Fiber physics-cluster sandbox (glossy bodies drawn to a centre and pushed by the cursor). The palette, layout and every figure are unchanged.

- Hero: glossy two-tone capsules and chalky tablets (WebGL) cluster around the stock-health core, which becomes frosted glass. The cursor sweeps them aside, a click on empty hero space bursts them outward, and they wobble with scroll speed.
- Load-in: the title rises letter by letter, kickers scramble into place, the health ring draws while its percentage counts up, and KPI cards flip up as their values count to the exact server figure.
- Scroll: Lenis smooth scrolling, cards arrive with masked word-by-word headings, rows, pills and counters; the supplier-balance bars wave; a light glass capsule band (drifting pastel aurora, holographic glint) carries the page's real figures as two-tone medicine capsules with rolling tablets between them; they ride a travelling wave that grows with scroll speed, the belt reverses with scroll direction, and hovering slows it and magnifies the capsule under the cursor; a progress line runs along the top.
- Hover: primary buttons are magnetic and KPI/insight cards tilt in 3D.

Turn it off per device with "Pause motion" in the page finder (Ctrl/Cmd + K); the system reduced-motion setting also disables it. Print always shows the finished, static page.
