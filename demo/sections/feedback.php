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
<!-- ===== FEEDBACK (merged: toast + confirm + modal) ===== -->
<div class="demo-section" data-section="feedback">
    <h2>Feedback & Dialogs</h2>

    <h3 style="margin: 32px 0 16px;">Toast <?= since('toast') ?></h3>
    <div class="demo-pair">
    <div class="demo-card">
        <p class="demo-intro">Toast notifications for success, error and info messages. Disappear after 3 seconds.</p>
        <div class="demo-btn-row">
            <button class="gk-btn gk-btn-filled gk-btn-success" onclick="GK.toast.success('Successfully saved!')"><span class="material-icons" style="font-size:16px" aria-hidden="true">check_circle</span> Success</button>
            <button class="gk-btn gk-btn-filled gk-btn-danger" onclick="GK.toast.error('Error while saving!')"><span class="material-icons" style="font-size:16px" aria-hidden="true">error</span> Error</button>
            <button class="gk-btn gk-btn-filled gk-btn-warning" onclick="GK.toast.warning('Warning: Limit reached!')"><span class="material-icons" style="font-size:16px" aria-hidden="true">warning</span> Warning</button>
            <button class="gk-btn gk-btn-primary" onclick="GK.toast.info('3 new entries available')"><span class="material-icons" style="font-size:16px" aria-hidden="true">info</span> Info</button>
        </div>
    </div>
    <div class="demo-code"><pre>GK.toast.success('Successfully saved!');
