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
<!-- ===== SUCHE ===== -->
<div class="demo-section" data-section="suche">
    <h2>Search <?= since('global-search') ?></h2>
    <div class="demo-pair">
    <div class="demo-card">
        <p class="demo-intro">System-wide quick search. GridKit provides the widget only —
           WHAT is searched is decided by your backend endpoint. Opens with
           <kbd>Ctrl</kbd>+<kbd>K</kbd> (<kbd>Cmd</kbd>+<kbd>K</kbd> on Mac) or any element
           carrying <code>data-gk-search</code>. Arrow keys select, Enter opens, Escape closes.</p>
        <div class="demo-btn-row">
            <button class="gk-btn gk-btn-primary" data-gk-search><span class="material-icons" style="font-size:16px" aria-hidden="true">search</span> Open search</button>
        </div>
    </div>
    <div class="demo-code"><pre>GK.search.init({
  url: '/demo/api/quicksearch.php',
  hotkey: 'ctrl+k',
  minLength: 0
});

// Server response. The German key names — gruppen / titel / treffer /
// untertitel / betrag — are still read as a fallback, but this is the shape:
{ "groups": [
    { "title": "Transactions",
      "items": [
        { "title": "Hetzner",
          "subtitle": "Jul 7, 2026",
          "amount": "&euro;64.80",
          "url": "/transactions/2",
          "icon": "account_balance" } ] } ] }</pre></div>
    </div>
</div>

