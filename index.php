<?php
$version = trim(file_get_contents(__DIR__ . '/VERSION'));
$skillContent = file_get_contents(__DIR__ . '/GRIDKIT_SKILL.md');
$canonicalUrl = 'https://gridkit.ssi.at';

/**
 * Simple Markdown → HTML renderer for skill preview
 */
function renderSkillMd(string $md): string {
    $lines = explode("\n", $md);
    $html = '';
    $inCode = false;
    $codeLang = '';
    $codeBuffer = '';
    $inTable = false;
    $tableRows = [];
    $inList = false;

    $inline = function(string $text): string {
        $text = htmlspecialchars($text);
        $text = preg_replace('/`([^`]+)`/', '<code>$1</code>', $text);
        $text = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $text);
        $text = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/', '<a href="$2" target="_blank" rel="noopener">$1</a>', $text);
        return $text;
    };

    $flushTable = function() use (&$html, &$tableRows, &$inTable, $inline) {
        if (!$tableRows) return;
        $html .= '<div class="skill-table">';
        $header = array_shift($tableRows);
        // skip separator row
        if (isset($tableRows[0]) && preg_match('/^[\s|:-]+$/', $tableRows[0])) {
            array_shift($tableRows);
        }
        $headers = array_map('trim', array_filter(explode('|', $header)));
        foreach ($tableRows as $row) {
            $cells = array_map('trim', array_filter(explode('|', $row)));
            if (count($cells) < 2) continue;
            $html .= '<div class="skill-table-row">';
            for ($i = 0; $i < count($headers); $i++) {
                $val = $cells[$i] ?? '';
                $html .= '<div class="skill-table-cell">';
                if ($i === 0) $html .= '<span class="skill-table-label">' . $inline($val) . '</span>';
                else $html .= '<span class="skill-table-value">' . $inline($val) . '</span>';
                $html .= '</div>';
            }
            $html .= '</div>';
        }
        $html .= '</div>';
        $tableRows = [];
        $inTable = false;
    };

    $flushList = function() use (&$html, &$inList) {
        if ($inList) { $html .= '</ul>'; $inList = false; }
    };

    foreach ($lines as $line) {
        // Code blocks
        if (preg_match('/^```(\w*)/', $line, $m)) {
            if ($inCode) {
                $html .= '<div class="skill-code"><div class="skill-code-lang">' . htmlspecialchars($codeLang) . '</div><pre>' . htmlspecialchars($codeBuffer) . '</pre></div>';
                $inCode = false;
                $codeBuffer = '';
            } else {
                $flushTable();
                $flushList();
                $inCode = true;
                $codeLang = $m[1] ?: 'code';
            }
            continue;
        }
        if ($inCode) { $codeBuffer .= $line . "\n"; continue; }

        $trimmed = trim($line);
        if ($trimmed === '' || $trimmed === '---') {
            $flushTable();
            $flushList();
            continue;
        }

        // Table
        if (str_starts_with($trimmed, '|')) {
            $flushList();
            $inTable = true;
            $tableRows[] = $trimmed;
            continue;
        } else if ($inTable) {
            $flushTable();
        }

        // Headings
        if (preg_match('/^(#{1,4})\s+(.+)/', $trimmed, $m)) {
            $flushList();
            $level = strlen($m[1]);
            $tag = 'h' . min($level + 1, 5); // ## → h3, ### → h4
            $html .= '<' . $tag . ' class="skill-heading">' . $inline($m[2]) . '</' . $tag . '>';
            continue;
        }

        // Blockquote
        if (str_starts_with($trimmed, '>')) {
            $flushList();
            $html .= '<div class="skill-meta">' . $inline(ltrim($trimmed, '> ')) . '</div>';
            continue;
        }

        // List items
        if (preg_match('/^[-*]\s+(.+)/', $trimmed, $m)) {
            if (!$inList) { $html .= '<ul class="skill-list">'; $inList = true; }
            $html .= '<li>' . $inline($m[1]) . '</li>';
            continue;
        }
        // Numbered list
        if (preg_match('/^\d+\.\s+(.+)/', $trimmed, $m)) {
            if (!$inList) { $html .= '<ul class="skill-list skill-list-num">'; $inList = true; }
            $html .= '<li>' . $inline($m[1]) . '</li>';
            continue;
        }

        $flushList();
        $html .= '<p class="skill-para">' . $inline($trimmed) . '</p>';
    }

    $flushTable();
    $flushList();
    return $html;
}

