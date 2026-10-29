/*
 * VORSCHLAG „Reiter“ (Bahn RV, Phase 1) — nur von demo/reiter.php geladen, mit dem Vorschlag löschbar.
 * Eine Reiterzeile, die am Handy waagrecht schiebt, zeigt sonst nur ihren Anfang: steht der gewählte
 * Reiter weiter rechts, sah man ihn nicht. Hier rückt die Zeile ihn ins Bild — ohne die Seite zu
 * bewegen (scrollLeft statt scrollIntoView). In Phase 2 gehört das in GK.tabsMarkup von gridkit.js.
 */
(function () {
  "use strict";
  function zeigeGewaehlte() {
    document.querySelectorAll(".gk-tab-nav").forEach(function (nav) {
      if (nav.scrollWidth <= nav.clientWidth) return;
      var an = nav.querySelector(".gk-tab-btn.gk-active, .gk-tab-btn[aria-current='page']");
      if (!an) return;
      nav.scrollLeft += an.getBoundingClientRect().left - nav.getBoundingClientRect().left - 24;
    });
  }
  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", zeigeGewaehlte);
  else zeigeGewaehlte();
  window.addEventListener("resize", zeigeGewaehlte);
})();
