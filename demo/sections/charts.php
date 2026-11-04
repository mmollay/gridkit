<?php
use GridKit\Button;
use GridKit\Chart;
use GridKit\StatCards;
use GridKit\Table;
?>
<!-- ===== CHARTS (1.121.0) ===== -->
<div class="demo-section" data-section="charts">
    <h2>Charts <?= since('chart') ?></h2>
    <p class="demo-intro">Revenue per month, open rates, the sources of a site's visits: <code>GridKit\Chart</code> draws
        line, area, bar and donut charts and sparklines as inline SVG on the server — no library, nothing to wait for.
        The axis steps by 1, 2 and 5, bars start at zero, numbers are written in the chart's language
        (<code>1.234,50 €</code>, <code>€1,234.50</code>). Every chart has a summary in words for a screen reader, a
        legend that names its series and its data as a table. <code>js/chart.js</code> draws it again at the width of its
        box, shows the values on hover and on the keys (<kbd>Tab</kbd> into a chart, then <kbd>←</kbd> <kbd>→</kbd>,
        <kbd>Home</kbd>, <kbd>End</kbd>, <kbd>Enter</kbd>), and switches a series off from its legend.</p>

    <h3 class="demo-h3" id="charts-bar">Bars: two years side by side</h3>
    <div class="demo-card">
        <?php
        showcase(function () {
            (new Chart('demo-revenue'))
                ->type('bar')
                ->title('Revenue per month')
                ->labels(['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'])
                ->series('2025', [8200, 7900, 9400, 10100, 9800, 11200, 12500, 11900, 10400, 12800, 14100, 16900])
                ->series('2026', [9100, 8800, 10900, 11600, 12100, 12900, 13800, 13100, 12200, null, null, null])
                ->series('Plan', [9000, 9000, 9500, 10000, 10500, 11000, 11500, 12000, 12500, 13000, 13500, 14000],
                    ['hidden' => true])   // starts switched off; the legend switches it on
                ->format('money', ['currency' => 'EUR', 'locale' => 'de'])
                ->height(280)
                ->render();
        }, '', true);
        ?>
        <div class="demo-btn-row" style="margin-top:12px">
            <?= Button::render('New figures for 2026', ['variant' => 'outlined', 'size' => 'sm', 'icon' => 'refresh', 'id' => 'demo-chart-update']) ?>
            <?= Button::render('Switch 2025 off and on', ['variant' => 'outlined', 'size' => 'sm', 'icon' => 'visibility', 'id' => 'demo-chart-toggle']) ?>
        </div>
        <p class="demo-intro" style="margin:12px 0 6px">What the page hears — <code>gk:chartselect</code> (click a bar, or
            <kbd>Enter</kbd> on the keys) and <code>gk:charttoggle</code> (the legend):</p>
        <pre class="demo-log" id="demo-chart-log">Click a bar or a name in the legend.</pre>
    </div>

    <h3 class="demo-h3" id="charts-line">Lines: open rates, with dashes and point shapes</h3>
    <div class="demo-card">
        <?php
        showcase(function () {
            (new Chart('demo-open-rates'))
                ->type('line')
                ->title('Newsletter open rate')
                ->labels(['W1', 'W2', 'W3', 'W4', 'W5', 'W6', 'W7', 'W8'])
                ->series('Customers', [41.2, 43.5, 39.8, 44.1, 46.3, 45.0, 47.2, 48.9])
                ->series('Trial users', [28.4, 30.1, 33.6, 31.2, 35.8, 34.4, 37.1, 36.5])
                ->series('Partners', [52.0, 49.5, 51.3, null, 54.2, 55.8, 53.9, 57.4])
                ->format('percent', ['locale' => 'en'])
                ->zero(false)             // the axis starts near the data
                ->markers()               // a point shape per series
                ->dashes()                // a dash pattern per series
                ->render();
        }, '', true);
        ?>
    </div>

    <h3 class="demo-h3" id="charts-area">Stacked areas: visits by channel</h3>
    <div class="demo-card">
        <?php
        showcase(function () {
            (new Chart('demo-visits'))
                ->type('area')
                ->stacked()
                ->title('Visits by channel')
                ->labels(['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'])
                ->series('Search', [1820, 1940, 2010, 1890, 1760, 980, 860])
                ->series('Direct', [640, 700, 690, 720, 680, 410, 380])
                ->series('Social', [310, 290, 350, 420, 510, 620, 580])
                ->format('number', ['locale' => 'en'])
                ->height(240)
                ->render();
        }, '', true);
        ?>
    </div>

    <h3 class="demo-h3" id="charts-horizontal">Horizontal bars: long names, negative values</h3>
    <div class="demo-card">
        <?php
        showcase(function () {
            (new Chart('demo-pages'))
                ->type('bar')
                ->horizontal()
                ->title('Change in visits since last month')
                ->labels(['/pricing', '/blog/how-we-cut-our-invoice-processing-time-in-half', '/features/reports',
                          '/contact', '/docs/getting-started'])
                ->series('Change', [18.5, 42.0, -7.5, -12.0, 9.0])
                ->format('percent', ['locale' => 'en'])
                ->height(240)
                ->render();
        }, '', true);
        ?>
    </div>

    <h3 class="demo-h3" id="charts-donut">Donut: parts of a whole</h3>
    <div class="demo-card">
        <?php
        showcase(function () {
            (new Chart('demo-sources'))
                ->type('donut')
                ->title('Where the orders came from')
                ->labels(['Online shop', 'Marketplace', 'Phone', 'Retail partners', 'Trade fairs', 'E-mail', 'Other'])
                ->series('Orders', [1240, 860, 410, 380, 150, 60, 25])   // from the sixth part on: "Other"
                ->format('number', ['locale' => 'de'])
                ->height(220)
                ->render();
        }, '', true);
        ?>
    </div>

    <h3 class="demo-h3" id="charts-spark">Sparklines: in a card and in a table cell</h3>
    <div class="demo-card">
        <?php
        showcase(function () {
            (new StatCards('demo-chart-stats'))
                ->card('Revenue', 16900, ['format' => 'currency', 'icon' => 'euro', 'trend' => '+12%',
                    'sparkline' => [8200, 7900, 9400, 10100, 9800, 11200, 12500, 11900, 10400, 12800, 14100, 16900]])
                ->card('Open rate', 48.9, ['format' => 'percent', 'icon' => 'mail',
                    'sparkline' => ['values' => [41.2, 43.5, 39.8, 44.1, 46.3, 45.0, 47.2, 48.9], 'color' => 3, 'area' => true]])
                ->render();

            (new Table('demo-chart-table'))
                ->setData([
                    ['product' => 'Starter', 'sales' => 412, 'trend' => Chart::sparkline([30, 32, 35, 33, 38, 41, 44], ['label' => 'Starter'])],
                    ['product' => 'Business', 'sales' => 268, 'trend' => Chart::sparkline([22, 21, 25, 24, 23, 27, 26], ['label' => 'Business', 'color' => 2])],
                    ['product' => 'Enterprise', 'sales' => 57, 'trend' => Chart::sparkline([9, 8, 8, 7, 6, 7, 5], ['label' => 'Enterprise', 'color' => 4])],
                ])
                ->column('product', 'Product')
                ->column('sales', 'Sales', ['format' => 'number'])
                ->column('trend', 'Last 7 weeks', ['format' => 'html'])
                ->render();
        }, '', true);
        ?>
    </div>

    <h3 class="demo-h3" id="charts-edge">When the data is thin</h3>
    <p class="demo-intro">No values at all, a single point, nothing but zeros (bars and a donut), and values below zero:
        each still draws a chart that says what it holds, and never an empty frame.</p>
    <div class="demo-chart-grid">
        <div class="demo-card">
            <?php
            showcase(function () {
                (new Chart('demo-empty'))->type('line')->title('Sign-ups')
                    ->labels(['Jan', 'Feb', 'Mar'])->series('Sign-ups', [null, null, null])
                    ->height(160)->render();
            });
            ?>
        </div>
        <div class="demo-card">
            <?php
            showcase(function () {
                (new Chart('demo-one'))->type('line')->title('First month')
                    ->labels(['Nov'])->series('Orders', [37])
                    ->height(160)->render();
            });
            ?>
        </div>
        <div class="demo-card">
            <?php
            showcase(function () {
                (new Chart('demo-zero'))->type('bar')->title('Refunds')
                    ->labels(['Q1', 'Q2', 'Q3', 'Q4'])->series('Refunds', [0, 0, 0, 0])
                    ->format('money', ['locale' => 'en', 'currency' => 'USD'])
                    ->height(160)->render();
            });
            ?>
        </div>
        <div class="demo-card">
            <?php
            showcase(function () {
                (new Chart('demo-nothing'))->type('donut')->title('Returns by reason')
                    ->labels(['Damaged', 'Wrong size', 'Changed mind'])->series('Returns', [0, 0, 0])
                    ->height(140)->render();
            });
            ?>
        </div>
        <div class="demo-card">
            <?php
            showcase(function () {
                (new Chart('demo-negative'))->type('bar')->stacked()->title('Cash flow')
                    ->labels(['Q1', 'Q2', 'Q3', 'Q4'])
                    ->series('In', [5200, 6100, 4800, 7300])
                    ->series('Out', [-4100, -6900, -3900, -5200])
                    ->format('money', ['locale' => 'de'])
                    ->height(160)->render();
            });
            ?>
        </div>
    </div>

    <script>
    (function () {
        var log = document.getElementById('demo-chart-log');
        function say(text) { log.textContent = text + '\n' + log.textContent.split('\n').slice(0, 4).join('\n'); }
        document.addEventListener('gk:chartselect', function (e) {
            if (e.detail.chart !== 'demo-revenue') return;
            say('gk:chartselect ' + e.detail.label + ' — ' + e.detail.values.map(function (v) { return v.name + ' ' + v.text; }).join(', '));
        });
        document.addEventListener('gk:charttoggle', function (e) {
            if (e.detail.chart !== 'demo-revenue') return;
            say('gk:charttoggle ' + e.detail.name + (e.detail.visible ? ' shown' : ' hidden'));
        });
        var turn = 0;
        document.getElementById('demo-chart-update').addEventListener('click', function () {
            if (!window.GK || !GK.chart) return;
            var base = [9100, 8800, 10900, 11600, 12100, 12900, 13800, 13100, 12200, 13600, 15200, 18100];
            turn++;
            var data = GK.chart.data('demo-revenue');
            data.series[1].values = base.map(function (v, i) { return Math.round(v * (0.9 + ((i * 7 + turn * 13) % 10) / 25)); });
            GK.chart.update('demo-revenue', { series: data.series });
        });
        document.getElementById('demo-chart-toggle').addEventListener('click', function () {
            if (window.GK && GK.chart) GK.chart.toggle('demo-revenue', 0);
        });
    })();
    </script>
</div>