$skillHtml = renderSkillMd($skillContent);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GRIDKit – Agent-Ready PHP Component Framework for Admin Dashboards</title>
    <meta name="description" content="GRIDKit is a zero-dependency PHP framework for building admin dashboards. 12 components, 1 CSS + 1 JS file, built for AI agents. Open Source on GitHub.">
    <meta name="keywords" content="PHP framework, admin dashboard, CRUD, AI agent, component framework, Material Design, open source">
    <meta name="author" content="Martin Mollay">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="<?= $canonicalUrl ?>">

    <!-- Open Graph -->
    <meta property="og:type" content="website">
    <meta property="og:title" content="GRIDKit – Agent-Ready PHP Component Framework">
    <meta property="og:description" content="Zero-dependency PHP framework for admin dashboards. 12 components, built for AI agents. Open Source.">
    <meta property="og:url" content="<?= $canonicalUrl ?>">
    <meta property="og:site_name" content="GRIDKit">
    <meta property="og:image" content="<?= $canonicalUrl ?>/og-image.png">

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="GRIDKit – Agent-Ready PHP Component Framework">
    <meta name="twitter:description" content="Zero-dependency PHP framework for admin dashboards. Built for AI agents.">

    <!-- Favicon -->
    <link rel="icon" href="/favicon.ico" type="image/x-icon">

    <!-- Structured Data -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "SoftwareSourceCode",
        "name": "GRIDKit",
        "description": "Agent-ready PHP component framework for admin dashboards. Zero dependencies, Material Design 3, AJAX-first.",
        "codeRepository": "https://github.com/mmollay/gridkit",
        "programmingLanguage": ["PHP", "JavaScript", "CSS"],
        "license": "https://opensource.org/licenses/MIT",
        "version": "<?= $version ?>",
        "author": {
            "@type": "Person",
            "name": "Martin Mollay"
        },
        "operatingSystem": "Cross-platform",
        "applicationCategory": "DeveloperApplication"
    }
    </script>

    <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --gk-primary: #4f46e5;
            --gk-primary-light: #818cf8;
            --gk-primary-dark: #3730a3;
            --gk-surface: #ffffff;
            --gk-surface-dim: #f8fafc;
            --gk-surface-container: #f1f5f9;
            --gk-text: #0f172a;
            --gk-text-secondary: #475569;
            --gk-text-muted: #94a3b8;
            --gk-border: #e2e8f0;
            --gk-success: #10b981;
            --gk-accent: #f59e0b;
            --gk-code-bg: #1e293b;
            --gk-code-text: #e2e8f0;
            --gk-gradient: linear-gradient(135deg, #4f46e5 0%, #7c3aed 50%, #2563eb 100%);
        }

        @media (prefers-color-scheme: dark) {
            :root {
                --gk-surface: #0f172a;
                --gk-surface-dim: #1e293b;
                --gk-surface-container: #1e293b;
                --gk-text: #f1f5f9;
                --gk-text-secondary: #94a3b8;
                --gk-text-muted: #64748b;
                --gk-border: #334155;
                --gk-code-bg: #0f172a;
                --gk-code-text: #e2e8f0;
            }
        }

        html { scroll-behavior: smooth; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            color: var(--gk-text);
            background: var(--gk-surface);
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
        }

        /* --- NAV --- */
        .nav {
            position: fixed; top: 0; left: 0; right: 0; z-index: 100;
            background: rgba(255,255,255,0.8);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--gk-border);
            transition: all 0.3s;
        }
        @media (prefers-color-scheme: dark) {
            .nav { background: rgba(15,23,42,0.85); }
        }
        .nav-inner {
            max-width: 1200px; margin: 0 auto; padding: 0 24px;
            display: flex; align-items: center; justify-content: space-between; height: 64px;
        }
        .nav-brand {
            font-size: 20px; font-weight: 800; color: var(--gk-primary);
            text-decoration: none; display: flex; align-items: center; gap: 8px;
        }
        .nav-brand span { font-size: 11px; font-weight: 500; color: var(--gk-text-muted); }
        .nav-links { display: flex; gap: 32px; align-items: center; }
        .nav-links a {
            color: var(--gk-text-secondary); text-decoration: none; font-size: 14px;
            font-weight: 500; transition: color 0.2s;
        }
        .nav-links a:hover { color: var(--gk-primary); }
        .nav-cta {
            background: var(--gk-primary) !important; color: #fff !important;
            padding: 8px 20px; border-radius: 8px; font-weight: 600;
            transition: background 0.2s, transform 0.1s;
        }
        .nav-cta:hover { background: var(--gk-primary-dark) !important; transform: translateY(-1px); }

        /* --- HERO --- */
        .hero {
            padding: 140px 24px 80px; text-align: center;
            background: linear-gradient(180deg, var(--gk-surface-dim) 0%, var(--gk-surface) 100%);
            position: relative; overflow: hidden;
        }
        .hero::before {
            content: ''; position: absolute; top: -50%; left: -50%; width: 200%; height: 200%;
            background: radial-gradient(circle at 50% 50%, rgba(79,70,229,0.06) 0%, transparent 60%);
            pointer-events: none;
        }
        .hero-badge {
            display: inline-flex; align-items: center; gap: 8px;
            background: var(--gk-surface-container); border: 1px solid var(--gk-border);
            padding: 6px 16px; border-radius: 100px; font-size: 13px; font-weight: 500;
            color: var(--gk-text-secondary); margin-bottom: 24px;
        }
        .hero-badge .dot {
            width: 8px; height: 8px; background: var(--gk-success); border-radius: 50%;
            animation: pulse 2s infinite;
        }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }

        .hero h1 {
            font-size: clamp(36px, 6vw, 64px); font-weight: 800; line-height: 1.1;
            margin-bottom: 20px; letter-spacing: -0.03em;
        }
        .hero h1 .gradient {
            background: var(--gk-gradient); -webkit-background-clip: text;
            -webkit-text-fill-color: transparent; background-clip: text;
        }
        .hero-sub {
            font-size: clamp(16px, 2.5vw, 20px); color: var(--gk-text-secondary);
            max-width: 640px; margin: 0 auto 40px; line-height: 1.6;
        }
        .hero-actions { display: flex; gap: 16px; justify-content: center; flex-wrap: wrap; }
        .btn {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 14px 28px; border-radius: 12px; font-size: 15px; font-weight: 600;
            text-decoration: none; transition: all 0.2s; border: none; cursor: pointer;
        }
        .btn-primary {
            background: var(--gk-primary); color: #fff;
            box-shadow: 0 4px 14px rgba(79,70,229,0.3);
        }
        .btn-primary:hover { background: var(--gk-primary-dark); transform: translateY(-2px); box-shadow: 0 6px 20px rgba(79,70,229,0.4); }
        .btn-secondary {
            background: var(--gk-surface-container); color: var(--gk-text);
            border: 1px solid var(--gk-border);
        }
        .btn-secondary:hover { border-color: var(--gk-primary); color: var(--gk-primary); }
        .btn .material-icons { font-size: 20px; }

        .hero-stats {
            display: flex; justify-content: center; gap: 48px; margin-top: 56px;
            padding-top: 40px; border-top: 1px solid var(--gk-border);
        }
        .hero-stat-num { font-size: 28px; font-weight: 800; color: var(--gk-primary); }
        .hero-stat-label { font-size: 13px; color: var(--gk-text-muted); margin-top: 4px; }

        /* --- SECTIONS --- */
        .section { padding: 80px 24px; }
        .section-alt { background: var(--gk-surface-dim); }
        .container { max-width: 1200px; margin: 0 auto; }
        .section-header { text-align: center; margin-bottom: 56px; }
        .section-header h2 {
            font-size: clamp(28px, 4vw, 40px); font-weight: 800; margin-bottom: 16px;
            letter-spacing: -0.02em;
        }
        .section-header p { font-size: 17px; color: var(--gk-text-secondary); max-width: 600px; margin: 0 auto; }

        /* --- FEATURES GRID --- */
        .features-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 24px;
        }
        .feature-card {
            background: var(--gk-surface); border: 1px solid var(--gk-border);
            border-radius: 16px; padding: 32px; transition: all 0.3s;
        }
        .feature-card:hover { border-color: var(--gk-primary-light); transform: translateY(-4px); box-shadow: 0 12px 40px rgba(0,0,0,0.08); }
        .feature-icon {
            width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center;
            background: linear-gradient(135deg, rgba(79,70,229,0.1), rgba(124,58,237,0.1));
            color: var(--gk-primary); margin-bottom: 20px;
        }
        .feature-card h3 { font-size: 18px; font-weight: 700; margin-bottom: 8px; }
        .feature-card p { font-size: 14px; color: var(--gk-text-secondary); line-height: 1.6; }

        /* --- AGENT SECTION --- */
        .agent-section { background: var(--gk-code-bg); color: #e2e8f0; padding: 80px 24px; }
        .agent-section .section-header h2 { color: #f1f5f9; }
        .agent-section .section-header p { color: #94a3b8; }
        .agent-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 48px; align-items: start; }
        .agent-info h3 { font-size: 22px; font-weight: 700; margin-bottom: 16px; color: #f1f5f9; }
        .agent-info p { color: #94a3b8; margin-bottom: 24px; line-height: 1.7; }
        .agent-steps { list-style: none; counter-reset: steps; }
        .agent-steps li {
            counter-increment: steps; padding: 12px 0; padding-left: 40px;
            position: relative; color: #cbd5e1; font-size: 15px;
        }
        .agent-steps li::before {
            content: counter(steps); position: absolute; left: 0; top: 12px;
            width: 28px; height: 28px; border-radius: 8px;
            background: rgba(79,70,229,0.2); color: var(--gk-primary-light);
            display: flex; align-items: center; justify-content: center;
            font-size: 13px; font-weight: 700;
        }

        /* --- INTERACTIVE DEMO --- */
        .demo-terminal {
            background: #0d1117; border-radius: 12px; overflow: hidden;
            border: 1px solid #30363d; box-shadow: 0 16px 48px rgba(0,0,0,0.3);
        }
        .demo-terminal-bar {
            display: flex; align-items: center; gap: 8px; padding: 12px 16px;
            background: #161b22; border-bottom: 1px solid #30363d;
        }
        .demo-terminal-dot { width: 12px; height: 12px; border-radius: 50%; }
        .demo-terminal-dot:nth-child(1) { background: #ff5f56; }
        .demo-terminal-dot:nth-child(2) { background: #ffbd2e; }
        .demo-terminal-dot:nth-child(3) { background: #27c93f; }
        .demo-terminal-title { font-size: 12px; color: #8b949e; margin-left: auto; font-family: 'JetBrains Mono', monospace; }
        .demo-terminal-body {
            padding: 20px; font-family: 'JetBrains Mono', monospace; font-size: 13px;
            line-height: 1.8; min-height: 320px; max-height: 420px; overflow-y: auto;
        }
        .demo-terminal-body .prompt { color: #7ee787; }
        .demo-terminal-body .cmd { color: #e6edf3; }
        .demo-terminal-body .comment { color: #8b949e; }
        .demo-terminal-body .output { color: #79c0ff; }
        .demo-terminal-body .highlight { color: #d2a8ff; }
        .demo-terminal-body .success { color: #3fb950; }
        .demo-terminal-body .line { margin-bottom: 2px; min-height: 22px; }
        .demo-terminal-body .cursor {
            display: inline-block; width: 8px; height: 16px; background: #e6edf3;
            animation: blink 1s steps(1) infinite; vertical-align: text-bottom;
        }
        @keyframes blink { 0%, 100% { opacity: 1; } 50% { opacity: 0; } }

        .demo-controls {
            display: flex; gap: 8px; padding: 12px 16px;
            background: #161b22; border-top: 1px solid #30363d;
        }
        .demo-btn {
            padding: 6px 16px; border-radius: 6px; font-size: 12px; font-weight: 600;
            cursor: pointer; transition: all 0.2s; border: 1px solid #30363d;
            background: #21262d; color: #c9d1d9; font-family: 'JetBrains Mono', monospace;
        }
        .demo-btn:hover { background: #30363d; border-color: #8b949e; }
        .demo-btn.active { background: rgba(79,70,229,0.2); border-color: var(--gk-primary-light); color: var(--gk-primary-light); }

        /* --- CODE BLOCK --- */
        .code-block {
            background: var(--gk-code-bg); border-radius: 12px; overflow: hidden;
            border: 1px solid var(--gk-border); margin: 24px 0;
        }
        .code-header {
            display: flex; align-items: center; justify-content: space-between;
            padding: 12px 16px; background: rgba(0,0,0,0.2); border-bottom: 1px solid rgba(255,255,255,0.06);
        }
        .code-lang { font-size: 12px; color: #8b949e; font-family: 'JetBrains Mono', monospace; }
        .code-copy {
            padding: 4px 12px; background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.1);
            border-radius: 6px; color: #8b949e; font-size: 12px; cursor: pointer;
            font-family: 'JetBrains Mono', monospace; transition: all 0.2s;
        }
        .code-copy:hover { background: rgba(255,255,255,0.1); color: #e6edf3; }
        .code-body {
            padding: 20px; overflow-x: auto; font-family: 'JetBrains Mono', monospace;
            font-size: 13px; line-height: 1.7; color: var(--gk-code-text);
        }
        .code-body .kw { color: #ff7b72; }
        .code-body .str { color: #a5d6ff; }
        .code-body .fn { color: #d2a8ff; }
        .code-body .var { color: #ffa657; }
        .code-body .cmt { color: #8b949e; }

        /* --- SKILL DOWNLOAD --- */
        .skill-section {
            background: linear-gradient(135deg, rgba(79,70,229,0.05) 0%, rgba(124,58,237,0.05) 100%);
            border: 1px solid var(--gk-border); border-radius: 16px;
            padding: 40px; margin-top: 48px;
        }
        .skill-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; flex-wrap: wrap; gap: 16px; }
        .skill-header h3 { font-size: 20px; font-weight: 700; }
        .skill-actions { display: flex; gap: 8px; }
        .skill-desc { color: var(--gk-text-secondary); margin-bottom: 24px; font-size: 15px; }
        .skill-preview {
            background: var(--gk-surface); border: 1px solid var(--gk-border);
            border-radius: 12px; padding: 32px; max-height: 600px; overflow-y: auto;
            line-height: 1.7; position: relative;
        }
        .skill-preview.collapsed { max-height: 320px; overflow: hidden; }
        .skill-preview.collapsed::after {
            content: ''; position: absolute; bottom: 0; left: 0; right: 0; height: 80px;
            background: linear-gradient(transparent, var(--gk-surface));
            pointer-events: none;
        }
        .skill-toggle {
            display: block; margin: 16px auto 0; padding: 8px 24px; border-radius: 8px;
            background: var(--gk-surface-container); border: 1px solid var(--gk-border);
            color: var(--gk-primary); font-size: 14px; font-weight: 600; cursor: pointer;
            transition: all 0.2s; font-family: inherit;
        }
        .skill-toggle:hover { background: var(--gk-primary); color: #fff; }
        .skill-heading { margin: 24px 0 12px; color: var(--gk-text); }
        .skill-preview h2.skill-heading { font-size: 22px; font-weight: 700; border-bottom: 2px solid var(--gk-border); padding-bottom: 8px; }
        .skill-preview h3.skill-heading { font-size: 18px; font-weight: 700; }
        .skill-preview h4.skill-heading { font-size: 15px; font-weight: 600; color: var(--gk-text-secondary); }
        .skill-preview h5.skill-heading { font-size: 14px; font-weight: 600; color: var(--gk-text-muted); }
        .skill-meta {
            font-size: 14px; color: var(--gk-text-secondary);
            border-left: 3px solid var(--gk-primary); padding: 8px 16px; margin: 12px 0;
            background: rgba(79,70,229,0.04); border-radius: 0 8px 8px 0;
        }
        .skill-meta code { background: rgba(79,70,229,0.1); padding: 2px 6px; border-radius: 4px; font-size: 13px; }
        .skill-para { font-size: 14px; color: var(--gk-text-secondary); margin: 8px 0; }
        .skill-para code, .skill-list code {
            background: var(--gk-surface-container); padding: 2px 6px; border-radius: 4px;
            font-family: 'JetBrains Mono', monospace; font-size: 12px; color: var(--gk-primary-dark);
        }
        .skill-para a, .skill-list a { color: var(--gk-primary); text-decoration: none; }
        .skill-para a:hover, .skill-list a:hover { text-decoration: underline; }
        .skill-list {
            list-style: none; margin: 8px 0; padding: 0;
        }
        .skill-list li {
            position: relative; padding: 6px 0 6px 20px; font-size: 14px; color: var(--gk-text-secondary);
        }
        .skill-list li::before { content: '›'; position: absolute; left: 4px; color: var(--gk-primary); font-weight: 700; }
        .skill-list-num { counter-reset: snum; }
        .skill-list-num li { padding-left: 28px; }
        .skill-list-num li::before {
            counter-increment: snum; content: counter(snum) '.';
            position: absolute; left: 4px; color: var(--gk-primary); font-weight: 600; font-size: 13px;
        }
        .skill-table { margin: 12px 0; border: 1px solid var(--gk-border); border-radius: 10px; overflow: hidden; }
        .skill-table-row {
            display: flex; gap: 0; border-bottom: 1px solid var(--gk-border);
            font-size: 13px;
        }
        .skill-table-row:last-child { border-bottom: none; }
        .skill-table-row:nth-child(even) { background: var(--gk-surface-dim); }
        .skill-table-cell { padding: 10px 14px; flex: 1; }
        .skill-table-cell:first-child { flex: 0 0 140px; }
        .skill-table-label { font-weight: 600; color: var(--gk-text); }
        .skill-table-label code { font-size: 12px; }
        .skill-table-value { color: var(--gk-text-secondary); }
        .skill-code {
            margin: 12px 0; border-radius: 10px; overflow: hidden;
            border: 1px solid var(--gk-border); background: var(--gk-code-bg);
        }
        .skill-code .skill-code-lang {
            padding: 6px 14px; font-size: 11px; font-weight: 600;
            color: #8b949e; background: rgba(0,0,0,0.2); text-transform: uppercase;
            font-family: 'JetBrains Mono', monospace; letter-spacing: 0.05em;
        }
        .skill-code pre {
            padding: 16px; font-family: 'JetBrains Mono', monospace; font-size: 12px;
            line-height: 1.6; color: var(--gk-code-text); overflow-x: auto; margin: 0;
        }
        @media (prefers-color-scheme: dark) {
            .skill-preview { background: #1e293b; border-color: #334155; }
            .skill-preview.collapsed::after { background: linear-gradient(transparent, #1e293b); }
            .skill-para code, .skill-list code { background: #334155; color: #818cf8; }
            .skill-table { border-color: #334155; }
            .skill-table-row { border-bottom-color: #334155; }
            .skill-table-row:nth-child(even) { background: rgba(255,255,255,0.03); }
        }

        /* --- COMPONENTS PREVIEW --- */
        .components-preview {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 16px; margin-top: 40px;
        }
        .comp-card {
            background: var(--gk-surface); border: 1px solid var(--gk-border);
            border-radius: 12px; padding: 20px; text-align: center;
            transition: all 0.2s;
        }
        .comp-card:hover { border-color: var(--gk-primary-light); }
        .comp-card .material-icons { font-size: 32px; color: var(--gk-primary); margin-bottom: 8px; }
        .comp-card h4 { font-size: 15px; font-weight: 600; margin-bottom: 4px; }
        .comp-card p { font-size: 12px; color: var(--gk-text-muted); }

        /* --- FOOTER --- */
        .footer {
            padding: 48px 24px; border-top: 1px solid var(--gk-border);
            background: var(--gk-surface-dim);
        }
        .footer-inner {
            max-width: 1200px; margin: 0 auto;
            display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;
        }
        .footer-left { font-size: 14px; color: var(--gk-text-muted); }
        .footer-left a { color: var(--gk-text-secondary); text-decoration: none; }
        .footer-links { display: flex; gap: 24px; }
        .footer-links a { color: var(--gk-text-muted); text-decoration: none; font-size: 14px; transition: color 0.2s; }
        .footer-links a:hover { color: var(--gk-primary); }

        /* --- RESPONSIVE --- */
        @media (max-width: 768px) {
            .nav-links { display: none; }
            .hero-stats { flex-direction: column; gap: 16px; }
            .agent-grid { grid-template-columns: 1fr; }
            .hero { padding: 120px 16px 60px; }
            .section { padding: 56px 16px; }
            .features-grid { grid-template-columns: 1fr; }
            .skill-header { flex-direction: column; align-items: flex-start; }
        }
    </style>
</head>
<body>

<!-- Navigation -->
<nav class="nav" role="navigation" aria-label="Main navigation">
    <div class="nav-inner">
        <a href="/" class="nav-brand">
            <span class="material-icons" style="font-size:24px">widgets</span>
            GRIDKit <span>v<?= $version ?></span>
        </a>
        <div class="nav-links">
            <a href="#features">Features</a>
            <a href="#agent">Agent Skill</a>
            <a href="#components">Components</a>
            <a href="/demo/">Demo</a>
            <a href="https://github.com/mmollay/gridkit" class="nav-cta" target="_blank" rel="noopener">
                GitHub
            </a>
        </div>
    </div>
</nav>

<!-- Hero -->
<header class="hero">
    <div class="hero-badge">
        <span class="dot"></span>
        Open Source &middot; MIT License &middot; v<?= $version ?>
    </div>
    <h1>
        Build Admin Dashboards<br>
        <span class="gradient">Your AI Agent Understands</span>
    </h1>
    <p class="hero-sub">
        GRIDKit is a zero-dependency PHP component framework designed for AI agents.
        12 components, 1 CSS + 1 JS file, no build process. Your agent reads the skill file
        and builds complete CRUD applications in seconds.
    </p>
    <div class="hero-actions">
        <a href="https://github.com/mmollay/gridkit" class="btn btn-primary" target="_blank" rel="noopener">
            <span class="material-icons">code</span> View on GitHub
        </a>
        <a href="#agent" class="btn btn-secondary">
            <span class="material-icons">smart_toy</span> Agent Skill
        </a>
        <a href="/demo/" class="btn btn-secondary">
            <span class="material-icons">visibility</span> Live Demo
        </a>
    </div>
    <div class="hero-stats">
        <div>
            <div class="hero-stat-num">12</div>
            <div class="hero-stat-label">Components</div>
        </div>
        <div>
            <div class="hero-stat-num">0</div>
            <div class="hero-stat-label">Dependencies</div>
        </div>
        <div>
            <div class="hero-stat-num">2</div>
            <div class="hero-stat-label">Files (CSS + JS)</div>
        </div>
        <div>
            <div class="hero-stat-num">6</div>
            <div class="hero-stat-label">Themes</div>
        </div>
    </div>
</header>

<!-- Features -->
<section class="section section-alt" id="features">
    <div class="container">
        <div class="section-header">
            <h2>Why GRIDKit?</h2>
            <p>Everything you need to build production-ready admin dashboards. Nothing you don't.</p>
        </div>
        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon"><span class="material-icons">smart_toy</span></div>
                <h3>Agent-First Design</h3>
                <p>Built with AI agents in mind. Feed the skill file to your AI assistant and it generates complete GRIDKit applications — tables, forms, modals, authentication.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><span class="material-icons">bolt</span></div>
                <h3>Zero Dependencies</h3>
                <p>One CSS file. One JS file. No npm, no Composer, no build process. Clone and go. Works with any PHP 8.2+ project.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><span class="material-icons">palette</span></div>
                <h3>Material Design 3</h3>
                <p>6 complete themes (Indigo, Ocean, Forest, Rose, Amber, Slate) with light and dark mode. All via CSS Custom Properties.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><span class="material-icons">sync</span></div>
                <h3>AJAX-First</h3>
                <p>No page reloads. Tables search, sort, filter, paginate via AJAX. Forms submit and validate via AJAX. Everything stays fast.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><span class="material-icons">grid_view</span></div>
                <h3>Fluent PHP API</h3>
                <p>Declarative, chainable API. Define a complete data table with search, sorting, pagination, and modals in under 15 lines of PHP.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><span class="material-icons">phone_iphone</span></div>
                <h3>Responsive by Default</h3>
                <p>Every component is mobile-ready. Tables switch to card layout, sidebars become overlays, forms reflow to single column.</p>
            </div>
        </div>
    </div>
</section>

<!-- Agent Skill Section -->
<section class="agent-section" id="agent">
    <div class="container">
        <div class="section-header">
            <h2>Agent Skill — Let AI Build For You</h2>
            <p>Give your AI agent the GRIDKit skill file. It knows every component, every pattern, every best practice.</p>
        </div>

        <div class="agent-grid">
            <div class="agent-info">
                <h3>How It Works</h3>
                <p>
                    The GRIDKit Agent Skill is a structured document that teaches any AI assistant
                    (Claude, GPT, Gemini, or any LLM) how to use GRIDKit optimally. It contains
                    component references, code patterns, and best practices — everything an agent
                    needs to generate correct GRIDKit code on the first try.
                </p>
                <ol class="agent-steps">
                    <li>Download <code>GRIDKIT_SKILL.md</code> from the repository</li>
                    <li>Add it to your AI agent's context or project knowledge</li>
                    <li>Describe what you need: "Create a user management dashboard"</li>
                    <li>The agent generates working GRIDKit PHP code — tables, forms, modals, all wired up</li>
                </ol>
            </div>

            <!-- Interactive Demo Terminal -->
            <div class="demo-terminal" role="region" aria-label="Interactive agent demo">
                <div class="demo-terminal-bar">
                    <div class="demo-terminal-dot"></div>
                    <div class="demo-terminal-dot"></div>
                    <div class="demo-terminal-dot"></div>
                    <div class="demo-terminal-title">Agent + GRIDKit Skill</div>
                </div>
                <div class="demo-terminal-body" id="demo-output">
                    <div class="line"><span class="comment">// Click a scenario below to see it in action</span></div>
                    <div class="line">&nbsp;</div>
                    <div class="line"><span class="prompt">user&gt;</span> <span class="cmd">Build me a product management table</span></div>
                    <div class="line"><span class="cursor"></span></div>
                </div>
                <div class="demo-controls">
                    <button class="demo-btn active" onclick="runDemo('table')">CRUD Table</button>
                    <button class="demo-btn" onclick="runDemo('form')">Form</button>
                    <button class="demo-btn" onclick="runDemo('dashboard')">Dashboard</button>
                    <button class="demo-btn" onclick="runDemo('auth')">Auth</button>
                </div>
            </div>
        </div>

        <!-- Skill Download -->
        <div class="skill-section">
            <div class="skill-header">
                <h3>GRIDKIT_SKILL.md</h3>
                <div class="skill-actions">
                    <button class="btn btn-secondary" onclick="copySkill()" id="copy-btn" style="font-size:13px;padding:10px 20px;">
                        <span class="material-icons" style="font-size:16px">content_copy</span> Copy Skill
                    </button>
                    <a href="https://github.com/mmollay/gridkit/blob/main/GRIDKIT_SKILL.md" class="btn btn-primary" target="_blank" rel="noopener" style="font-size:13px;padding:10px 20px;">
                        <span class="material-icons" style="font-size:16px">download</span> View on GitHub
                    </a>
                </div>
            </div>
            <p class="skill-desc">
                Add this file to your AI agent's project context. It contains complete documentation for all 12 components,
                code patterns, JavaScript API reference, and common recipes.
            </p>
            <div class="skill-preview collapsed" id="skill-preview"><?= $skillHtml ?></div>
            <button class="skill-toggle" id="skill-toggle" onclick="toggleSkill()">Show full document</button>
        </div>
    </div>
</section>

<!-- Components -->
<section class="section section-alt" id="components">
    <div class="container">
        <div class="section-header">
            <h2>12 Production-Ready Components</h2>
            <p>Each component follows the same fluent PHP API. Chainable, declarative, zero boilerplate.</p>
        </div>

        <!-- Quick Code Example -->
        <div class="code-block">
            <div class="code-header">
                <span class="code-lang">PHP — Complete CRUD in 12 lines</span>
                <button class="code-copy" onclick="copyCode(this)">Copy</button>
            </div>
            <div class="code-body">
<pre><span class="var">$table</span> = <span class="kw">new</span> <span class="fn">Table</span>(<span class="str">'products'</span>);
<span class="var">$table</span>-><span class="fn">query</span>(<span class="var">$db</span>, <span class="str">"SELECT * FROM products ORDER BY name"</span>)
    -><span class="fn">search</span>([<span class="str">'name'</span>, <span class="str">'sku'</span>])
    -><span class="fn">column</span>(<span class="str">'name'</span>, <span class="str">'Product'</span>, [<span class="str">'sortable'</span> => <span class="kw">true</span>])
    -><span class="fn">column</span>(<span class="str">'sku'</span>, <span class="str">'SKU'</span>, [<span class="str">'width'</span> => <span class="str">'120px'</span>])
    -><span class="fn">column</span>(<span class="str">'price'</span>, <span class="str">'Price'</span>, [<span class="str">'format'</span> => <span class="str">'currency'</span>, <span class="str">'sortable'</span> => <span class="kw">true</span>])
    -><span class="fn">column</span>(<span class="str">'is_active'</span>, <span class="str">'Status'</span>, [<span class="str">'format'</span> => <span class="str">'label'</span>])
    -><span class="fn">button</span>(<span class="str">'edit'</span>, [<span class="str">'icon'</span> => <span class="str">'edit'</span>, <span class="str">'modal'</span> => <span class="str">'edit_product'</span>])
    -><span class="fn">button</span>(<span class="str">'delete'</span>, [<span class="str">'icon'</span> => <span class="str">'delete'</span>, <span class="str">'modal'</span> => <span class="str">'del'</span>, <span class="str">'color'</span> => <span class="str">'error'</span>])
    -><span class="fn">modal</span>(<span class="str">'edit_product'</span>, <span class="str">'Edit'</span>, <span class="str">'forms/product.php'</span>, [<span class="str">'size'</span> => <span class="str">'medium'</span>])
    -><span class="fn">newButton</span>(<span class="str">'New Product'</span>, [<span class="str">'modal'</span> => <span class="str">'edit_product'</span>])
    -><span class="fn">paginate</span>(<span class="kw">25</span>)
    -><span class="fn">render</span>();</pre>
            </div>
        </div>

        <div class="components-preview">
            <div class="comp-card">
                <span class="material-icons">table_chart</span>
                <h4>Table</h4>
                <p>Search, sort, paginate, AJAX reload, multi-select</p>
            </div>
            <div class="comp-card">
                <span class="material-icons">edit_note</span>
                <h4>Form</h4>
                <p>16-column grid, 15 field types, AJAX submit</p>
            </div>
            <div class="comp-card">
                <span class="material-icons">menu</span>
                <h4>Sidebar</h4>
                <p>Groups, badges, collapse, mobile overlay</p>
            </div>
            <div class="comp-card">
                <span class="material-icons">web</span>
                <h4>Header</h4>
                <p>Fixed, search, user menu, theme switcher</p>
            </div>
            <div class="comp-card">
                <span class="material-icons">open_in_new</span>
                <h4>Modal</h4>
                <p>Stackable dialogs, form-ready, sizes</p>
            </div>
            <div class="comp-card">
                <span class="material-icons">analytics</span>
                <h4>StatCards</h4>
                <p>KPI display with trends and colors</p>
            </div>
            <div class="comp-card">
                <span class="material-icons">lock</span>
                <h4>Auth</h4>
                <p>Session auth, bcrypt, remember-me</p>
            </div>
            <div class="comp-card">
                <span class="material-icons">palette</span>
                <h4>Theme</h4>
                <p>6 themes, light/dark mode</p>
            </div>
            <div class="comp-card">
                <span class="material-icons">smart_button</span>
                <h4>Button</h4>
                <p>Filled, outlined, text, tonal, FAB</p>
            </div>
            <div class="comp-card">
                <span class="material-icons">layers</span>
                <h4>Layout</h4>
                <p>Sidebar-first, header-first modes</p>
            </div>
            <div class="comp-card">
                <span class="material-icons">filter_list</span>
                <h4>FilterChips</h4>
                <p>Clickable filter chip buttons</p>
            </div>
            <div class="comp-card">
                <span class="material-icons">date_range</span>
                <h4>YearFilter</h4>
                <p>Year navigation filter</p>
            </div>
        </div>
    </div>
</section>

<!-- Get Started -->
<section class="section" id="start">
    <div class="container">
        <div class="section-header">
            <h2>Get Started in 30 Seconds</h2>
            <p>Clone, include, build. No configuration, no package managers, no build tools.</p>
        </div>

        <div class="code-block">
            <div class="code-header">
                <span class="code-lang">Terminal</span>
                <button class="code-copy" onclick="copyCode(this)">Copy</button>
            </div>
            <div class="code-body">
<pre><span class="cmt"># Clone GRIDKit</span>
<span class="cmd">git clone https://github.com/mmollay/gridkit.git</span>

<span class="cmt"># Copy the skeleton as your starting point</span>
<span class="cmd">cp gridkit/skeleton.php my-app/index.php</span>

<span class="cmt"># That's it. Open in browser.</span></pre>
            </div>
        </div>

        <div style="text-align:center; margin-top:40px;">
            <a href="https://github.com/mmollay/gridkit" class="btn btn-primary" target="_blank" rel="noopener">
                <span class="material-icons">code</span> Star on GitHub
            </a>
            <a href="/demo/" class="btn btn-secondary" style="margin-left:12px">
                <span class="material-icons">visibility</span> Explore Components
            </a>
        </div>
    </div>
</section>

<!-- Footer -->
<footer class="footer" role="contentinfo">
    <div class="footer-inner">
        <div class="footer-left">
            GRIDKit v<?= $version ?> &middot; MIT License &middot; Made by <a href="https://github.com/mmollay" target="_blank" rel="noopener">Martin Mollay</a>
        </div>
        <div class="footer-links">
            <a href="https://github.com/mmollay/gridkit" target="_blank" rel="noopener">GitHub</a>
            <a href="/demo/">Demo</a>
            <a href="https://github.com/mmollay/gridkit/blob/main/GRIDKIT_SKILL.md" target="_blank" rel="noopener">Agent Skill</a>
        </div>
    </div>
</footer>

<!-- Hidden skill content for copy -->
<textarea id="skill-content" style="position:absolute;left:-9999px" aria-hidden="true"><?= htmlspecialchars($skillContent) ?></textarea>

<script>
// Demo scenarios
const demos = {
    table: [
        { type: 'prompt', text: 'user> Build me a product management table with search and edit modal' },
        { type: 'blank' },
        { type: 'comment', text: '// Agent reads GRIDKIT_SKILL.md and generates:' },
        { type: 'blank' },
        { type: 'highlight', text: '$table = new Table(\'products\');' },
        { type: 'output', text: '$table->query($db, "SELECT * FROM products")' },
        { type: 'output', text: '    ->search([\'name\', \'sku\'])' },
        { type: 'output', text: '    ->column(\'name\', \'Product\', [\'sortable\' => true])' },
        { type: 'output', text: '    ->column(\'price\', \'Price\', [\'format\' => \'currency\'])' },
        { type: 'output', text: '    ->column(\'status\', \'Status\', [\'format\' => \'label\'])' },
        { type: 'output', text: '    ->button(\'edit\', [\'icon\' => \'edit\', \'modal\' => \'edit_product\'])' },
        { type: 'output', text: '    ->modal(\'edit_product\', \'Edit\', \'forms/product.php\')' },
        { type: 'output', text: '    ->newButton(\'New Product\', [\'modal\' => \'edit_product\'])' },
        { type: 'output', text: '    ->paginate(25)' },
        { type: 'output', text: '    ->render();' },
        { type: 'blank' },
        { type: 'success', text: '// ✓ Table with search, sort, pagination, edit modal — done.' },
    ],
    form: [
        { type: 'prompt', text: 'user> Create a customer registration form with validation' },
        { type: 'blank' },
        { type: 'comment', text: '// Agent generates a 16-column grid form:' },
        { type: 'blank' },
        { type: 'highlight', text: '$form = new Form(\'customer\');' },
        { type: 'output', text: '$form->action(\'api/save_customer.php\')' },
        { type: 'output', text: '    ->row()' },
        { type: 'output', text: '        ->field(\'first_name\', \'First Name\', \'text\', [\'width\' => 8, \'required\' => true])' },
        { type: 'output', text: '        ->field(\'last_name\', \'Last Name\', \'text\', [\'width\' => 8, \'required\' => true])' },
        { type: 'output', text: '    ->endRow()' },
        { type: 'output', text: '    ->field(\'email\', \'Email\', \'email\', [\'width\' => 8])' },
        { type: 'output', text: '    ->field(\'phone\', \'Phone\', \'tel\', [\'width\' => 8])' },
        { type: 'output', text: '    ->field(\'notes\', \'Notes\', \'richtext\', [\'width\' => 16])' },
        { type: 'output', text: '    ->submit(\'Register Customer\')' },
        { type: 'output', text: '    ->render();' },
        { type: 'blank' },
        { type: 'success', text: '// ✓ Responsive form with AJAX submit and validation — done.' },
    ],
    dashboard: [
        { type: 'prompt', text: 'user> Build a dashboard with KPI cards and recent orders table' },
        { type: 'blank' },
        { type: 'comment', text: '// Agent combines StatCards + Table:' },
        { type: 'blank' },
        { type: 'highlight', text: '$cards = new StatCards();' },
        { type: 'output', text: '$cards->card(\'Revenue\', \'€12,450\', [\'icon\' => \'payments\', \'trend\' => \'+12%\'])' },
        { type: 'output', text: '    ->card(\'Orders\', \'384\', [\'icon\' => \'shopping_cart\', \'color\' => \'success\'])' },
        { type: 'output', text: '    ->card(\'Users\', \'1,205\', [\'icon\' => \'people\'])' },
        { type: 'output', text: '    ->render();' },
        { type: 'blank' },
        { type: 'highlight', text: '$table = new Table(\'recent_orders\');' },
        { type: 'output', text: '$table->query($db, "SELECT * FROM orders ORDER BY date DESC LIMIT 10")' },
        { type: 'output', text: '    ->column(\'order_no\', \'#\')' },
        { type: 'output', text: '    ->column(\'customer\', \'Customer\')' },
        { type: 'output', text: '    ->column(\'total\', \'Total\', [\'format\' => \'currency\'])' },
        { type: 'output', text: '    ->column(\'date\', \'Date\', [\'format\' => \'datetime\'])' },
        { type: 'output', text: '    ->render();' },
        { type: 'blank' },
        { type: 'success', text: '// ✓ KPI dashboard with live data table — done.' },
    ],
    auth: [
        { type: 'prompt', text: 'user> Add login page with authentication' },
        { type: 'blank' },
        { type: 'comment', text: '// Agent sets up Auth component:' },
        { type: 'blank' },
        { type: 'highlight', text: 'use GridKit\\Auth;' },
        { type: 'blank' },
        { type: 'output', text: 'Auth::setUsersFile(__DIR__ . \'/users.conf\');' },
        { type: 'blank' },
        { type: 'output', text: 'if ($_SERVER[\'REQUEST_METHOD\'] === \'POST\') {' },
        { type: 'output', text: '    if (Auth::login($_POST[\'username\'], $_POST[\'password\'])) {' },
        { type: 'output', text: '        header(\'Location: /dashboard\');' },
        { type: 'output', text: '        exit;' },
        { type: 'output', text: '    }' },
        { type: 'output', text: '}' },
        { type: 'blank' },
        { type: 'output', text: 'if (!Auth::check()) {' },
        { type: 'output', text: '    Auth::renderLogin([\'title\' => \'My App\']);' },
        { type: 'output', text: '    exit;' },
        { type: 'output', text: '}' },
        { type: 'blank' },
        { type: 'success', text: '// ✓ Session auth with bcrypt, remember-me, styled login page — done.' },
    ],
};

let currentDemo = null;
let demoTimeout = null;

function runDemo(name) {
    if (demoTimeout) clearTimeout(demoTimeout);

    // Update buttons
    document.querySelectorAll('.demo-btn').forEach(b => b.classList.remove('active'));
    event.target.classList.add('active');

    const output = document.getElementById('demo-output');
    output.innerHTML = '';
    currentDemo = name;

    const lines = demos[name];
    let i = 0;

    function addLine() {
        if (i >= lines.length || currentDemo !== name) return;
        const line = lines[i];
        const div = document.createElement('div');
        div.className = 'line';

        switch (line.type) {
            case 'prompt':
                const parts = line.text.split('> ');
                div.innerHTML = `<span class="prompt">${parts[0]}></span> <span class="cmd">${parts[1]}</span>`;
                break;
            case 'comment':
                div.innerHTML = `<span class="comment">${line.text}</span>`;
                break;
            case 'output':
                div.innerHTML = `<span class="output">${escapeHtml(line.text)}</span>`;
                break;
            case 'highlight':
                div.innerHTML = `<span class="highlight">${escapeHtml(line.text)}</span>`;
                break;
            case 'success':
                div.innerHTML = `<span class="success">${line.text}</span>`;
                break;
            case 'blank':
                div.innerHTML = '&nbsp;';
                break;
        }

        output.appendChild(div);
        output.scrollTop = output.scrollHeight;
        i++;
        demoTimeout = setTimeout(addLine, line.type === 'blank' ? 100 : 60);
    }

    addLine();
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function toggleSkill() {
    const preview = document.getElementById('skill-preview');
    const btn = document.getElementById('skill-toggle');
    preview.classList.toggle('collapsed');
    btn.textContent = preview.classList.contains('collapsed') ? 'Show full document' : 'Collapse';
}

function copySkill() {
    const content = document.getElementById('skill-content').value;
    navigator.clipboard.writeText(content).then(() => {
        const btn = document.getElementById('copy-btn');
        btn.innerHTML = '<span class="material-icons" style="font-size:16px">check</span> Copied!';
        setTimeout(() => {
            btn.innerHTML = '<span class="material-icons" style="font-size:16px">content_copy</span> Copy Skill';
        }, 2000);
    });
}

function copyCode(btn) {
    const code = btn.closest('.code-block').querySelector('pre').textContent;
    navigator.clipboard.writeText(code).then(() => {
        btn.textContent = 'Copied!';
        setTimeout(() => { btn.textContent = 'Copy'; }, 2000);
    });
}

// Auto-start first demo on scroll into view
const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting && !currentDemo) {
            runDemo('table');
            observer.disconnect();
        }
    });
}, { threshold: 0.3 });

const agentSection = document.getElementById('agent');
if (agentSection) observer.observe(agentSection);

// Smooth nav background on scroll
window.addEventListener('scroll', () => {
    const nav = document.querySelector('.nav');
    if (window.scrollY > 20) {
        nav.style.boxShadow = '0 1px 8px rgba(0,0,0,0.08)';
    } else {
        nav.style.boxShadow = 'none';
    }
});
</script>

</body>
</html>
