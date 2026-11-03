<link rel="stylesheet" href="css/workspace.css?v=<?php echo filemtime(__DIR__ . '/css/workspace.css'); ?>">
<link rel="stylesheet" href="css/lisso.css?v=<?php echo filemtime(__DIR__ . '/css/lisso.css'); ?>">
<link rel="stylesheet" href="css/page-polish.css?v=<?php echo filemtime(__DIR__ . '/css/page-polish.css'); ?>">
<link rel="stylesheet" href="css/billing-polish.css?v=<?php echo filemtime(__DIR__ . '/css/billing-polish.css'); ?>">
<link rel="stylesheet" href="css/application-print.css?v=<?php echo filemtime(__DIR__ . '/css/application-print.css'); ?>">
<link rel="stylesheet" href="css/workspace-experience.css?v=<?php echo filemtime(__DIR__ . '/css/workspace-experience.css'); ?>">
<link rel="stylesheet" href="css/ai-agent.css?v=<?php echo filemtime(__DIR__ . '/css/ai-agent.css'); ?>">
<?php if (basename($_SERVER['PHP_SELF']) === 'dashboard.php'): ?>
<link rel="stylesheet" href="css/dashboard-studio.css?v=<?php echo filemtime(__DIR__ . '/css/dashboard-studio.css'); ?>">
<?php endif; ?>
<script src="js/vendor/lucide-0.468.0.min.js"></script>
<div class="sidebar" id="sidebar" role="dialog" aria-modal="true" aria-label="All business pages" inert>
    <button type="button" class="drawer-close" onclick="document.getElementById('sidebar').classList.remove('active')" aria-label="Close navigation"><i data-lucide="x"></i></button>
    <a href="dashboard.php" class="sidebar-brand" style="display:block; text-decoration:none;">
        <h4><i data-lucide="pill"></i> Medicine Shop</h4>
        <p>Business workspace</p>
    </a>

    <ul class="sidebar-menu">
        <?php
        // Get the current file name (e.g., 'dashboard.php')
        $current_page = basename($_SERVER['PHP_SELF']);

        // Define menu items including the new Customer links
        $menu_items = [
            // Dashboard
            ['file' => 'dashboard.php', 'icon' => 'layout-dashboard', 'label' => 'Dashboard', 'margin' => false],

            // Companies (Suppliers)
            ['file' => 'add_company.php', 'icon' => 'building-2', 'label' => 'Add Company', 'margin' => true],
            ['file' => 'view_companies.php', 'icon' => 'list', 'label' => 'View Companies', 'margin' => false],

            // Customers (Clients) - NEW LINKS
            ['file' => 'add_customer.php', 'icon' => 'user-plus', 'label' => 'Add Customer', 'margin' => true],
            ['file' => 'view_customers.php', 'icon' => 'users', 'label' => 'View Customers', 'margin' => false],

            // Products
            ['file' => 'add_product.php', 'icon' => 'package-plus', 'label' => 'Add Product', 'margin' => true],
            ['file' => 'view_products.php', 'icon' => 'package', 'label' => 'View Products', 'margin' => false],
            ['file' => 'inventory_report.php', 'icon' => 'warehouse', 'label' => 'Inventory Report', 'margin' => true],

            // Sales
            ['file' => 'create_sales_bill.php', 'icon' => 'shopping-cart', 'label' => 'Create Sales Bill', 'margin' => true],
            ['file' => 'view_sales_bills.php', 'icon' => 'receipt', 'label' => 'View Sales Bills', 'margin' => false],

            // Purchases
            ['file' => 'create_purchase_bill.php', 'icon' => 'truck', 'label' => 'Create Purchase Bill', 'margin' => true],
            ['file' => 'view_purchase_bills.php', 'icon' => 'file-text', 'label' => 'Purchase Bills', 'margin' => false],
            ['file' => 'add_opening_balance.php', 'icon' => 'file-input', 'label' => 'Add Opening Balance', 'margin' => false],

        ];

        // Loop through items to generate HTML
        $sections = ['dashboard.php' => 'Workspace', 'add_company.php' => 'Relationships', 'add_product.php' => 'Inventory', 'create_sales_bill.php' => 'Sales & billing', 'create_purchase_bill.php' => 'Purchasing'];
        foreach ($menu_items as $item) {
            if (isset($sections[$item['file']])) {
                echo '<li class="nav-section">' . $sections[$item['file']] . '</li>';
            }
            // Check if this item is the current page
            $active_class = ($current_page == $item['file']) ? 'active' : '';
            // Add margin-top if specified
            $margin_style = ($item['margin']) ? 'style="margin-top: 20px;"' : '';

            echo '<li class="menu-item" ' . $margin_style . '>';
            echo '<a href="' . $item['file'] . '" class="' . $active_class . '"' . ($active_class ? ' aria-current="page"' : '') . '>';
            echo '<i data-lucide="' . $item['icon'] . '"></i>';
            echo '<span>' . $item['label'] . '</span>';
            echo '</a>';
            echo '</li>';
        }
        ?>
    </ul>
    <div class="sidebar-footer">
        <span class="workspace-avatar">MS</span>
        <div><strong>Medicine Shop</strong><small>Your business, in balance.</small></div>
    </div>
