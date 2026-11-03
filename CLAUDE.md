# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

A "Medicine Shop" inventory/billing system: plain PHP + MySQLi, no framework, no build step, served directly by XAMPP (Apache). Bootstrap 5, Font Awesome, jQuery, and Chart.js are all loaded via CDN `<script>`/`<link>` tags in each page's `<head>` — there is no npm/composer/bundler anywhere in this repo.

## Commands

There is no package manager, linter, or test suite in this project. Treat the following as the full set of "commands":

- **Run the app**: start XAMPP's Apache and MySQL (`C:\xampp\apache_start.bat`, `C:\xampp\mysql_start.bat`, or via the XAMPP control panel), then browse to `http://localhost/Farms/dashboard.php`.
- **Database**: import `medicine_shop.sql` into a MySQL database named `medicine_shop`. This file is the authoritative, current schema+data dump. **Ignore `database_setup.sql`** — it's an older, incomplete schema script missing the `customers` and `payments` tables; do not use it as a schema reference.
- **DB credentials**: `config.php` connects as `root` with no password to `localhost` — this matches a fresh, default XAMPP MySQL install.
- **Syntax-check a file**: `C:\xampp\php\php.exe -l path\to\file.php` (no autoloader/Composer, so this only catches parse errors, not undefined symbols).
- **Verify a page after changes**: hit it directly, e.g. `Invoke-WebRequest http://localhost/Farms/<page>.php -UseBasicParsing`, and check the body for `Fatal error`/`Parse error`/`Warning` strings — there's no test suite, so this plus a visual check in a browser is the only feedback loop.

## Architecture

### Page pattern

Every page is a single flat `.php` file with no router/MVC layer. The consistent shape is:

```php
require_once 'config.php';        // DB connection ($conn) + helpers
// handle $_GET (e.g. ?delete_id=, ?edit_id=)
// handle $_POST: sanitize, mysqli query, header("Location: ...") + exit()
?>
<!DOCTYPE html>
<html><head>
  <title>...</title>
  <link rel="icon" type="image/svg+xml" href="favicon.svg">
  <!-- Bootstrap / Font Awesome CDN, then css/style.css, then optional page-specific <style> -->
</head><body>
  <?php include 'sidebar.php'; ?>
  <div class="main-content">
    <div class="page-header">...</div>   <!-- gradient banner: title/subtitle + actions -->
    <!-- page body -->
  </div>
  <script>function toggleSidebar() {...}</script>  <!-- + page-specific JS -->
</body></html>
```

Follow this shape for new pages rather than introducing a different structure.

### Shared files (edit these, not per-page copies)

- **`config.php`** — opens `$conn` (mysqli) and defines `generateBillNumber($prefix)`, `formatCurrency($amount)` (prefixes `Rs. `, 2 decimals), `formatDate($date)`. Required via `require_once` at the top of every page.
- **`sidebar.php`** — the shared navigation partial, included on every page except standalone PDF views and `reset_system.php`. It now renders the Lisso-inspired floating top navigation, grouped dropdowns, and an accessible all-pages drawer, not a permanent sidebar. `$menu_items` remains the source for menu links. It emits `toggleSidebar()` and observes drawer state so legacy page-local toggles continue to work.
- **Shared styling** — `css/style.css` is the original component foundation, followed by `css/workspace.css`, then the current visual layer `css/lisso.css` (the latter two are loaded by `sidebar.php`). The current approved direction is a warm blush canvas, floating white pill navigation, black buttons, white rounded cards, and yellow/lavender accents. Reuse the existing component classes and edit the current shared layer instead of adding divergent page-local styles. All application stylesheets use `filemtime()` cache versions.
- **`pdf_style.php`** — shared `<style>` block `include`d by the three `*_pdf.php` print views so invoices/reports share one visual language.
- **Page content modules** — `page_overview.php` defines `renderPageOverview()` and `renderPageGuide()`. Pass plain text and local application URLs; the helpers escape output. `css/page-polish.css` and `css/billing-polish.css` are loaded by the shared shell after `lisso.css`. Reuse these overview, form-guide and billing compositions rather than leaving narrow forms centered in an empty workspace.
- **Application printing** — `css/application-print.css` is the final shell stylesheet. `sidebar.php` renders a print-only document heading and marks semantic action columns; direct browser printing hides navigation, helpers, and interactive controls while preserving data. Standalone documents use their own independent styles instead.

### Dashboard design and saved versions

`dashboard.php` follows the user's Lisso dashboard reference with a three-column editorial layout, real stock-health percentage, sunny yellow business activity card, white financial/stock cards, lavender supplier balance card, and working quick actions. Its page-specific styles live in `css/lisso-dashboard.css`, scoped to `body.lisso-dashboard` and `ld-*` components. ApexCharts (CDN) renders the six-month sales/purchases chart; an accessible data table remains available if the library cannot load. Do not add fake business metrics or nonfunctional AI/voice/task controls.

The prior approved green design was saved as a full project file backup before this redesign. See `DESIGN_NOTES.md` for its absolute backup location, the user's visual reference, and restoration notes. Do not modify that backup while changing the current design.

The current dashboard adds a scoped `cx-dashboard` Studio layer in `css/dashboard-studio.css` and `js/dashboard-studio.js`. All figures remain server-rendered; the JSON snapshot powers three/six-month chart views and the combined recent-transaction search/type/sort controls. Do not replace real metrics with decorative or fabricated numbers. Keep decorative motion optional, reduced-motion aware, paused in hidden tabs and excluded from print.

