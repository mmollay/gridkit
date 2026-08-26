(function () {
  window.__probe = function (theme, mode) {
    var b = document.querySelector('body.gk-root') || document.body;
    b.setAttribute('data-gk-theme', theme); b.setAttribute('data-gk-mode', mode);
    document.querySelectorAll('.demo-section').forEach(function (s) { s.classList.add('active'); });
    var props = ['color','backgroundColor','borderTopColor','borderRightColor','borderBottomColor','borderLeftColor','outlineColor','boxShadow','fill','stroke','backgroundImage','fontSize','fontWeight','lineHeight','letterSpacing','textTransform','borderTopLeftRadius','borderBottomRightRadius','paddingTop','paddingRight','paddingBottom','paddingLeft','borderTopWidth','borderBottomWidth','transitionProperty','transitionDuration','transitionTimingFunction'];
    var out = {}, seen = Object.create(null);
    document.querySelectorAll('body *').forEach(function (el) {
      if (el.tagName === 'SCRIPT' || el.tagName === 'STYLE' || el.tagName === 'LINK') return;
      var cls = (typeof el.className === 'string' ? el.className : (el.className.baseVal || '')).trim();
      var sig = el.tagName + (cls ? '.' + cls.split(/\s+/).join('.') : '');
      seen[sig] = (seen[sig] || 0) + 1;
      var key = sig + '#' + seen[sig];
      var cs = getComputedStyle(el), rec = {};
      for (var i = 0; i < props.length; i++) { var p = props[i], v = cs[p]; if (v && v !== 'none' && v !== 'normal' && v !== 'rgba(0, 0, 0, 0)') rec[p] = v; }
      if (Object.keys(rec).length) out[key] = rec;
    });
    return out;
  };
  return 'bereit';
})()
