<?php
/**
 * Read-only presentation helpers. Require once, then pass plain text (not HTML).
 * Styles: css/page-polish.css, loaded after lisso.css by the shared shell.
 * Overview: eyebrow, title, description, tone (yellow|lavender|mint),
 * metrics (2–4 label/value/optional note arrays), links (label/href/optional icon).
 * Guide: eyebrow, title, description, steps (title/text arrays), same links.
 */
function poEscape($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function poLocalHref(string $href): string
{
    // Only relative application PHP URLs; never script, external or protocol-relative URLs.
    if (preg_match('/[\x00-\x20\\\\]/', $href) || strpos($href, '..') !== false ||
        !preg_match('~^(?!/)[a-zA-Z0-9_/-]+\.php(?:[?#][^<>]*)?$~D', $href)) {
        return '';
    }
    return $href;
}

function poRenderLinks(array $links): void
{
    if (!$links) {
        return;
    }
    echo '<nav class="po-links" aria-label="Related actions">';
    foreach ($links as $link) {
        $href = poLocalHref((string) ($link['href'] ?? ''));
        if ($href === '') {
            continue;
        }
        echo '<a class="po-link" href="' . poEscape($href) . '">';
        if (!empty($link['icon']) && preg_match('/^[a-z0-9-]+$/D', $link['icon'])) {
            echo '<i class="po-link-icon" data-lucide="' . poEscape($link['icon']) . '" aria-hidden="true"></i>';
        }
        echo '<span>' . poEscape($link['label'] ?? '') . '</span><span class="po-link-arrow" aria-hidden="true">↗</span></a>';
    }
    echo '</nav>';
}

function renderPageOverview(array $options): void
{
    $tone = in_array($options['tone'] ?? '', ['yellow', 'lavender', 'mint'], true) ? $options['tone'] : 'yellow';
    $metrics = array_slice($options['metrics'] ?? [], 0, 4);
    ?>
    <section class="po-overview po-tone-<?php echo $tone; ?>" aria-label="<?php echo poEscape($options['eyebrow'] ?? 'Page overview'); ?>">
        <div class="po-story">
            <span class="po-eyebrow"><?php echo poEscape($options['eyebrow'] ?? 'At a glance'); ?></span>
            <span class="po-orbit" aria-hidden="true"><svg viewBox="0 0 64 64" fill="none"><circle cx="32" cy="32" r="20"/><ellipse cx="32" cy="32" rx="29" ry="10" transform="rotate(-40 32 32)"/><path d="M32 23v18M23 32h18"/></svg></span>
            <h3 class="po-title"><?php echo poEscape($options['title'] ?? ''); ?></h3>
            <p class="po-description"><?php echo poEscape($options['description'] ?? ''); ?></p>
            <?php poRenderLinks($options['links'] ?? []); ?>
        </div>
        <?php if ($metrics): ?>
        <dl class="po-metrics">
            <?php foreach ($metrics as $metric): ?>
            <div class="po-metric">
                <dt><?php echo poEscape($metric['label'] ?? ''); ?></dt>
                <dd><?php echo poEscape($metric['value'] ?? ''); ?><?php if (!empty($metric['note'])): ?><span class="po-metric-note"><?php echo poEscape($metric['note']); ?></span><?php endif; ?></dd>
            </div>
            <?php endforeach; ?>
        </dl>
        <?php endif; ?>
    </section>
    <?php
}

function renderPageGuide(array $options): void
{
    ?>
    <aside class="po-guide" aria-label="<?php echo poEscape($options['eyebrow'] ?? 'Form guide'); ?>">
        <div class="po-guide-intro">
            <span class="po-eyebrow"><?php echo poEscape($options['eyebrow'] ?? 'A little guidance'); ?></span>
            <span class="po-guide-mark" aria-hidden="true">✳</span>
            <h3 class="po-title"><?php echo poEscape($options['title'] ?? ''); ?></h3>
            <p class="po-description"><?php echo poEscape($options['description'] ?? ''); ?></p>
        </div>
        <ol class="po-steps">
            <?php foreach ($options['steps'] ?? [] as $step): ?>
            <li><div><h4><?php echo poEscape($step['title'] ?? ''); ?></h4><p><?php echo poEscape($step['text'] ?? ''); ?></p></div></li>
            <?php endforeach; ?>
        </ol>
        <?php poRenderLinks($options['links'] ?? []); ?>
    </aside>
    <?php
}