</div>
<button type="button" class="drawer-backdrop" aria-label="Close navigation" onclick="document.getElementById('sidebar').classList.remove('active')" tabindex="-1"></button>
<?php
$workspace_title = ucwords(str_replace('_', ' ', basename($_SERVER['PHP_SELF'], '.php')));
foreach ($menu_items as $item) {
    if ($item['file'] === $current_page) { $workspace_title = $item['label']; break; }
}
?>
<header class="workspace-topbar">
    <a href="dashboard.php" class="lisso-brand" aria-label="Medicine Shop home"><span class="lisso-mark" aria-hidden="true"><b></b><b></b><b></b><b></b></span>Medicine Shop<span class="brand-period">.</span></a>
    <nav class="lisso-nav" aria-label="Main navigation">
        <a href="dashboard.php" class="lisso-nav-link<?php echo $current_page === 'dashboard.php' ? ' is-current' : ''; ?>" <?php echo $current_page === 'dashboard.php' ? 'aria-current="page"' : ''; ?>>Dashboard</a>
        <?php
        $navigation_groups = [
            'Inventory' => ['add_product.php', 'view_products.php', 'inventory_report.php'],
            'Billing' => ['create_sales_bill.php', 'view_sales_bills.php', 'create_purchase_bill.php', 'view_purchase_bills.php', 'add_opening_balance.php'],
            'Directory' => ['add_company.php', 'view_companies.php', 'add_customer.php', 'view_customers.php'],
        ];
        $parent_pages = ['edit_product.php' => 'view_products.php', 'sales_bill_detail.php' => 'view_sales_bills.php', 'purchase_bill_detail.php' => 'view_purchase_bills.php', 'add_payment.php' => 'view_purchase_bills.php', 'edit_company.php' => 'view_companies.php', 'company_details.php' => 'view_companies.php', 'customer_ledger.php' => 'view_customers.php', 'edit_customer.php' => 'view_customers.php'];
        $nav_current = $parent_pages[$current_page] ?? $current_page;
        foreach ($navigation_groups as $group_label => $files): ?>
        <details class="lisso-nav-group<?php echo in_array($nav_current, $files, true) ? ' is-current' : ''; ?>">
            <summary><?php echo $group_label; ?><i data-lucide="chevron-down"></i></summary>
            <div class="lisso-dropdown">
                <?php foreach ($menu_items as $item): if (!in_array($item['file'], $files, true)) continue; ?>
                <a href="<?php echo $item['file']; ?>" <?php echo $current_page === $item['file'] ? 'aria-current="page"' : ''; ?>><i data-lucide="<?php echo $item['icon']; ?>"></i><?php echo $item['label']; ?></a>
                <?php endforeach; ?>
            </div>
        </details>
        <?php endforeach; ?>
    </nav>
    <div class="lisso-nav-actions">
        <button type="button" class="wx-nav-command" data-wx-open aria-label="Find a workspace page"><i data-lucide="search" aria-hidden="true"></i><kbd data-wx-shortcut>Ctrl K</kbd></button>
        <div class="lisso-mode-switch" aria-label="Create a bill"><a href="create_purchase_bill.php" class="<?php echo $current_page === 'create_purchase_bill.php' ? 'selected' : ''; ?>">Purchase</a><a href="create_sales_bill.php" class="<?php echo $current_page !== 'create_purchase_bill.php' ? 'selected' : ''; ?>">New sale</a></div>
        <button type="button" class="lisso-menu-button" onclick="toggleSidebar()" aria-label="Open all pages" aria-controls="sidebar" aria-expanded="false"><i data-lucide="menu"></i></button>
    </div>
