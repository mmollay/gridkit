<?php
use GridKit\Form;
use GridKit\LineItems;
?>
<!-- ===== LINE ITEMS (1.117.0) ===== -->
<div class="demo-section" data-section="lineitems">
    <h2>Line items <?= since('line-items') ?></h2>
    <p class="demo-intro">The lines of an invoice, an offer or an order: <code>GridKit\LineItems</code>. Every field posts
        <code>name[i][field]</code> in the order on the screen; the totals — subtotal, discount, shipping, one VAT line
        per rate, the total — follow every keystroke, and <code>LineItems::totals()</code> computes the very same figures
        in PHP, to the cent: integers all the way, no float. Lines are added (the button, or Enter in the last field),
        duplicated, deleted with Undo, and put in order by the handle — mouse, finger or Space and the arrow keys.
        Narrower than 56rem, every line is a card.</p>

    <div class="demo-card">
        <h3 style="margin:0 0 4px; font-size:15px; color:var(--gk-on-surface, #374151);">An Austrian invoice</h3>
        <p class="demo-intro">VAT 20 % and 10 %, a discount on one line, 3 % off everything, shipping, euros with a
            decimal comma. Type <code>1234,5</code>, <code>1.234,50</code> or <code>1 234,50</code> — it is read the same,
            and written back as the page writes it.</p>
        <?php
        showcase(function () {
            (new LineItems('invoice'))
                ->invoiceColumns()          // description, qty, unit, unit price, discount %, VAT, amount
                ->locale('de')              // numbers with a decimal comma
                ->currency('EUR')
                ->vatRates([20, 10, 13, 0])
                ->discount(3)               // 3 % off everything, with its own field
                ->shipping('4.90')          // shipping, under the highest rate of the lines
                ->limits(1, 30)
                ->items([
                    ['id' => 41, 'description' => "Website relaunch\nDesign, templates, three rounds of changes",
                     'qty' => 1, 'unit' => 'flat', 'price' => '2400', 'discount' => 5, 'vat' => 20],
                    ['id' => 42, 'description' => 'Web hosting, annual', 'qty' => 12, 'unit' => 'month', 'price' => 19.9, 'vat' => 20],
                    ['id' => 43, 'description' => 'Handbook (print)', 'qty' => 3, 'unit' => 'pcs', 'price' => '34.50', 'vat' => 10],
                ])
                ->render();
        });
        ?>
        <p class="demo-intro" style="margin:16px 0 6px">What the page hears — <code>gk:lineitemschange</code>, its
            <code>detail.totals</code>:</p>
        <pre class="demo-log" id="demo-lines-log">Change a figure above.</pre>
        <script>
        document.addEventListener('gk:lineitemschange', function (e) {
            if (e.target.id !== 'gk-lines-invoice') return;
            var t = e.detail.totals;
            document.getElementById('demo-lines-log').textContent = JSON.stringify({
                subtotal: t.subtotal, discount: t.discount, shipping: t.shipping, net: t.net,
                vat: t.vat, gross: t.gross, lines: e.detail.items.length
            }, null, 1);
        });
        </script>
    </div>

    <div class="demo-card">
        <h3 style="margin:0 0 4px; font-size:15px; color:var(--gk-on-surface, #374151);">The same invoice on the server</h3>
        <p class="demo-intro">What the editor above posts as it was first drawn, read back with <code>parse()</code>: the
            lines in machine form, the messages keyed like the POST, and the totals — computed here, never taken from the
            browser. They are the figures under the lines above.</p>
        <?php
        showcase(function () {
            $lines = (new LineItems('invoice'))->invoiceColumns()->locale('de')->vatRates([20, 10, 13, 0])
                ->discount(0)->shipping(0);
            $in = $lines->parse([
                'invoice' => [
                    ['id' => '41', 'description' => "Website relaunch\nDesign, templates, three rounds of changes",
                     'qty' => '1', 'unit' => 'flat', 'price' => '2.400,00', 'discount' => '5', 'vat' => '20'],
                    ['id' => '42', 'description' => 'Web hosting, annual', 'qty' => '12', 'unit' => 'month',
                     'price' => '19,90', 'discount' => '', 'vat' => '20'],
                    ['id' => '43', 'description' => 'Handbook (print)', 'qty' => '3', 'unit' => 'pcs',
                     'price' => '34,50', 'discount' => '', 'vat' => '10'],
                ],
                'invoice_discount' => '3',
                'invoice_shipping' => '4,90',
            ]);
            $t = $in['totals'];
            echo '<pre class="demo-log">' . htmlspecialchars(json_encode([
                'net' => $t['net'], 'vat' => $t['vat'], 'gross' => $t['gross'], 'errors' => $in['errors'],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . '</pre>';
        });
        ?>
    </div>

    <div class="demo-card">
        <h3 style="margin:0 0 4px; font-size:15px; color:var(--gk-on-surface, #374151);">Sent: read-only</h3>
        <p class="demo-intro">The same invoice once it has gone out: the same columns and grid, the figures as text — no
            field, no button, no handle, nothing that posts.</p>
        <?php
        showcase(function () {
            (new LineItems('sent'))->invoiceColumns()->locale('de')->vatRates([20, 10])->discount(3)->shipping('4.90')
                ->items([
                    ['description' => "Website relaunch\nDesign, templates, three rounds of changes",
                     'qty' => 1, 'unit' => 'flat', 'price' => '2400', 'discount' => 5, 'vat' => 20],
                    ['description' => 'Web hosting, annual', 'qty' => 12, 'unit' => 'month', 'price' => 19.9, 'vat' => 20],
                    ['description' => 'Handbook (print)', 'qty' => 3, 'unit' => 'pcs', 'price' => '34.50', 'vat' => 10],
                ])
                ->readonly()
                ->render();
        });
        ?>
    </div>

    <div class="demo-card">
        <h3 style="margin:0 0 4px; font-size:15px; color:var(--gk-on-surface, #374151);">An article search, in a Form</h3>
        <p class="demo-intro">A column of type <code>lookup</code> asks <code>api/articles.php</code> as you type (try
            "hand" or "hosting"); picking an article fills description, unit, price and VAT. The editor is a field of a
            <code>Form</code> here — <code>'lineitems'</code>, named by its label; an AJAX answer
            <code>{"errors": {"order[1][qty]": "…"}}</code> lands under that very cell. Gross prices this time.</p>
        <?php
        showcase(function () {
            $lines = (new LineItems('order'))
                ->column('article', 'Article', ['type' => 'lookup', 'url' => 'api/articles.php', 'minChars' => 1,
                    'subtextField' => 'number', 'display' => 'article_name',
                    'fill' => ['description' => 'name', 'unit' => 'unit', 'price' => 'price', 'vat' => 'vat']])
                ->column('description', 'Description', ['type' => 'text', 'required' => true])
                ->column('qty', 'Qty', ['type' => 'number', 'required' => true, 'default' => '1'])
                ->column('unit', 'Unit', ['type' => 'select'])
                ->column('price', '', ['type' => 'money', 'required' => true])
                ->column('vat', 'VAT', ['type' => 'select'])
                ->column('total', 'Amount', ['type' => 'computed'])
                ->pricesNet(false)
                ->vatRates([20, 10])
                ->limits(1);
            (new Form('order_form'))
                ->field('customer', 'Customer', 'text', ['width' => 8])
                ->field('order', 'Order lines', 'lineitems', ['lines' => $lines])
                ->render();
        });
        ?>
    </div>
</div>
