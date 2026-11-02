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
<!-- ===== LAYOUT (merged: segment + message) ===== -->
<div class="demo-section" data-section="layout">
    <h2>Layout</h2>

    <h3 style="margin: 32px 0 16px;">Segment <?= since('segment') ?></h3>
    <p class="demo-intro">Container for related content — single, stacked or with header.</p>

    <div class="gk-segment"><p>A simple segment visually groups related content.</p></div>

    <h3 style="margin: 32px 0 16px;">Segment with header</h3>
    <div class="gk-segment"><div class="gk-segment-header">Project overview</div><p>Segments can have a header to describe the content.</p></div>

    <h3 style="margin: 32px 0 16px;">Stacked segments</h3>
    <div class="gk-segments">
        <div class="gk-segment"><div class="gk-segment-header">Step 1</div><p>Create account and confirm email.</p></div>
        <div class="gk-segment"><div class="gk-segment-header">Step 2</div><p>Fill out profile and configure settings.</p></div>
        <div class="gk-segment"><div class="gk-segment-header">Step 3</div><p>Create first project and get started.</p></div>
    </div>

    <h3 style="margin: 32px 0 16px;">Variants</h3>
    <div class="gk-segment gk-segment-raised"><div class="gk-segment-header">Raised</div><p>With shadow — stands out more from the background.</p></div>
    <div class="gk-segment gk-segment-muted"><div class="gk-segment-header">Muted</div><p>Muted background — for secondary content.</p></div>
    <div class="gk-segment gk-segment-compact"><div class="gk-segment-header">Compact</div><p>Less padding — for denser layouts.</p></div>

    <div class="demo-code"><pre>.gk-segment / .gk-segment-raised / .gk-segment-muted / .gk-segment-compact
.gk-segment-padded / .gk-segment-basic
.gk-segments > .gk-segment  // Stacked</pre></div>

    <hr style="border:none;border-top:1px solid var(--gk-outline-variant);margin:40px 0">

    <h3>Message <?= since('message') ?></h3>
    <p class="demo-intro">Notices, warnings and status messages for users.</p>

    <div class="gk-message"><span class="material-icons" aria-hidden="true">info</span><div class="gk-message-content">A neutral message without a specific status.</div></div>

    <h3 style="margin: 32px 0 16px;">Types</h3>
    <div class="gk-message gk-message-info"><span class="material-icons" aria-hidden="true">info</span><div class="gk-message-content"><div class="gk-message-header">Information</div>Your changes are saved automatically.</div></div>
    <div class="gk-message gk-message-success"><span class="material-icons" aria-hidden="true">check_circle</span><div class="gk-message-content"><div class="gk-message-header">Success</div>The profile has been successfully updated.</div></div>
    <div class="gk-message gk-message-warning"><span class="material-icons" aria-hidden="true">warning</span><div class="gk-message-content"><div class="gk-message-header">Warning</div>Your SSL certificate expires in 7 days.</div></div>
    <div class="gk-message gk-message-error"><span class="material-icons" aria-hidden="true">error</span><div class="gk-message-content"><div class="gk-message-header">Error</div>Could not establish connection to the database.<ul class="gk-message-list"><li>Host unreachable</li><li>Timeout after 30 seconds</li></ul></div></div>

    <h3 style="margin: 32px 0 16px;">Compact</h3>
    <div class="gk-message gk-message-info gk-message-compact"><span class="material-icons" aria-hidden="true">info</span><div class="gk-message-content">Compact message for less space.</div></div>

    <h3 class="demo-h3">Small <?= since('message') ?></h3>
    <p class="demo-intro">A notch denser and without a margin of its own, for a place that sets its own gaps: the sentence above a chat, a hint beside a form. All four tones work, light and dark; a long address wraps.</p>
    <div class="gk-flex-col gk-gap-md">
        <div class="gk-message gk-message-info gk-message-sm"><span class="material-icons" aria-hidden="true">lock</span><div class="gk-message-content"><div class="gk-message-header">Your data stays with us</div>The conversation is stored on our server and never passed on.</div></div>
        <div class="gk-message gk-message-success gk-message-sm"><span class="material-icons" aria-hidden="true">check_circle</span><div class="gk-message-content">Saved. Nothing else to do.</div></div>
        <div class="gk-message gk-message-warning gk-message-sm"><span class="material-icons" aria-hidden="true">warning</span><div class="gk-message-content">The link expires in 7 days.</div></div>
        <div class="gk-message gk-message-error gk-message-sm"><span class="material-icons" aria-hidden="true">error</span><div class="gk-message-content">Could not reach the server. Try again in a minute.</div></div>
    </div>

    <h3 style="margin: 32px 0 16px;">Dismissible</h3>
    <div class="gk-message gk-message-warning" id="demo-dismiss-msg"><span class="material-icons" aria-hidden="true">warning</span><div class="gk-message-content">This message can be closed.</div><button class="gk-message-dismiss" onclick="this.parentElement.style.display='none'"><span class="material-icons" aria-hidden="true">close</span></button></div>

    <div class="demo-code"><pre>.gk-message / .gk-message-info / .gk-message-success
.gk-message-warning / .gk-message-error / .gk-message-compact
.gk-message-sm       // Denser, no margin of its own
.gk-message-dismiss  // Close button</pre></div>
</div>