GK.toast.error('Error while saving!');
GK.toast.warning('Warning: Limit reached!');
GK.toast.info('3 new entries');</pre></div>
    </div>

    <h3 class="demo-h3">Announcement after a save <?= since('announce') ?></h3>
    <p class="demo-intro">A toast is for the corner; this line says "Saved." where the person is working, and a screen reader reads it. The region is on the page from the start and never hidden — empty, it draws no box. <code>GK.announce()</code> changes only its words, and the same words twice are read twice.</p>
        <?php
        showcase(function () {
            echo <<<'HTML'
            <div class="demo-announce-row">
              <button type="button" class="gk-btn gk-btn-filled gk-btn-primary" data-demo-announce="Saved." data-tone="success">Save</button>
              <button type="button" class="gk-btn gk-btn-outlined gk-btn-danger" data-demo-announce="Could not save — the connection dropped." data-tone="error">Fail</button>
              <button type="button" class="gk-btn gk-btn-text gk-btn-neutral" data-demo-announce="">Clear</button>
            </div>
            <div class="gk-message gk-message-compact gk-announce" id="demo-announce" role="status" aria-live="polite" aria-atomic="true"></div>
            <script>
            document.addEventListener('click', function (e) {
              var b = e.target.closest('[data-demo-announce]');
              if (b) GK.announce('demo-announce', b.getAttribute('data-demo-announce'), b.getAttribute('data-tone'));
            });
            </script>
            HTML;
        });
        ?>

    <h3 class="demo-h3" id="timeline">Timeline <?= since('timeline') ?></h3>
    <p class="demo-intro">A log of steps, one under the other: what ran, in which order, how each step ended. A real ordered list, so the order is the information; each state has its own symbol <em>and</em> a word beside the title, never a colour alone. Small chips carry cost or provider, a failed step shows its message as a block. The ring of the running step turns, and stands still when the person asked for less motion. Light and dark follow from the roles; long addresses break.</p>
        <?php
        showcase(function () {
            echo <<<'HTML'
            <div class="demo-timelines">
              <div>
                <h4>A run in progress</h4>
                <ol class="gk-timeline" role="list" aria-label="Steps of the run in progress">
                  <li class="gk-timeline-step gk-timeline-ok">
                    <span class="gk-timeline-marker" aria-hidden="true"></span>
                    <div class="gk-timeline-body">
                      <div class="gk-timeline-head">
                        <span class="gk-timeline-title">Write the script</span>
                        <span class="gk-timeline-state">Done</span>
                        <span class="gk-timeline-time"><time datetime="2026-10-17T09:12:03">09:12:03</time> · 4.2 s</span>
                      </div>
                      <div class="gk-timeline-chips"><span class="gk-label gk-label-gray">Claude</span><span class="gk-label gk-label-gray">$0.03</span></div>
                      <p class="gk-timeline-detail">Three scenes, 38 words, ends with a question.</p>
                    </div>
                  </li>
                  <li class="gk-timeline-step gk-timeline-ok">
                    <span class="gk-timeline-marker" aria-hidden="true"></span>
                    <div class="gk-timeline-body">
                      <div class="gk-timeline-head">
                        <span class="gk-timeline-title">Record the voice</span>
                        <span class="gk-timeline-state">Done</span>
                        <span class="gk-timeline-time"><time datetime="2026-10-17T09:12:08">09:12:08</time> · 11 s</span>
                      </div>
                      <div class="gk-timeline-chips"><span class="gk-label gk-label-gray">Text to speech</span><span class="gk-label gk-label-gray">$0.11</span></div>
                    </div>
                  </li>
                  <li class="gk-timeline-step gk-timeline-running" aria-current="step">
                    <span class="gk-timeline-marker" aria-hidden="true"></span>
                    <div class="gk-timeline-body">
                      <div class="gk-timeline-head">
                        <span class="gk-timeline-title">Make the pictures</span>
                        <span class="gk-timeline-state">Running</span>
                        <span class="gk-timeline-time"><time datetime="2026-10-17T09:12:19">09:12:19</time> · 8 s so far</span>
                      </div>
                      <p class="gk-timeline-detail">Picture 2 of 3.</p>
                    </div>
                  </li>
                  <li class="gk-timeline-step gk-timeline-waiting">
                    <span class="gk-timeline-marker" aria-hidden="true"></span>
                    <div class="gk-timeline-body">
                      <div class="gk-timeline-head">
                        <span class="gk-timeline-title">Assemble the video</span>
                        <span class="gk-timeline-state">Waiting</span>
                      </div>
                    </div>
                  </li>
                  <li class="gk-timeline-step gk-timeline-waiting">
                    <span class="gk-timeline-marker" aria-hidden="true"></span>
                    <div class="gk-timeline-body">
                      <div class="gk-timeline-head">
                        <span class="gk-timeline-title">Check the safe zones</span>
                        <span class="gk-timeline-state">Waiting</span>
                      </div>
                    </div>
                  </li>
                </ol>
              </div>
              <div>
                <h4>A run that broke</h4>
                <ol class="gk-timeline" role="list" aria-label="Steps of the run that broke">
                  <li class="gk-timeline-step gk-timeline-ok">
                    <span class="gk-timeline-marker" aria-hidden="true"></span>
                    <div class="gk-timeline-body">
                      <div class="gk-timeline-head">
                        <span class="gk-timeline-title">Write the script</span>
                        <span class="gk-timeline-state">Done</span>
                        <span class="gk-timeline-time"><time datetime="2026-10-17T10:40:11">10:40:11</time> · 3.9 s</span>
                      </div>
                      <div class="gk-timeline-chips"><span class="gk-label gk-label-gray">Claude</span><span class="gk-label gk-label-gray">$0.03</span></div>
                    </div>
                  </li>
                  <li class="gk-timeline-step gk-timeline-failed">
                    <span class="gk-timeline-marker" aria-hidden="true"></span>
                    <div class="gk-timeline-body">
                      <div class="gk-timeline-head">
                        <span class="gk-timeline-title">Make the pictures</span>
                        <span class="gk-timeline-state">Failed</span>
                        <span class="gk-timeline-time"><time datetime="2026-10-17T10:40:15">10:40:15</time> · 31 s</span>
                      </div>
                      <div class="gk-timeline-chips"><span class="gk-label gk-label-gray">Image service</span></div>
                      <p class="gk-timeline-error">The image service did not answer after 3 tries (https://example.org/api/v1/images/generate?scene=2&amp;attempt=3, timeout after 30 s).</p>
                    </div>
                  </li>
                  <li class="gk-timeline-step gk-timeline-skipped">
                    <span class="gk-timeline-marker" aria-hidden="true"></span>
                    <div class="gk-timeline-body">
                      <div class="gk-timeline-head">
                        <span class="gk-timeline-title">Assemble the video</span>
                        <span class="gk-timeline-state">Skipped</span>
                      </div>
                      <p class="gk-timeline-detail">Needs the pictures.</p>
                    </div>
                  </li>
                  <li class="gk-timeline-step gk-timeline-skipped">
                    <span class="gk-timeline-marker" aria-hidden="true"></span>
                    <div class="gk-timeline-body">
                      <div class="gk-timeline-head">
                        <span class="gk-timeline-title">Check the safe zones</span>
                        <span class="gk-timeline-state">Skipped</span>
                      </div>
                    </div>
                  </li>
                </ol>
              </div>
            </div>
            HTML;
        });
        ?>

    <hr style="border:none;border-top:1px solid var(--gk-outline-variant);margin:40px 0">

    <h3>Confirm <?= since('confirm') ?></h3>
    <div class="demo-pair">
    <div class="demo-card">
        <p class="demo-intro">Confirm dialogs as clean modals. Promise-based, with danger mode for destructive actions.</p>
        <div class="demo-btn-row">
            <button class="gk-btn gk-btn-primary" onclick="GK.confirm('Send invoice to customer?', {title:'Send Invoice', confirmText:'Send'}).then(function(ok){ if(ok) GK.toast.success('Sent!'); })"><span class="material-icons" style="font-size:16px" aria-hidden="true">send</span> Standard Confirm</button>
            <button class="gk-btn gk-btn-danger" onclick="GK.confirm('Really permanently delete this entry?', {title:'Delete Entry', confirmText:'Delete', danger:true}).then(function(ok){ if(ok) GK.toast.success('Deleted!'); })"><span class="material-icons" style="font-size:16px" aria-hidden="true">delete_forever</span> Danger Confirm</button>
        </div>
    </div>
    <div class="demo-code"><pre>GK.confirm('Send invoice?', {
    title: 'Send Invoice', confirmText: 'Send'
}).then(ok => { if (ok) GK.toast.success('Sent!'); });

GK.confirm('Really delete?', {
    title: 'Delete', confirmText: 'Delete', danger: true
}).then(ok => { if (ok) { /* ... */ } });</pre></div>
    </div>

    <hr style="border:none;border-top:1px solid var(--gk-outline-variant);margin:40px 0">

    <h3>Modal <?= since('modal') ?></h3>
    <div class="demo-pair">
        <div class="demo-pair-left">
            <div class="demo-card">
                <p class="demo-intro">Modals in four sizes. Loaded via AJAX, backdrop click and ESC close them.</p>
                <div class="demo-btn-row">
                    <button class="gk-btn" onclick="GK.modal.open('Small (420px)', 'form/f_delete.php', {}, 'small')"><span class="material-icons" style="font-size:16px" aria-hidden="true">crop_square</span> Small</button>
                    <button class="gk-btn gk-btn-primary" onclick="GK.modal.open('Medium (640px)', 'form/f_articles.php', {}, 'medium')"><span class="material-icons" style="font-size:16px" aria-hidden="true">crop_din</span> Medium</button>
                    <button class="gk-btn gk-btn-primary" onclick="GK.modal.open('Large (900px)', 'form/f_articles.php', {}, 'large')"><span class="material-icons" style="font-size:16px" aria-hidden="true">crop_free</span> Large</button>
                    <button class="gk-btn gk-btn-filled gk-btn-neutral" onclick="GK.modal.open('Fullscreen Modal', 'form/f_articles.php', {}, 'full')"><span class="material-icons" style="font-size:16px" aria-hidden="true">fullscreen</span> Full</button>
                </div>
            </div>
            <div class="demo-card">
                <h3 style="margin:0 0 8px; font-size:15px; color:var(--gk-on-surface, #374151);">Nesting: Modal with form</h3>
                <p class="demo-intro">A modal loads a form via AJAX – the most common use case.</p>
                <div class="demo-btn-row">
                    <button class="gk-btn gk-btn-primary" onclick="GK.modal.open('Edit Article', 'form/f_articles.php', {}, 'medium')"><span class="material-icons" style="font-size:16px" aria-hidden="true">edit</span> Modal + Form</button>
                </div>
            </div>
            <div class="demo-card">
                <h3 style="margin:0 0 8px; font-size:15px; color:var(--gk-on-surface, #374151);">Nesting: Modal with table + sub-modal</h3>
                <p class="demo-intro">A large modal shows a customer list. Clicking "Edit" opens a second modal with the form.</p>
                <div class="demo-btn-row">
                    <button class="gk-btn gk-btn-primary" onclick="GK.modal.open('Customer Management', 'form/f_table_modal.php', {}, 'large')"><span class="material-icons" style="font-size:16px" aria-hidden="true">people</span> Modal + Table + Sub-Modal</button>
                </div>
            </div>
        </div>
        <div class="demo-code"><pre>GK.modal.open('Title', 'form/edit.php', {id: 42}, 'small');
GK.modal.open('Title', 'form/edit.php', {id: 42}, 'medium');
GK.modal.open('Title', 'form/edit.php', {id: 42}, 'large');
GK.modal.open('Title', 'form/edit.php', {id: 42}, 'full');

$table->button('edit', ['icon' => 'edit', 'modal' => 'edit_form'])
    ->modal('edit_form', 'Edit', 'form/edit.php', ['size' => 'large']);</pre></div>
    </div>
</div>

