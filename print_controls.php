<?php
// Shared helpers for the standalone, read-only document previews.
function documentEscape($value) {
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function documentDate($value) {
    $timestamp = $value ? strtotime($value) : false;
    return $timestamp === false ? 'Not recorded' : date('d M Y', $timestamp);
}

function documentError($status, $message, $backUrl, $backLabel) {
    http_response_code($status);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Document unavailable — Medicine Shop</title>
        <?php include __DIR__ . '/pdf_style.php'; ?>
    </head>
    <body>
        <main class="document document-error">
            <?php documentBrand(); ?>
            <p class="eyebrow">Document unavailable · <?php echo (int) $status; ?></p>
            <h1><?php echo documentEscape($message); ?></h1>
            <p>Return to the list and select an existing bill to preview.</p>
            <a class="control-link" href="<?php echo documentEscape($backUrl); ?>"><?php echo documentEscape($backLabel); ?></a>
        </main>
    </body>
    </html>
    <?php
    exit;
}

function documentBillId($backUrl, $backLabel) {
    $rawId = $_GET['id'] ?? null;
    $id = is_string($rawId) && preg_match('/^[1-9][0-9]*$/D', $rawId)
        ? filter_var($rawId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : false;
    if ($id === false) {
        documentError(400, 'A valid bill ID is required.', $backUrl, $backLabel);
    }
    return $id;
}

function documentToolbar($backUrl, $backLabel, $landscape = false) {
    ?>
    <nav class="document-toolbar no-print" aria-label="Document controls">
        <a class="control-link" href="<?php echo documentEscape($backUrl); ?>"><span aria-hidden="true">←</span> <?php echo documentEscape($backLabel); ?></a>
        <p>A4 <?php echo $landscape ? 'landscape' : 'portrait'; ?> <span aria-hidden="true">·</span> Currency: Rs.</p>
        <button class="print-button" type="button" onclick="window.print()">Print / Save PDF</button>
    </nav>
    <?php
}

function documentBrand() {
    ?>
    <div class="document-brand">
        <svg class="brand-mark" width="42" height="42" viewBox="0 0 42 42" fill="none" aria-hidden="true" focusable="false">
            <rect x="1" y="1" width="40" height="40" rx="13" fill="#eee8f6" stroke="#202020" stroke-width="1.5" />
            <path d="M17 11h8v6h6v8h-6v6h-8v-6h-6v-8h6z" stroke="#202020" stroke-width="1.8" stroke-linejoin="round" />
        </svg>
        <div><p class="brand-name">Medicine Shop</p><p class="brand-subtitle">Inventory &amp; billing</p></div>
    </div>
    <?php
}

function documentFooter($description) {
    ?>
    <footer class="document-footer">
        <strong>Medicine Shop</strong>
        <span><?php echo documentEscape($description); ?> · Amounts in Rs.</span>
    </footer>
    <?php
}