The dashboard motion layer is `css/dashboard-motion.css`, `js/dashboard-motion.js` and `js/dashboard-pills.js` (an ES module). GSAP 3.15.0 (ScrollTrigger, SplitText, ScrambleText), Lenis 1.3.27 and a tree-shaken Three.js 0.186.1 bundle are vendored in `js/vendor/` with their licenses (`three-LICENSE.txt` records how the bundle was built). `dashboard-motion.js` drives Lenis smooth scrolling, the load-in timeline, scroll-triggered card entrances, count-ups, the figures marquee, magnetic controls and card tilt; it owns all entrance animation, so `dashboard-studio.js` has none (it only defers the ApexCharts render until the chart card is in view). `dashboard-pills.js` renders the WebGL capsule/tablet cluster around the stock-health core with its own small physics step (cursor push, click burst, scroll wobble) and falls back to the CSS orbit rings without WebGL. Rules for this layer: counters must finish on the exact server-rendered text; animated text keeps an `.ld-sr-only` copy and animates an `aria-hidden` `<cx-v>` copy; SplitText/ScrambleText DOM is restored after it plays; every animated element gets `.cx-m` so print CSS can force it visible; the marquee only repeats figures already on the page and stays `aria-hidden`. Motion honours `prefers-reduced-motion` and the `farms.motion` pause preference: pausing settles everything permanently for that page view and the pills draw one still frame. The inline guard in `dashboard.php`'s `<head>` hides `.ld-main` only until the motion script is ready (3 s failsafe), which is why the motion scripts sit before the Bootstrap CDN script.

`workspace_experience.php` plus `css/workspace-experience.css` and `js/workspace-experience.js` supply the global Ctrl/Cmd+K page finder and local presentation preferences; `css/ai-agent.css`, `js/ai-agent.js`, and `ai_agent.php` provide the shared OpenAI-powered assistant. The assistant reads `OPENAI_API_KEY` from the root `.env` on the server, never exposes it to the browser, and is restricted to loopback clients. A strict topic gate keeps answers within this website and its medicine-shop workflows; it receives recent chat context so short follow-ups remain in scope. Recent messages are retained in tab-scoped sessionStorage across page navigation and reloads, with a New chat control to clear them. Assistant replies should sound conversational, concise, and unformatted by default; page-specific guidance keeps explanations grounded in actual UI features. Opening and closing motion respects reduced-motion settings, and the greeting offers suggested actions. The assistant can look up live product stock/prices, navigate safe local pages, fill visible form fields without submitting them, and create customers only after a short-lived, session-bound confirmation. Inventory results are queried locally and are not sent back to OpenAI. It does not execute arbitrary SQL, delete records, or submit bills/payments. Keep the `.env` access denial in `.htaccess`. `data-wx-motion` on the body is state, while `button[data-wx-motion]` is the actual control; event delegation must distinguish them. Lucide 0.468.0 and ApexCharts 3.45.2 are served from `js/vendor/` with their upstream licenses to avoid a CDN dependency.

### Domain model

- **Companies** (suppliers) vs **customers** (buyers) are distinct entities — don't conflate them.
- **`purchase_bills`** (from companies) track partial payment: `total_amount`, `paid_amount`, `balance`, with individual payments logged in the **`payments`** table (`add_payment.php`). `add_opening_balance.php` is the manual one-record-at-a-time tool for entering a supplier's historical/opening balance directly into `purchase_bills` — it is labeled "Add Opening Balance" in the UI; it is *not* a bulk importer despite some older code/comments calling it "import".
- **`sales_bills`** (to customers) are paid in full at creation — there is no partial-payment table for sales.
- **`products`** belong to a company (`company_id`) and carry both `purchase_rate` and `sale_rate` plus `current_stock`. Stock is decremented in `create_sales_bill.php` and incremented in `create_purchase_bill.php` — any new stock-affecting flow must update `current_stock` consistently with these.
- **`search_product.php`** is a tiny JSON-only AJAX endpoint (no HTML) used by the live product-search widgets in `create_sales_bill.php`/`create_purchase_bill.php`.
- **`reset_system.php`** is a standalone danger-zone utility (hardcoded password `admin123`) that `TRUNCATE`s tables. It deliberately has its own page chrome and is not linked from `sidebar.php`'s nav.
- **`*_pdf.php`** files (`sales_bill_pdf.php`, `purchase_bill_pdf.php`, `inventory_report_pdf.php`) are standalone, sidebar-less document previews sharing `pdf_style.php` and `print_controls.php`. They have an explicit Print / Save PDF button (no forced `onload` printing), validate bill IDs, and escape document data. Sales/purchase invoices use portrait A4; inventory uses landscape A4 with a repeated table header and search-aware rows/totals. Avoid gradients or white text that requires background printing, fixed footers, or whole-table page-break avoidance.

### Data access conventions

Most queries are raw `$conn->query("...")` with manual sanitization (`real_escape_string`, `intval`, `floatval`) rather than prepared statements — this is the prevailing (if not ideal) pattern. The bulk-insert loops in `create_sales_bill.php`/`create_purchase_bill.php` do use `$conn->prepare()`/`bind_param`; prefer that approach for any new multi-row insert logic. There is no authentication/login system anywhere in the app.
