<?php
use GridKit\Table;
use GridKit\Form;
use GridKit\Button;
use GridKit\Tooltip;
?>
<!-- ===== TOOLTIP ===== -->
<div class="demo-section" data-section="tooltip">
    <h2>Tooltip <?= since('tooltip') ?></h2>
    <p class="demo-intro">One engine for every tooltip: it places the bubble on the side you ask for, flips it when there is no room, shifts it along the edge to stay inside the window (and inside a box that scrolls) and keeps the arrow on its trigger. Hover opens it after a short delay, keyboard focus at once, a tap on touch; Escape, blur, scrolling away and the pointer leaving close it. A screen reader hears the text as the trigger's description. Without the script the CSS-only tooltip is what it always was.</p>

    <h3 class="demo-h3">Placement: flip, shift, auto <?= since('tooltip') ?></h3>
    <p class="demo-intro">Each trigger in the box below asks for a side it has no room on, at the edge of a box that scrolls: the bubble flips to the other side or slides along the edge, and the arrow still points at the word. Scroll the box — the bubble follows, and closes when its trigger leaves the visible part. <code>auto</code> takes above or below, whichever has more room.</p>
        <?php
        showcase(function () {
            $spot = static fn (string $where, string $label, string $text, string $pos): string =>
                '<span class="demo-tt-spot" style="' . $where . '"' . Tooltip::attrs($text, ['pos' => $pos]) . '>' . $label . '</span>';
            echo '<div class="demo-tt-box"><div class="demo-tt-inner">'
               . $spot('top:6px;left:40%', 'top → flips', 'Asked for top — no room, so below', 'top')
               . $spot('top:64px;left:6px', 'left → flips', 'Asked for left — no room, so right', 'left')
               . $spot('top:64px;right:6px', 'right → flips', 'Asked for right, at the right edge — so left', 'right')
               . $spot('top:112px;right:6px', 'top, shifted', 'A long text at the right edge: shifted left, the arrow stays on the word', 'top')
               . $spot('top:160px;left:40%', 'auto', 'auto: above or below, whichever has more room', 'auto')
               . $spot('top:380px;left:30%', 'further down', 'Scroll the box: this one follows its trigger', 'bottom')
               . '</div></div>';
        });
        ?>

    <h3 class="demo-h3">Tones, keyboard hint, width, delay <?= since('tooltip') ?></h3>
    <p class="demo-intro">Six tones, each at 4.5:1 or more in light and dark mode, under every theme. <code>kbd</code> adds a key after the text, <code>max</code> narrows the bubble, <code>delay</code> sets the wait on hover (0 opens at once).</p>
        <?php
        showcase(function () {
            echo '<div class="demo-tt-row">';
            foreach (['neutral', 'info', 'success', 'warning', 'danger', 'light'] as $tone) {
                echo '<span class="demo-tt-chip"' . Tooltip::attrs(ucfirst($tone) . ' tone', ['tone' => $tone]) . '>' . $tone . '</span>';
            }
            echo '</div><div class="demo-tt-row">';
            echo Button::render('Save', ['icon' => 'save', 'tooltip' => ['text' => 'Saves the draft', 'kbd' => 'Ctrl+S']]);
            echo '<span class="demo-tt-chip"' . Tooltip::attrs('A long explanation that would run wide is kept to 160 pixels and wraps', ['max' => 160]) . '>max 160</span>';
            echo '<span class="demo-tt-chip"' . Tooltip::attrs('Opens at once', ['delay' => 0]) . '>delay 0</span>';
            echo '<span class="demo-tt-chip"' . Tooltip::attrs('Took its time', ['delay' => 1000]) . '>delay 1000</span>';
            echo '</div>';
        });
        ?>

    <h3 class="demo-h3">Toggletip and help icon <?= since('tooltip') ?></h3>
    <p class="demo-intro">A toggletip opens on a click, Enter or Space — not on hover — and stays open, so it can hold a link or a button. Its text is read out; the focus stays on the trigger, and Tab walks into its links. Escape, a click outside or a second click close it. <code>Tooltip::help()</code> is the ready-made "?" (24px, 44px to a finger), named after what it explains.</p>
        <?php
        showcase(function () {
            echo '<div class="demo-tt-row">';
            echo '<span>VAT ' . Tooltip::help('Reduced rate of 10 % for food and books.', ['label' => 'VAT']) . '</span>';
            echo '<span>IBAN ' . Tooltip::help('#demo-iban-help', ['label' => 'IBAN', 'icon' => 'info']) . '</span>';
            echo '<button type="button" class="gk-btn gk-btn-outlined gk-btn-neutral"'
               . Tooltip::toggletip('Counted from the invoice date, not from delivery.', ['tone' => 'info', 'pos' => 'bottom']) . '>Why 14 days?</button>';
            echo '</div>';
            echo '<div id="demo-iban-help" hidden>On your bank card and statement. <a href="#tooltip">Where to find it</a></div>';
        });
        ?>

    <h3 class="demo-h3">On components: Button, Table, Form <?= since('tooltip') ?></h3>
    <p class="demo-intro"><code>'tooltip'</code> on a button and on a table's row button replaces the browser's <code>title</code> — never both, and a description, not the name. On a table column it is a help icon beside the label (the sort button still sorts); <code>'help'</code> on a form field puts one beside the label. All of it survives a sort of a <code>setData()</code> table.</p>
        <?php
        showcase(function () {
            echo '<p>' . Button::icon('delete', ['color' => 'danger', 'tooltip' => ['text' => 'Deletes the invoice for good', 'tone' => 'danger']]) . '</p>';
            (new Table('demo-tooltip-table'))
                ->setData([
                    ['id' => 1, 'customer' => 'Mustermann GmbH', 'net' => 1450.00],
                    ['id' => 2, 'customer' => 'Weber & Partner', 'net' => 890.50],
                ])
                ->column('customer', 'Customer', ['sortable' => true, 'tooltip' => 'As on the contract'])
                ->column('net', 'Net', ['format' => 'currency', 'align' => 'right', 'sortable' => true,
                                        'tooltip' => ['text' => 'Before VAT', 'tone' => 'info']])
                ->button('edit', ['icon' => 'edit', 'tooltip' => 'Edit this customer'])
                ->caption('Customers with tooltips')
                ->render();
            (new Form('demo-tooltip-form'))
                ->field('iban', 'IBAN', 'text', ['help' => 'On your bank card.'])
                ->field('terms', 'I accept the terms', 'checkbox', ['help' => 'They fit on one page.'])
                ->render();
        });
        ?>

    <h3 class="demo-h3">Title, plain, rich</h3>
    <p class="demo-intro">Any <code>title</code> gets the same bubble (GK.tip; opt out with <code>data-gk-tip-off</code>). <code>data-gk-tooltip</code> is the plain form by hand; <code>data-gk-tooltip-rich</code> shows an element with HTML in it, and the pointer can move into it.</p>
        <?php
        showcase(function () {
            echo <<<'HTML'
            <div class="demo-tt-row">
              <span class="demo-tt-chip" title="Upgraded automatically — just use title">title</span>
              <span class="demo-tt-chip" title="First line&#10;Second line (\n breaks)">title, two lines</span>
              <span data-gk-tip-off><span class="demo-tt-chip" title="This one is the browser's own">opt-out</span></span>
              <span class="demo-tt-chip" data-gk-tooltip="Plain, by hand" data-gk-tooltip-pos="bottom">plain</span>
              <span class="demo-tt-chip" data-gk-tooltip="Several lines when the text is long enough to need them" data-gk-tooltip-wrap>wrap</span>
              <span class="demo-tt-chip" data-gk-tooltip-rich="#richTip1">rich</span>
            </div>
            <div id="richTip1"><strong>GridKit Tooltip</strong>
              <p style="margin:6px 0 4px">Real HTML: formatting, and <a href="#tooltip">a link</a> the pointer can reach.</p>
            </div>
            HTML;
        });
        ?>
</div>
<style>
.demo-tt-box { position:relative; height:220px; overflow:auto; border:1px solid var(--gk-outline-variant); border-radius:8px; background:var(--gk-surface-container-low); }
.demo-tt-inner { position:relative; height:520px; }
.demo-tt-spot { position:absolute; padding:4px 8px; border-radius:6px; background:var(--gk-surface); border:1px solid var(--gk-outline-variant); font-size:13px; white-space:nowrap; }
.demo-tt-row { display:flex; flex-wrap:wrap; gap:12px; align-items:center; margin-bottom:12px; }
.demo-tt-chip { padding:6px 12px; border-radius:6px; background:var(--gk-surface-container); font-size:13px; }
</style>