</header>
<?php require __DIR__ . '/workspace_experience.php'; ?>
<script defer src="js/workspace-experience.js?v=<?php echo filemtime(__DIR__ . '/js/workspace-experience.js'); ?>"></script>
<div class="application-print-heading">
    <div><strong>Medicine Shop</strong><h1 id="applicationPrintTitle"><?php echo htmlspecialchars($workspace_title); ?></h1></div>
    <div class="application-print-meta"><p id="applicationPrintContext">Inventory &amp; billing</p><p>Printed: <time id="applicationPrintDate"><?php echo date('d M Y'); ?></time></p><p>Currency: Rs.</p><p class="application-print-note" id="applicationPrintNote"></p></div>
</div>

<script>
    function toggleSidebar() {
        document.getElementById('sidebar').classList.toggle('active');
    }

    document.addEventListener('DOMContentLoaded', function () {
        if (window.lucide) lucide.createIcons({ attrs: { width: 18, height: 18, 'stroke-width': 2.1 } });
        const drawer = document.getElementById('sidebar');
        const drawerTrigger = document.querySelector('.lisso-menu-button');
        let drawerWasOpen = false;
        function syncDrawer() {
            const open = drawer.classList.contains('active');
            drawer.inert = !open;
            drawerTrigger.setAttribute('aria-expanded', String(open));
            document.body.classList.toggle('navigation-open', open);
            if (open) drawer.querySelector('.drawer-close').focus();
            else if (drawerWasOpen) drawerTrigger.focus();
            drawerWasOpen = open;
        }
        syncDrawer();
        new MutationObserver(syncDrawer).observe(drawer, { attributes: true, attributeFilter: ['class'] });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                if (drawer.classList.contains('active')) { drawer.classList.remove('active'); drawerTrigger.focus(); }
                document.querySelectorAll('.lisso-nav-group[open]').forEach(group => group.open = false);
            }
            if (event.key === 'Tab' && drawer.classList.contains('active')) {
                const controls = drawer.querySelectorAll('button, a[href]');
                const first = controls[0], last = controls[controls.length - 1];
                if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
                else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
            }
        });
        document.querySelectorAll('.lisso-nav-group').forEach(function (group) {
            group.addEventListener('toggle', function () {
                if (group.open) document.querySelectorAll('.lisso-nav-group').forEach(other => { if (other !== group) other.open = false; });
            });
        });
        document.addEventListener('click', function (event) {
            if (!event.target.closest('.lisso-nav-group')) document.querySelectorAll('.lisso-nav-group[open]').forEach(group => group.open = false);
        });
        // Direct browser printing works on every screen, not just invoice links.
        // Mark only semantic action columns; never hide a financial data column.
        function prepareApplicationPrint() {
            const pageTitle = document.querySelector('.main-content .page-header h2, .main-content h1');
            const pageContext = document.querySelector('.main-content .page-header p');
            if (pageTitle) document.getElementById('applicationPrintTitle').textContent = document.body.classList.contains('cx-dashboard') ? 'Business overview' : pageTitle.textContent.trim();
            if (pageContext) document.getElementById('applicationPrintContext').textContent = pageContext.textContent.trim();
            document.getElementById('applicationPrintDate').textContent = new Date().toLocaleString('en-GB', { dateStyle: 'medium', timeStyle: 'short' });
            const editing = document.querySelector('.main-content form[method="POST" i]');
            document.getElementById('applicationPrintNote').textContent = editing ? 'Form preview — values shown may not have been saved.' : '';
            document.querySelectorAll('.main-content table').forEach(function (table) {
                const headers = table.tHead && table.tHead.rows[0];
                if (!headers) return;
                let column = 0;
                Array.from(headers.cells).forEach(function (header) {
                    const actionColumn = /^(actions?|manage)$/i.test(header.textContent.trim());
                    if (actionColumn) {
                        header.classList.add('print-action-column');
                        Array.from(table.rows).forEach(function (row) {
                            let cellColumn = 0;
                            Array.from(row.cells).forEach(function (cell) {
                                if (cellColumn === column && cell.colSpan === 1) cell.classList.add('print-action-column');
                                cellColumn += cell.colSpan;
                            });
                        });
                    }
                    column += header.colSpan;
                });
            });
        }
        prepareApplicationPrint();
        window.addEventListener('beforeprint', prepareApplicationPrint);
    });
</script>