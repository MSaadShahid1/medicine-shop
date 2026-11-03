<?php
// Shared, read-only workspace enhancement. Safe to include after the top navigation.
(static function ($items, $currentPage) {
    $escape = static function ($value) {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    };
    $catalog = [
        'dashboard.php' => ['Dashboard', 'Workspace', 'Your stock, sales and business overview.', 'overview home activity'],
        'add_company.php' => ['Add Company', 'Suppliers & customers', 'Create a supplier record.', 'supplier company new'],
        'view_companies.php' => ['View Companies', 'Suppliers & customers', 'Find suppliers and open their account details.', 'supplier directory balance payments'],
        'add_customer.php' => ['Add Customer', 'Suppliers & customers', 'Create a customer record.', 'buyer client new'],
        'view_customers.php' => ['View Customers', 'Suppliers & customers', 'Find customers and open their ledgers.', 'buyer client directory ledger'],
        'add_product.php' => ['Add Product', 'Inventory', 'Set up a product, supplier and rates.', 'medicine item stock new'],
        'view_products.php' => ['View Products', 'Inventory', 'Browse products, prices and stock levels.', 'medicine item price rate edit'],
        'inventory_report.php' => ['Inventory Report', 'Inventory', 'Review stock quantities and inventory value.', 'report warehouse valuation print'],
        'create_sales_bill.php' => ['Create Sales Bill', 'Sales', 'Start a new customer sale.', 'invoice sell checkout new'],
        'view_sales_bills.php' => ['View Sales Bills', 'Sales', 'Browse sales and open an invoice to print.', 'invoice history receipts print'],
        'create_purchase_bill.php' => ['Create Purchase Bill', 'Purchasing', 'Record a supplier purchase and incoming stock.', 'buy invoice new'],
        'view_purchase_bills.php' => ['Purchase Bills', 'Purchasing', 'Review supplier bills, balances and payment actions.', 'buy invoice history due payment'],
        'add_opening_balance.php' => ['Add Opening Balance', 'Purchasing', 'Enter one supplier’s historical opening balance.', 'supplier previous outstanding due'],
    ];
    $routes = [];
    foreach (is_array($items) ? $items : [] as $item) {
        if (!is_array($item)) continue;
        $file = $item['file'] ?? '';
        // Only existing, same-directory PHP pages: no external URLs or query actions.
        if (!is_string($file) || !preg_match('/\A[a-zA-Z0-9_-]+\.php\z/D', $file)
            || !is_file(__DIR__ . '/' . $file)) continue;
        $meta = $catalog[$file] ?? [ucwords(str_replace('_', ' ', basename($file, '.php'))), 'Workspace', 'Open this workspace page.', ''];
        $label = isset($item['label']) && is_string($item['label']) ? $item['label'] : $meta[0];
        $routes[$file] = [$label, $meta[1], $meta[2], $meta[3]];
    }
    // Also works when rendered without sidebar variables.
    foreach ($catalog as $file => $meta) {
        if (!isset($routes[$file]) && is_file(__DIR__ . '/' . $file)) $routes[$file] = $meta;
    }
    $groups = [];
    foreach ($routes as $file => $meta) $groups[$meta[1]][$file] = $meta;
    ?>
    <div class="wx-atmosphere" aria-hidden="true">
        <span class="wx-orb wx-orb-violet"></span>
        <span class="wx-orb wx-orb-peach"></span>
        <span class="wx-orb wx-orb-citron"></span>
        <span class="wx-grain"></span>
    </div>

    <?php
    if (!isset($_SESSION['ai_agent_csrf'])) {
        $_SESSION['ai_agent_csrf'] = bin2hex(random_bytes(32));
    }
    ?>
    <button type="button" class="wx-agent-launch" data-agent-open aria-haspopup="dialog" aria-controls="wxAgentPanel" aria-expanded="false">
        <span class="wx-agent-spark" aria-hidden="true">✦</span>
        <span>Ask your AI agent</span><span class="wx-agent-tag">BETA</span>
    </button>

    <section id="wxAgentPanel" class="wx-agent-panel" role="dialog" aria-modal="false" aria-labelledby="wxAgentTitle" hidden
        data-endpoint="ai_agent.php" data-csrf="<?php echo $escape($_SESSION['ai_agent_csrf']); ?>">
        <header class="wx-agent-head">
            <div class="wx-agent-avatar" aria-hidden="true">✦</div>
            <div class="wx-agent-heading">
                <span class="wx-eyebrow">MEDICINE SHOP / AI ASSISTANT</span>
                <h2 id="wxAgentTitle">Your shop co-pilot</h2>
                <span class="wx-agent-online"><i aria-hidden="true"></i> Ready to help</span>
            </div>
            <div class="wx-agent-head-actions">
                <button type="button" class="wx-agent-new-chat" data-agent-new-chat>New chat</button>
                <button type="button" class="wx-close" data-agent-close aria-label="Close AI assistant">×</button>
            </div>
        </header>
        <p class="wx-agent-privacy">Focused on this shop. Recent chat stays in this tab for follow-ups; messages are sent to the configured AI service.</p>
        <div class="wx-agent-messages" data-agent-messages role="log" aria-live="polite" aria-relevant="additions"></div>
        <form class="wx-agent-form" data-agent-form>
            <label class="wx-sr-only" for="wxAgentInput">Message your AI assistant</label>
            <textarea id="wxAgentInput" rows="1" maxlength="2000" placeholder="Ask a question or describe a task…" required></textarea>
            <button type="submit" class="wx-agent-send" aria-label="Send message"><span aria-hidden="true">↑</span></button>
        </form>
        <div class="wx-agent-footer"><span>Answers may need a quick fact-check.</span><span>Enter to send · Shift+Enter for a new line</span></div>
    </section>

    <dialog id="wxCommandDialog" class="wx-dialog" aria-labelledby="wxCommandTitle" aria-describedby="wxCommandDescription" hidden>
        <div class="wx-dialog-head">
            <div><span class="wx-eyebrow">MEDICINE SHOP / NAVIGATE</span>
                <h2 id="wxCommandTitle">A shortcut to your next step<span aria-hidden="true">.</span></h2>
            </div>
            <button type="button" class="wx-close" data-wx-close aria-label="Close page finder">×</button>
        </div>
        <p id="wxCommandDescription" class="wx-description">Find a workspace page. Search stays on this device — not your business records.</p>
        <div class="wx-search-wrap">
            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 4 4"/></svg>
            <label class="wx-sr-only" for="wxCommandInput">Search workspace pages</label>
            <input id="wxCommandInput" type="search" placeholder="Try “stock”, “customer” or “invoice”…" autocomplete="off" spellcheck="false" aria-controls="wxCommandRoutes" aria-describedby="wxKeyboardHelp">
            <span class="wx-search-key" aria-hidden="true">↵</span>
        </div>
        <div class="wx-results-meta"><span id="wxResultStatus" role="status" aria-live="polite" aria-atomic="true"><?php echo count($routes); ?> pages available</span><span>Pages, not records</span></div>
        <nav id="wxCommandRoutes" class="wx-route-scroll" aria-label="Workspace pages">
            <?php $routeIndex = 0; foreach ($groups as $group => $entries): ?>
                <section class="wx-route-group" aria-labelledby="wxGroup<?php echo $routeIndex; ?>">
                    <h3 id="wxGroup<?php echo $routeIndex; ?>"><?php echo $escape($group); ?></h3>
                    <ul class="wx-route-list">
                        <?php foreach ($entries as $file => $meta): $routeIndex++; ?>
                            <li class="wx-route-item">
                                <a class="wx-route" href="<?php echo $escape($file); ?>" data-wx-route data-wx-search="<?php echo $escape(implode(' ', $meta)); ?>"<?php echo $file === $currentPage ? ' aria-current="page"' : ''; ?>>
                                    <span class="wx-route-glyph" aria-hidden="true"><?php echo $escape(str_pad((string) $routeIndex, 2, '0', STR_PAD_LEFT)); ?></span>
                                    <span class="wx-route-copy"><strong><?php echo $escape($meta[0]); ?></strong><span><?php echo $escape($meta[2]); ?></span></span>
                                    <?php if ($file === $currentPage): ?><span class="wx-current">Here</span><?php endif; ?>
                                    <span class="wx-route-arrow" aria-hidden="true">↗</span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </section>
            <?php endforeach; ?>
            <div id="wxNoResults" class="wx-no-results" hidden><span aria-hidden="true">⌕</span><strong>No matching pages</strong><p>Try “sales”, “suppliers” or “inventory”.</p><button type="button" data-wx-clear>Show all pages</button></div>
        </nav>
        <footer class="wx-dialog-footer">
            <div class="wx-preferences">
                <fieldset class="wx-ambience"><legend>Atmosphere</legend>
                    <button type="button" data-wx-ambience="pearl" aria-pressed="true"><span class="wx-swatch wx-swatch-pearl" aria-hidden="true"></span>Pearl</button>
                    <button type="button" data-wx-ambience="lilac" aria-pressed="false"><span class="wx-swatch wx-swatch-lilac" aria-hidden="true"></span>Lilac</button>
                    <button type="button" data-wx-ambience="solar" aria-pressed="false"><span class="wx-swatch wx-swatch-solar" aria-hidden="true"></span>Solar</button>
                </fieldset>
                <button type="button" class="wx-motion" data-wx-motion aria-pressed="false"><span aria-hidden="true">Ⅱ</span><span data-wx-motion-label>Pause motion</span></button>
            </div>
            <p id="wxMotionNote" class="wx-motion-note" hidden>Your device requests reduced motion; animation stays off.</p>
            <p id="wxKeyboardHelp" class="wx-keyboard-help"><span><kbd>↑</kbd><kbd>↓</kbd> browse <kbd>Enter</kbd> open</span><span><kbd>Esc</kbd> close · <span data-wx-shortcut>Ctrl K</span> anywhere</span></p>
        </footer>
    </dialog>
    <script defer src="js/ai-agent.js?v=<?php echo filemtime(__DIR__ . '/js/ai-agent.js'); ?>"></script>
    <?php
})($menu_items ?? [], $current_page ?? '');