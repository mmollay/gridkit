<?php
use GridKit\Table;
use GridKit\Form;
use GridKit\Modal;
use GridKit\StatCards;
use GridKit\Sidebar;
use GridKit\FilterChips;
use GridKit\YearFilter;
use GridKit\TableHeader;
use GridKit\Header;
use GridKit\Button;
use GridKit\Theme;
use GridKit\Layout;
use GridKit\Lang;
?>
<!-- ===== CHANGELOG ===== -->
<div class="demo-section" data-section="changelog">
    <h2>Changelog</h2>
    <?php
    $changelog = file_get_contents(__DIR__ . '/../../CHANGELOG.md');
    $versions = preg_split('/^## /m', $changelog);
    array_shift($versions);
    $count = 0;
    foreach ($versions as $v) {
        if ($count >= 8) break;
        if (str_starts_with($v, '[Unreleased]')) continue;
        $lines = explode("\n", trim($v));
        $title = array_shift($lines);
        $title = trim($title, '[] ');
        $parts = explode(' - ', $title, 2);
        $ver = $parts[0] ?? $title;
        $date = $parts[1] ?? '';
        echo '<div class="gk-segment" style="margin-bottom:12px">';
        echo '<div class="gk-segment-header" style="display:flex;align-items:center;gap:8px">';
        echo '<span style="font-family:monospace;background:var(--gk-primary);color:var(--gk-on-primary);padding:2px 10px;border-radius:4px;font-size:12px;font-weight:600">' . htmlspecialchars($ver) . '</span>';
        if ($date) echo '<span style="font-size:12px;color:var(--gk-on-surface-variant)">' . htmlspecialchars($date) . '</span>';
        echo '</div>';
        echo '<div style="font-size:13px;line-height:1.7;color:var(--gk-on-surface-variant);margin-top:8px">';
        $body = implode("\n", $lines);
        $body = htmlspecialchars($body);
        $body = preg_replace('/^### (.+)$/m', '<strong style="color:var(--gk-on-surface);display:block;margin-top:8px">$1</strong>', $body);
        $body = preg_replace('/^- (.+)$/m', '<span style="display:block;padding-left:12px">&middot; $1</span>', $body);
        echo $body;
        echo '</div></div>';
        $count++;
    }
    ?>
</div>

