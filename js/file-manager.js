/* GridKit file manager v1.125.0 – Vanilla, zero dependencies. Its own file for the reason js/tree.js is one:
 * the PawBot dashboard keeps a changed copy of gridkit.js that no sync ever overwrites. Load it after
 * gridkit.js (skeleton.php does); nothing here needs gridkit.js — the words, the files and the limits come in
 * the manager's own config. GK.toast and GK.confirm are used when they are there.
 *
 *   <section class="gk-files" data-gk-files="media">                     ← GridKit\FileManager
 *     a script element of type application/json, data-gk-files-config: {files, folders, actions, limits, words}
 *     form.gk-files-toolbar (GET) · form.gk-files-upload (multipart) · form.gk-files-body (POST, checkboxes)
 *     aside.gk-files-details
 *
 * Without this script every part works as a plain form or link. With it: search, type, sort and view act at
 * once and stay in the address; files chosen, dropped on the manager or pasted are checked here (type, size,
 * count) and sent one by one — a few at the same time — with a progress bar each, a way to cancel and to try
 * again; the server's answer {"ok":true,"file":{…}} puts the file into the listing without a reload. The
 * details open beside the list (the whole screen on a phone); the actions are posted with fetch.
 *
 * Events, on the .gk-files, bubbling:
 *   gk:filesselect   { manager, ids }                                 the selection changed
 *   gk:filesopen     { manager, id, file }                            the details of a file opened
 *   gk:filesupload   { manager, file, name }                          cancelable: a file is about to be sent
 *   gk:filesuploaded { manager, file, answer }                        the server took a file
 *   gk:fileserror    { manager, name, message }                       a file was refused, here or by the server
 *   gk:filesaction   { manager, action, ids, target, name, parent }   cancelable: before an action is posted
 *   gk:filesdone     { manager, action, ids, ok, answer }             after the server answered an action
 */
(function () {
  "use strict";

  var ROOT = "[data-gk-files]";
  var states = new WeakMap();
  var jobSeq = 0;
  // What a file without a type in the browser most likely is, by its name — the server looks at the content.
  var EXT_MIME = {
    jpg: "image/jpeg", jpeg: "image/jpeg", jpe: "image/jpeg", jfif: "image/jpeg", png: "image/png", gif: "image/gif",
    webp: "image/webp", avif: "image/avif", bmp: "image/bmp", tif: "image/tiff", tiff: "image/tiff", svg: "image/svg+xml",
    pdf: "application/pdf", txt: "text/plain", csv: "text/csv", md: "text/plain", rtf: "application/rtf",
    doc: "application/msword", xls: "application/vnd.ms-excel", ppt: "application/vnd.ms-powerpoint",
    docx: "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
    xlsx: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
    pptx: "application/vnd.openxmlformats-officedocument.presentationml.presentation",
    odt: "application/vnd.oasis.opendocument.text", ods: "application/vnd.oasis.opendocument.spreadsheet",
    zip: "application/zip", mp3: "audio/mpeg", mp4: "video/mp4", webm: "video/webm", mov: "video/quicktime"
  };
  var SORTS = ["name-asc", "name-desc", "date-desc", "date-asc", "size-desc", "size-asc", "type-asc", "type-desc"];
  var TYPES = ["all", "image", "document", "other"];

  /* ── Words and small shapes ──────────────────────────────────────────── */

  function fill(text, params) {
    text = String(text);
    for (var name in params || {}) text = text.split("{" + name + "}").join(String(params[name]));
    return text;
  }
  function say(st, key, params) {
    var t = st.cfg.t || {};
    return fill(t[key] != null ? t[key] : key, params);
  }
  function num(st, n) {
    var s = String(Math.abs(Math.trunc(n)));
    var out = "";
    while (s.length > 3) { out = (st.cfg.locale.thousands || "") + s.slice(-3) + out; s = s.slice(0, -3); }
    return (n < 0 ? "-" : "") + s + out;
  }
  // FileManager::size(): bytes, KB, MB with one decimal, GB.
  function size(st, bytes) {
    if (bytes == null) return "–";
    if (bytes < 1024) return say(st, "size_bytes", { n: num(st, bytes) });
    if (bytes < 1048576) return say(st, "size_kb", { n: num(st, Math.max(1, Math.round(bytes / 1024))) });
    var unit = bytes < 1073741824 ? "size_mb" : "size_gb";
    var v = Math.round(bytes / (unit === "size_mb" ? 1048576 : 1073741824) * 10) / 10;
    return say(st, unit, { n: v === Math.floor(v) ? num(st, v) : v.toFixed(1).replace(".", st.cfg.locale.decimal || ".") });
  }
  // PHP's date() for the letters files.date_format uses: d, j, m, n, Y, y, H, G, i.
  function date(st, ts) {
    if (ts == null) return "–";
    var d = new Date(ts * 1000);
    var two = function (n) { return (n < 10 ? "0" : "") + n; };
    var map = { d: two(d.getDate()), j: d.getDate(), m: two(d.getMonth() + 1), n: d.getMonth() + 1, Y: d.getFullYear(),
      y: String(d.getFullYear()).slice(-2), H: two(d.getHours()), G: d.getHours(), i: two(d.getMinutes()) };
    return String(st.cfg.locale.date || "Y-m-d H:i").replace(/[djmnYyHGi]/g, function (c) { return String(map[c]); });
  }
  function fold(s) {
    s = String(s || "").toLowerCase();
    var map = { "ä": "a", "ö": "o", "ü": "u", "ß": "ss", "é": "e", "è": "e", "ê": "e", "á": "a", "à": "a", "â": "a",
      "í": "i", "ó": "o", "ô": "o", "ú": "u", "ç": "c", "ñ": "n" };
    return s.replace(/[äöüßéèêáàâíóôúçñ]/g, function (c) { return map[c]; });
  }
  function extOf(name) {
    var m = /\.([A-Za-z0-9]{1,10})$/.exec(String(name || ""));
    return m ? m[1].toLowerCase() : "";
  }
  function kindOf(mime) {
    mime = String(mime || "");
    if (mime.indexOf("image/") === 0) return "image";
    if (mime === "application/pdf" || mime.indexOf("text/") === 0 || mime.indexOf("officedocument") >= 0 ||
        mime.indexOf("opendocument") >= 0 || ["application/msword", "application/vnd.ms-excel", "application/vnd.ms-powerpoint",
        "application/rtf", "application/csv"].indexOf(mime) >= 0) return "document";
    return "other";
  }
  function kindIcon(f) {
    var m = f.mime || "";
    if (f.kind === "image") return "image";
    if (m === "application/pdf") return "picture_as_pdf";
    if (m.indexOf("sheet") >= 0 || m.indexOf("excel") >= 0 || f.ext === "csv" || f.ext === "tsv") return "table_chart";
    if (m.indexOf("presentation") >= 0 || m.indexOf("powerpoint") >= 0) return "slideshow";
    if (m.indexOf("audio/") === 0) return "audio_file";
    if (m.indexOf("video/") === 0) return "video_file";
    if (m === "application/zip") return "folder_zip";
    if (f.kind === "document") return "description";
    return "insert_drive_file";
  }
  // FileManager::typeWord().
  function typeWord(st, f) {
    var m = f.mime || "", ext = String(f.ext || "").toUpperCase();
    if (f.kind === "image") return say(st, "kind_image", { ext: ext || m.slice(6).toUpperCase() });
    if (m === "application/pdf") return say(st, "kind_pdf");
    if (m.indexOf("sheet") >= 0 || m.indexOf("excel") >= 0 || ext === "CSV") return say(st, "kind_sheet");
    if (m.indexOf("presentation") >= 0 || m.indexOf("powerpoint") >= 0) return say(st, "kind_slides");
    if (m.indexOf("word") >= 0 || m.indexOf("opendocument.text") >= 0 || m === "application/rtf") return say(st, "kind_word");
    if (m.indexOf("text/") === 0) return say(st, "kind_text");
    if (m.indexOf("audio/") === 0) return say(st, "kind_audio");
    if (m.indexOf("video/") === 0) return say(st, "kind_video");
    if (m === "application/zip") return say(st, "kind_archive");
    return say(st, "kind_file", { ext: ext || "?" });
  }
  function el(tag, cls, text) {
    var n = document.createElement(tag);
    if (cls) n.className = cls;
    if (text != null) n.textContent = text;
    return n;
  }
  function icon(name) {
    var s = el("span", "material-icons", name);
    s.setAttribute("aria-hidden", "true");
    return s;
  }
  // FileManager::unsafeUrl() and pictureData(): no javascript:, vbscript: or data: — but a raster picture as data:,
  // and an SVG as data: in a thumbnail, which is only ever an <img>.
  function safeUrl(u, thumb) {
    if (typeof u !== "string" || u.trim() === "") return null;
    var bare = u.replace(/[\x00-\x20\x7f]+/g, "");
    if (/^data:image\/(png|jpeg|gif|webp)[;,]/i.test(bare) || (thumb && /^data:image\/svg\+xml[;,]/i.test(bare))) return u;
    return /^(javascript|vbscript|data):/i.test(bare) ? null : u;
  }
  function idKey(id) {
    return /^[A-Za-z0-9_-]{1,64}$/.test(id) ? id : "h" + Math.abs(hash(id)).toString(16);
  }
  function hash(s) {
    var h = 0;
    for (var i = 0; i < s.length; i++) h = (h * 31 + s.charCodeAt(i)) | 0;
    return h;
  }
  function fire(st, name, detail, cancelable) {
    var d = { manager: st.cfg.id };
    for (var k in detail || {}) d[k] = detail[k];
    var ev = new CustomEvent(name, { bubbles: true, cancelable: !!cancelable, detail: d });
    return st.root.dispatchEvent(ev);
  }
  function announce(st, text) {
    var live = st.live;
    if (!live) return;
    live.textContent = "";
    setTimeout(function () { live.textContent = text; }, 30);
  }
  function toast(kind, text) {
    if (window.GK && GK.toast && typeof GK.toast[kind] === "function") GK.toast[kind](text);
  }
  function confirmAsk(text) {
    if (window.GK && typeof GK.confirm === "function") return GK.confirm(text, { danger: true });
    return Promise.resolve(window.confirm(text));
  }

  /* ── Files: what the server sent, what an answer brings ──────────────── */

  // A file from the config or an answer, in one shape. null when it has no id or no name.
  function normal(st, f) {
    if (!f || typeof f !== "object") return null;
    var id = f.id == null ? "" : String(f.id);
    var name = f.name == null ? "" : String(f.name).replace(/[\x00-\x1f\x7f\s]+/g, " ").trim();
    if (id === "" || name === "") return null;
    var mime = typeof f.mime === "string" && /^[a-z0-9.+-]+\/[a-z0-9.+-]+$/i.test(f.mime) ? f.mime.toLowerCase() : "";
    var ext = extOf(name);
    if (!mime && EXT_MIME[ext]) mime = EXT_MIME[ext];
    var mod = f.modified;
    if (typeof mod === "string" && /^\d+$/.test(mod)) mod = parseInt(mod, 10);
    else if (typeof mod === "string" && mod) { var t = Date.parse(mod.replace(" ", "T")); mod = isNaN(t) ? null : Math.round(t / 1000); }
    else if (typeof mod !== "number") mod = null;
    var sizeN = typeof f.size === "number" ? f.size : (typeof f.size === "string" && /^\d+$/.test(f.size) ? parseInt(f.size, 10) : null);
    var dim = function (v) { v = typeof v === "string" ? parseInt(v, 10) : v; return typeof v === "number" && v > 0 ? v : null; };
    return {
      id: id, name: name, size: sizeN, mime: mime, ext: ext, kind: kindOf(mime), modified: mod,
      url: safeUrl(f.url, false), thumbUrl: safeUrl(f.thumbUrl, true),
      folder: f.folder == null || f.folder === "" ? null : String(f.folder),
      width: dim(f.width), height: dim(f.height)
    };
  }

  // FileManager::itemHtml(), element for element.
  function itemEl(st, f) {
    var cfg = st.cfg;
    var li = el("li", "gk-files-item");
    li.setAttribute("data-gk-file", f.id);
    li.setAttribute("data-gk-kind", f.kind);
    var thumb;
    if (cfg.select !== "none") {
      var cid = "gk-files-" + cfg.key + "-c-" + idKey(f.id);
      var box = el("input", "gk-files-check");
      box.type = cfg.select === "single" ? "radio" : "checkbox";
      box.id = cid;
      box.name = "ids[]";
      box.value = f.id;
      box.setAttribute("aria-label", say(st, "select_one", { name: f.name }));
      li.appendChild(box);
      thumb = el("label", "gk-files-thumb");
      thumb.htmlFor = cid;
    } else {
      thumb = el("span", "gk-files-thumb");
    }
    thumb.setAttribute("aria-hidden", "true");
    var src = f.thumbUrl || (f.kind === "image" ? f.url : null);
    if (src) {
      var img = el("img");
      img.src = src;
      img.alt = "";
      img.loading = "lazy";
      img.decoding = "async";
      thumb.appendChild(img);
    } else {
      thumb.appendChild(el("span", "material-icons", kindIcon(f)));
      thumb.appendChild(el("span", "gk-files-ext", f.ext.toUpperCase()));
    }
    li.appendChild(thumb);
    var a = el("a", "gk-files-name", f.name);
    a.href = String(cfg.fileLink || "?file=__ID__").replace("__ID__", encodeURIComponent(f.id));
    a.title = f.name;
    li.appendChild(a);
    li.appendChild(el("span", "gk-files-type", typeWord(st, f)));
    li.appendChild(el("span", "gk-files-size", size(st, f.size)));
    li.appendChild(el("span", "gk-files-date", date(st, f.modified)));
    return li;
  }

  function folderEl(st, f) {
    var li = el("li", "gk-files-item gk-files-folder");
    li.setAttribute("data-gk-folder", f.id);
    var thumb = el("span", "gk-files-thumb");
    thumb.setAttribute("aria-hidden", "true");
    thumb.appendChild(el("span", "material-icons", "folder"));
    li.appendChild(thumb);
    var a = el("a", "gk-files-name");
    a.href = f.url || "?folder=" + encodeURIComponent(f.id);
    a.appendChild(el("span", "gk-sr-only", say(st, "folder") + " "));
    a.appendChild(document.createTextNode(f.name));
    li.appendChild(a);
    return li;
  }

  /* ── The listing: filter, sort, view ─────────────────────────────────── */

  function controls(st) {
    var r = st.root;
    return {
      q: r.querySelector("[data-gk-files-q]"),
      type: r.querySelector("[data-gk-files-type]"),
      sort: r.querySelector("[data-gk-files-sort]"),
      views: r.querySelectorAll("[data-gk-files-view]")
    };
  }

  function readControls(st) {
    var c = controls(st);
    if (c.q) st.q = c.q.value;
    if (c.type && TYPES.indexOf(c.type.value) >= 0) st.type = c.type.value;
    if (c.sort && SORTS.indexOf(c.sort.value) >= 0) st.sort = c.sort.value;
    for (var i = 0; i < c.views.length; i++) if (c.views[i].checked) st.view = c.views[i].value;
  }

  function compare(st) {
    var parts = st.sort.split("-"), key = parts[0], sign = parts[1] === "desc" ? -1 : 1;
    var coll = window.Intl && Intl.Collator ? new Intl.Collator(undefined, { numeric: true, sensitivity: "base" }) : null;
    var byName = function (a, b) { return coll ? coll.compare(a.name, b.name) : (fold(a.name) < fold(b.name) ? -1 : fold(a.name) > fold(b.name) ? 1 : 0); };
    return function (a, b) {
      var c = 0;
      if (key === "date") c = (a.modified || 0) - (b.modified || 0);
      else if (key === "size") c = (a.size || 0) - (b.size || 0);
      else if (key === "type") c = a.ext < b.ext ? -1 : a.ext > b.ext ? 1 : 0;
      if (c === 0) c = byName(a, b);
      if (c === 0) c = a.id < b.id ? -1 : a.id > b.id ? 1 : 0;
      return c * sign;
    };
  }

  // Shows what matches, in the chosen order, and says how much that is.
  function apply(st, quiet) {
    var r = st.root, cfg = st.cfg;
    r.classList.toggle("gk-files-view-grid", st.view === "grid");
    r.classList.toggle("gk-files-view-list", st.view === "list");
    var needle = fold(st.q.trim());
    var list = st.list;
    var folders = Array.prototype.slice.call(list.querySelectorAll(":scope > .gk-files-folder"));
    var shownFolders = 0;
    folders.forEach(function (li) {
      var name = fold(li.querySelector(".gk-files-name").textContent.replace(say(st, "folder") + " ", ""));
      var on = needle === "" || name.indexOf(needle) >= 0;
      li.hidden = !on;
      if (on) shownFolders++;
    });
    var files = [];
    st.files.forEach(function (f) {
      if (cfg.useFolders && f.folder !== (cfg.folder == null ? null : String(cfg.folder))) {
        var other = st.items.get(f.id);
        if (other) other.hidden = true;
        return;
      }
      files.push(f);
    });
    files.sort(compare(st));
    var shown = 0;
    files.forEach(function (f) {
      var li = st.items.get(f.id);
      if (!li) return;
      var on = (st.type === "all" || f.kind === st.type) && (needle === "" || fold(f.name).indexOf(needle) >= 0);
      li.hidden = !on;
      if (on) shown++;
      list.appendChild(li);
    });
    var text = say(st, shown === 1 ? "count_one" : "count_n", { n: num(st, shown) });
    if (shown !== files.length) text = say(st, "shown_n", { n: num(st, shown), total: num(st, files.length) });
    if (shownFolders) text += " · " + say(st, shownFolders === 1 ? "folders_one" : "folders_n", { n: num(st, shownFolders) });
    if (st.summary) st.summary.textContent = text;
    if (st.empty) {
      st.empty.hidden = shown + shownFolders > 0;
      st.empty.textContent = say(st, needle !== "" || st.type !== "all" ? "no_match" : "empty");
    }
    // What the person changed is said and kept in the address; a redraw of the script's own is not.
    if (!quiet) {
      announce(st, text);
      remember(st);
    }
  }

  // The state in the address, so a reload or a shared link shows the same.
  function remember(st) {
    if (!window.history || !history.replaceState || !window.URL) return;
    try {
      var u = new URL(location.href);
      var p = st.cfg.param;
      var set = function (k, v, dflt) {
        k = p ? p + "[" + k + "]" : k;
        if (v === dflt || v === "") u.searchParams.delete(k); else u.searchParams.set(k, v);
      };
      set("view", st.view, "grid");
      set("sort", st.sort, "name-asc");
      set("type", st.type, "all");
      set("q", st.q.trim(), "");
      if (u.href !== location.href) history.replaceState(history.state, "", u.href);
    } catch (e) { /* a sandboxed frame */ }
  }

  /* ── Selection and the keys ──────────────────────────────────────────── */

  function boxes(st) {
    return Array.prototype.slice.call(st.list.querySelectorAll(".gk-files-check"));
  }
  function selectedIds(st) {
    return boxes(st).filter(function (b) { return b.checked; }).map(function (b) { return b.value; });
  }
  function syncSelection(st, silent) {
    var all = boxes(st), n = 0, visible = 0, visibleOn = 0;
    all.forEach(function (b) {
      var li = b.closest(".gk-files-item");
      li.classList.toggle("gk-files-selected", b.checked);
      if (b.checked) n++;
      if (!li.hidden) { visible++; if (b.checked) visibleOn++; }
    });
    if (st.count) st.count.textContent = say(st, "selected_n", { n: num(st, n) });
    if (st.all) {
      st.all.checked = visible > 0 && visibleOn === visible;
      st.all.indeterminate = visibleOn > 0 && visibleOn < visible;
      st.all.disabled = visible === 0;
    }
    st.root.querySelectorAll("[data-gk-files-action]").forEach(function (b) { b.disabled = n === 0; });
    var target = st.root.querySelector("[data-gk-files-target]");
    if (target) target.disabled = n === 0;
    var key = selectedIds(st).join("\n");
    if (!silent && key !== st.lastSel) fire(st, "gk:filesselect", { ids: selectedIds(st) });
    st.lastSel = key;
  }
  function setChecked(st, ids, on) {
    var want = {};
    ids.forEach(function (id) { want[String(id)] = true; });
    boxes(st).forEach(function (b) {
      if (want[b.value]) b.checked = on !== false;
      else if (st.cfg.select === "single" && on !== false && ids.length) b.checked = false;
    });
    syncSelection(st);
  }

  // The element of an item the keyboard rests on: its box, or its link.
  function stop(li) {
    return li.querySelector(".gk-files-check") || li.querySelector(".gk-files-name");
  }
  function visibleItems(st) {
    return Array.prototype.slice.call(st.list.children).filter(function (li) { return li.classList.contains("gk-files-item") && !li.hidden; });
  }
  function columns(items) {
    if (items.length < 2) return 1;
    var top = items[0].offsetTop, n = 0;
    for (var i = 0; i < items.length && items[i].offsetTop === top; i++) n++;
    return Math.max(1, n);
  }

  function onListKey(st, e) {
    var li = e.target.closest && e.target.closest(".gk-files-item");
    if (!li || !st.list.contains(li)) return;
    var items = visibleItems(st), i = items.indexOf(li), cols = columns(items), to = -1;
    var mod = e.ctrlKey || e.metaKey;
    switch (e.key) {
      case "ArrowRight": to = i + 1; break;
      case "ArrowLeft": to = i - 1; break;
      case "ArrowDown": to = i + cols; break;
      case "ArrowUp": to = i - cols; break;
      case "Home": to = 0; break;
      case "End": to = items.length - 1; break;
      case "a": case "A":
        if (mod && st.cfg.select === "multi") {
          e.preventDefault();
          setChecked(st, items.filter(function (x) { return x.querySelector(".gk-files-check"); }).map(function (x) { return x.getAttribute("data-gk-file"); }), true);
          announce(st, say(st, "selected_n", { n: num(st, selectedIds(st).length) }));
        }
        return;
      case "Enter":
        if (e.target.classList.contains("gk-files-check")) {
          e.preventDefault();
          open(st, li.getAttribute("data-gk-file"));
        }
        return;
      case "Escape":
        if (st.openId != null) return;
        if (selectedIds(st).length) {
          e.preventDefault();
          setChecked(st, selectedIds(st), false);
          announce(st, say(st, "selected_n", { n: 0 }));
        }
        return;
      case "Delete":
        if (st.cfg.actions.delete != null && selectedIds(st).length) {
          e.preventDefault();
          act(st, "delete", selectedIds(st), {});
        }
        return;
      default: return;
    }
    if (st.rtl && (e.key === "ArrowRight" || e.key === "ArrowLeft")) to = e.key === "ArrowRight" ? i - 1 : i + 1;
    e.preventDefault();
    to = Math.max(0, Math.min(items.length - 1, to));
    var next = items[to];
    if (!next) return;
    stop(next).focus();
    if (e.shiftKey && st.cfg.select === "multi") {
      var from = st.anchor && items.indexOf(st.anchor) >= 0 ? items.indexOf(st.anchor) : i;
      var lo = Math.min(from, to), hi = Math.max(from, to), ids = [];
      for (var k = lo; k <= hi; k++) if (items[k].getAttribute("data-gk-file") != null) ids.push(items[k].getAttribute("data-gk-file"));
      setChecked(st, ids, true);
    } else if (!e.shiftKey) {
      st.anchor = next;
    }
  }

  /* ── Details ─────────────────────────────────────────────────────────── */

  function dl(st, list, label, value) {
    list.appendChild(el("dt", null, label));
    var dd = el("dd", null, value);
    list.appendChild(dd);
    return dd;
  }

  function open(st, id) {
    var f = st.files.get(String(id));
    if (!f || !st.details) return;
    var d = st.details, body = d.querySelector("[data-gk-files-details]"), cfg = st.cfg;
    if (st.openId == null) st.opener = document.activeElement;
    st.openId = f.id;
    d.querySelector(".gk-files-details-title").textContent = f.name;
    body.textContent = "";
    var prev = el("div", "gk-files-preview");
    if (f.kind === "image" && (f.url || f.thumbUrl)) {
      var img = el("img");
      img.src = f.url || f.thumbUrl;
      img.alt = say(st, "preview_of", { name: f.name });
      prev.appendChild(img);
    } else {
      prev.appendChild(icon(kindIcon(f)));
    }
    body.appendChild(prev);
    var props = el("dl", "gk-files-props");
    dl(st, props, say(st, "col_type"), typeWord(st, f) + (f.mime ? " (" + f.mime + ")" : ""));
    dl(st, props, say(st, "col_size"), size(st, f.size));
    if (f.kind === "image") {
      var dims = dl(st, props, say(st, "dimensions"), f.width && f.height ? say(st, "pixels", { w: num(st, f.width), h: num(st, f.height) }) : "…");
      if (!(f.width && f.height) && f.url) {
        var probe = new Image();
        probe.onload = function () {
          if (st.openId !== f.id) return;
          f.width = probe.naturalWidth; f.height = probe.naturalHeight;
          dims.textContent = say(st, "pixels", { w: num(st, f.width), h: num(st, f.height) });
        };
        probe.onerror = function () { dims.textContent = "–"; };
        probe.src = f.url;
      } else if (!(f.width && f.height)) {
        dims.textContent = "–";
      }
    }
    dl(st, props, say(st, "col_date"), date(st, f.modified));
    body.appendChild(props);
    if (f.url) {
      var box = el("div", "gk-files-url");
      var lab = el("label", "gk-files-field");
      lab.appendChild(el("span", null, say(st, "url")));
      var input = el("input");
      input.type = "text";
      input.readOnly = true;
      input.value = f.url;
      lab.appendChild(input);
      box.appendChild(lab);
      var row = el("div", "gk-files-details-actions");
      var copy = el("button", "gk-btn gk-btn-outlined gk-btn-primary gk-btn-sm gk-files-copy");
      copy.type = "button";
      copy.setAttribute("data-gk-files-copy", "");
      copy.appendChild(icon("content_copy"));
      copy.appendChild(el("span", null, say(st, "copy")));
      row.appendChild(copy);
      var link = el("a", "gk-btn gk-btn-text gk-btn-primary gk-btn-sm");
      link.href = f.url;
      link.target = "_blank";
      link.rel = "noopener";
      link.appendChild(icon("open_in_new"));
      link.appendChild(el("span", null, say(st, "open")));
      row.appendChild(link);
      box.appendChild(row);
      body.appendChild(box);
    }
    if (cfg.actions.rename != null) {
      var form = el("form", "gk-files-rename");
      form.method = "post";
      form.action = typeof cfg.actions.rename === "string" ? cfg.actions.rename : "";
      form.setAttribute("data-gk-files-form", "rename");
      var rl = el("label", "gk-files-field");
      rl.appendChild(el("span", null, say(st, "new_name")));
      var ri = el("input");
      ri.type = "text";
      ri.name = "name";
      ri.value = f.name;
      ri.required = true;
      ri.maxLength = 255;
      ri.autocomplete = "off";
      rl.appendChild(ri);
      form.appendChild(rl);
      var rb = el("button", "gk-btn gk-btn-tonal gk-btn-primary gk-btn-sm", say(st, "act_rename"));
      rb.type = "submit";
      form.appendChild(rb);
      body.appendChild(form);
    }
    d.hidden = false;
    st.root.classList.add("gk-files-details-open");
    st.items.forEach(function (li, key) { li.classList.toggle("gk-files-current", key === f.id); });
    if (phone()) {
      d.setAttribute("role", "dialog");
      d.setAttribute("aria-modal", "true");
      document.documentElement.classList.add("gk-files-sheet-open");
    }
    d.focus({ preventScroll: !phone() });
    fire(st, "gk:filesopen", { id: f.id, file: f });
  }

  function close(st) {
    if (st.openId == null || !st.details) return;
    var d = st.details;
    d.hidden = true;
    d.removeAttribute("role");
    d.removeAttribute("aria-modal");
    document.documentElement.classList.remove("gk-files-sheet-open");
    st.root.classList.remove("gk-files-details-open");
    var li = st.items.get(st.openId);
    if (li) li.classList.remove("gk-files-current");
    st.openId = null;
    var back = st.opener && document.contains(st.opener) ? st.opener : (li ? stop(li) : null);
    st.opener = null;
    if (back) back.focus();
  }

  function phone() {
    return !!(window.matchMedia && matchMedia("(max-width: 600px)").matches);
  }

  function copyUrl(st, button) {
    var input = st.details.querySelector(".gk-files-url input");
    if (!input) return;
    var url = input.value;
    try { url = new URL(input.value, location.href).href; } catch (e) { /* keep it */ }
    var ok = function () { announce(st, say(st, "copied")); toast("success", say(st, "copied")); };
    // Without the clipboard API (a page served over plain http): the old way, from the selected field.
    var old = function () {
      input.focus();
      input.select();
      var done = false;
      try { done = document.execCommand("copy"); } catch (e) { done = false; }
      if (done) ok(); else announce(st, say(st, "copy_failed"));
    };
    if (navigator.clipboard && navigator.clipboard.writeText) navigator.clipboard.writeText(url).then(ok, old);
    else old();
  }

  /* ── Actions ─────────────────────────────────────────────────────────── */

  function baseData(st) {
    var fd = new FormData(), cfg = st.cfg;
    if (cfg.csrf) fd.append(cfg.csrf.field, cfg.csrf.token);
    for (var k in cfg.hidden || {}) fd.append(k, cfg.hidden[k]);
    return fd;
  }
  function headers(st) {
    var h = { "X-Requested-With": "XMLHttpRequest", Accept: "application/json" };
    if (st.cfg.csrf) h["X-CSRF-Token"] = st.cfg.csrf.token;
    return h;
  }

  // delete, move, rename, folder: ask, fire, post, show what the server did.
  function act(st, name, ids, extra) {
    var target = st.cfg.actions[name];
    if (target == null) return Promise.resolve(false);
    if (name !== "folder" && !ids.length) {
      announce(st, say(st, "none_selected"));
      return Promise.resolve(false);
    }
    var asked = name === "delete" ? confirmAsk(say(st, "confirm_delete", { n: num(st, ids.length) })) : Promise.resolve(true);
    return asked.then(function (yes) {
      if (!yes) return false;
      var detail = { action: name, ids: ids.slice(), target: extra.target != null ? extra.target : null,
        name: extra.name != null ? extra.name : null, parent: name === "folder" ? st.cfg.folder : null };
      if (!fire(st, "gk:filesaction", detail, true) || target === true) return false;
      var fd = baseData(st);
      fd.append("action", name);
      fd.append("folder", st.cfg.folder == null ? "" : st.cfg.folder);
      ids.forEach(function (id) { fd.append("ids[]", id); });
      if (name === "move") fd.append("target", extra.target == null ? "" : extra.target);
      if (name === "rename" || name === "folder") fd.append("name", extra.name);
      if (name === "folder") fd.append("parent", st.cfg.folder == null ? "" : st.cfg.folder);
      st.root.setAttribute("aria-busy", "true");
      return fetch(target, { method: "POST", body: fd, credentials: "same-origin", headers: headers(st) })
        .then(function (res) {
          return res.text().then(function (text) {
            var data = null;
            try { data = JSON.parse(text); } catch (e) { data = null; }
            if (!res.ok || !data || data.ok !== true) throw new Error(data && typeof data.message === "string" ? data.message : say(st, "action_failed"));
            return data;
          });
        })
        .then(function (data) {
          st.root.removeAttribute("aria-busy");
          done(st, name, ids, extra, data);
          fire(st, "gk:filesdone", { action: name, ids: ids, ok: true, answer: data });
          return true;
        }, function (err) {
          st.root.removeAttribute("aria-busy");
          var msg = err && err.message ? err.message : say(st, "action_failed");
          announce(st, msg);
          toast("error", msg);
          fire(st, "gk:filesdone", { action: name, ids: ids, ok: false, answer: null });
          return false;
        });
    });
  }

  function done(st, name, ids, extra, data) {
    var text = "";
    if (name === "delete" || (name === "move" && String(extra.target || "") !== String(st.cfg.folder == null ? "" : st.cfg.folder))) {
      if (st.openId != null && ids.indexOf(st.openId) >= 0) close(st);
      ids.forEach(function (id) { remove(st, id); });
      text = say(st, name === "delete" ? "deleted" : "moved", { n: num(st, ids.length) });
    } else if (name === "move") {
      text = say(st, "moved", { n: num(st, ids.length) });
    } else if (name === "rename") {
      var f = st.files.get(ids[0]);
      if (f) {
        var nf = normal(st, Object.assign({}, f, { name: extra.name }, data.file || {}));
        if (nf) {
          st.files.set(f.id, nf);
          var old = st.items.get(f.id), li = itemEl(st, nf);
          var box = old && old.querySelector(".gk-files-check");
          if (box && box.checked) li.querySelector(".gk-files-check").checked = true;
          if (old) old.replaceWith(li);
          st.items.set(f.id, li);
          if (st.openId === f.id) { st.openId = null; open(st, f.id); }
          text = say(st, "renamed", { name: nf.name });
        }
      }
    } else if (name === "folder") {
      var fo = data.folder && data.folder.id != null ? data.folder : null;
      var fname = fo && fo.name ? String(fo.name) : extra.name;
      if (fo) {
        var entry = { id: String(fo.id), name: fname, url: safeUrl(fo.url, false) };
        var first = st.list.querySelector(":scope > .gk-files-item:not(.gk-files-folder)");
        st.list.insertBefore(folderEl(st, entry), first);
        var sel = st.root.querySelector("[data-gk-files-target]");
        if (sel) { var o = el("option", null, fname); o.value = entry.id; sel.appendChild(o); }
      }
      text = say(st, "folder_created", { name: fname });
      var det = st.root.querySelector(".gk-files-newfolder");
      if (det) { det.open = false; var inp = det.querySelector("input[name=name]"); if (inp) inp.value = ""; }
    }
    if (data.message && typeof data.message === "string") text = data.message;
    apply(st, true);
    syncSelection(st);
    if (text) { announce(st, text); toast("success", text); }
  }

  function remove(st, id) {
    id = String(id);
    var li = st.items.get(id);
    if (li) {
      if (li.contains(document.activeElement)) {
        var items = visibleItems(st), i = items.indexOf(li), next = items[i + 1] || items[i - 1];
        if (next && next !== li) stop(next).focus();
      }
      li.remove();
    }
    st.items.delete(id);
    st.files.delete(id);
  }

  function add(st, raw) {
    var f = normal(st, raw);
    if (!f) return null;
    if (st.items.has(f.id)) remove(st, f.id);
    st.files.set(f.id, f);
    var li = itemEl(st, f);
    st.items.set(f.id, li);
    st.list.appendChild(li);
    apply(st, true);
    syncSelection(st, true);
    return f;
  }

  /* ── Upload ──────────────────────────────────────────────────────────── */

  function accepts(st, file) {
    var type = String(file.type || "").toLowerCase(), ext = extOf(file.name);
    if (!type) type = EXT_MIME[ext] || "";
    var list = st.cfg.accept || [];
    for (var i = 0; i < list.length; i++) {
      var a = list[i];
      if (a === type && type) return true;
      // image/* takes raster images, never SVG — the server's rule.
      if (/\/\*$/.test(a) && type.indexOf(a.slice(0, -1)) === 0 && type !== "image/svg+xml") return true;
      if (a.charAt(0) === "." && a.slice(1) === ext && (ext !== "svg" || a === ".svg")) return true;
    }
    return false;
  }

  function queue(st, list, source) {
    if (!st.cfg.upload) return [];
    var files = Array.prototype.slice.call(list || []);
    if (!files.length) return [];
    var cfg = st.cfg;
    var pending = st.jobs.filter(function (j) { return j.state === "waiting" || j.state === "uploading"; }).length;
    var made = [];
    files.forEach(function (file) {
      var name = file.name || say(st, "pasted") + ".png";
      var job = { id: ++jobSeq, file: file, name: name, state: "waiting", percent: 0, xhr: null, url: null, message: "", canRetry: false };
      var msg = "";
      if (pending >= cfg.maxFiles) msg = say(st, "err_count", { max: num(st, cfg.maxFiles) });
      else if (!accepts(st, file)) msg = say(st, "err_type", { name: name });
      else if (file.size === 0) msg = say(st, "err_empty", { name: name });
      else if (file.size > cfg.maxSize) msg = say(st, "err_size", { name: name, size: size(st, file.size), max: size(st, cfg.maxSize) });
      if (msg) {
        job.state = "error";
        job.message = msg;
        fire(st, "gk:fileserror", { name: name, message: msg });
      } else {
        pending++;
        if (st.batch.total === 0) st.batch.done = 0;
        st.batch.total++;
      }
      st.jobs.push(job);
      jobEl(st, job);
      made.push(job);
    });
    st.queueEl.hidden = false;
    var refused = made.filter(function (j) { return j.state === "error"; });
    if (refused.length) announce(st, refused.map(function (j) { return j.message; }).join(" "));
    pump(st);
    return made;
  }

  function jobEl(st, job) {
    var li = el("li", "gk-files-job");
    li.setAttribute("data-gk-job", String(job.id));
    var thumb = el("span", "gk-files-job-thumb");
    thumb.setAttribute("aria-hidden", "true");
    // A picture before it is sent: an object URL, revoked the moment the picture has been drawn.
    if (/^image\//.test(job.file.type || "") && window.URL && URL.createObjectURL && job.state !== "error") {
      job.url = URL.createObjectURL(job.file);
      var img = el("img");
      img.alt = "";
      img.onload = img.onerror = function () { revoke(job); };
      img.src = job.url;
      thumb.appendChild(img);
    } else {
      thumb.appendChild(el("span", "material-icons", kindIcon({ mime: job.file.type, kind: kindOf(job.file.type), ext: extOf(job.name) })));
    }
    li.appendChild(thumb);
    var main = el("span", "gk-files-job-main");
    main.appendChild(el("span", "gk-files-job-name", job.name));
    var bar = el("progress", "gk-files-progress");
    bar.max = 100;
    bar.value = 0;
    bar.setAttribute("aria-label", job.name);
    main.appendChild(bar);
    var state = el("span", "gk-files-job-state");
    main.appendChild(state);
    li.appendChild(main);
    li.appendChild(el("span", "gk-files-job-actions"));
    job.el = li;
    st.queueEl.appendChild(li);
    paint(st, job);
  }

  function dragEnd(st) {
    st.drag = 0;
    st.root.classList.remove("gk-files-dragover");
    var dt = st.root.querySelector(".gk-files-drop-text[data-gk-files-was]");
    if (dt) { dt.textContent = dt.getAttribute("data-gk-files-was"); dt.removeAttribute("data-gk-files-was"); }
  }

  function revoke(job) {
    if (job.url) { URL.revokeObjectURL(job.url); job.url = null; }
  }

  // The job's row as its state says: a symbol AND a word for each state, never the colour alone.
  function paint(st, job) {
    var li = job.el;
    if (!li) return;
    li.setAttribute("data-state", job.state);
    var symbols = { waiting: "schedule", uploading: "upload", done: "check_circle", error: "error", cancelled: "block" };
    var words = { waiting: say(st, "state_waiting"), uploading: say(st, "state_uploading", { percent: job.percent }),
      done: say(st, "state_done"), error: say(st, "state_error"), cancelled: say(st, "state_cancelled") };
    var state = li.querySelector(".gk-files-job-state");
    state.textContent = "";
    state.appendChild(icon(symbols[job.state]));
    state.appendChild(el("span", null, words[job.state] + (job.state === "error" && job.message ? ": " + job.message : "")));
    var bar = li.querySelector(".gk-files-progress");
    bar.value = job.state === "done" ? 100 : job.percent;
    bar.hidden = job.state === "error" || job.state === "cancelled";
    var acts = li.querySelector(".gk-files-job-actions");
    var hadFocus = acts.contains(document.activeElement);
    acts.textContent = "";
    var btn = function (kind, symbol, label) {
      var b = el("button", "gk-files-job-btn");
      b.type = "button";
      b.setAttribute("data-gk-job-act", kind);
      b.setAttribute("aria-label", label);
      b.title = label;
      b.appendChild(icon(symbol));
      acts.appendChild(b);
      return b;
    };
    var first = null;
    if (job.state === "waiting" || job.state === "uploading") first = btn("cancel", "close", say(st, "cancel", { name: job.name }));
    if ((job.state === "error" && job.canRetry) || job.state === "cancelled") first = btn("retry", "refresh", say(st, "retry", { name: job.name }));
    if (job.state !== "waiting" && job.state !== "uploading") { var d = btn("dismiss", "close", say(st, "dismiss", { name: job.name })); first = first || d; }
    if (hadFocus && first) first.focus();
  }

  function pump(st) {
    var cfg = st.cfg;
    while (st.active < cfg.parallel) {
      var next = null;
      for (var i = 0; i < st.jobs.length; i++) if (st.jobs[i].state === "waiting") { next = st.jobs[i]; break; }
      if (!next) break;
      send(st, next);
    }
  }

  function send(st, job) {
    var cfg = st.cfg;
    if (!fire(st, "gk:filesupload", { file: job.file, name: job.name }, true)) {
      job.state = "cancelled";
      st.batch.total = Math.max(0, st.batch.total - 1);
      paint(st, job);
      return;
    }
    job.state = "uploading";
    job.percent = 0;
    st.active++;
    paint(st, job);
    var fd = baseData(st);
    fd.append("folder", cfg.folder == null ? "" : cfg.folder);
    fd.append("files[]", job.file, job.name);
    var xhr = new XMLHttpRequest();
    job.xhr = xhr;
    xhr.open("POST", cfg.upload, true);
    var h = headers(st);
    for (var k in h) xhr.setRequestHeader(k, h[k]);
    xhr.withCredentials = false;
    xhr.upload.onprogress = function (e) {
      if (!e.lengthComputable) return;
      // 99 at most until the server has answered: sent is not yet taken.
      var p = Math.min(99, Math.floor(e.loaded / e.total * 100));
      if (p !== job.percent) { job.percent = p; paint(st, job); }
    };
    xhr.onload = function () {
      var data = null;
      try { data = JSON.parse(xhr.responseText); } catch (e) { data = null; }
      if (data && data.ok === true && data.file) {
        var f = normal(st, data.file);
        if (f) {
          if (f.folder == null && cfg.folder != null && cfg.useFolders && data.file.folder === undefined) f.folder = String(cfg.folder);
          st.files.set(f.id, f);
          var li = itemEl(st, f);
          if (st.items.has(f.id)) st.items.get(f.id).replaceWith(li);
          st.items.set(f.id, li);
          st.list.appendChild(li);
          apply(st, true);
          syncSelection(st, true);
          finish(st, job, "done", "");
          announce(st, say(st, "uploaded", { name: f.name }));
          fire(st, "gk:filesuploaded", { file: f, answer: data });
          return;
        }
      }
      var msg;
      if (data && data.ok === false) msg = typeof data.message === "string" && data.message ? data.message : say(st, "err_answer");
      else if (xhr.status < 200 || xhr.status >= 300) msg = say(st, "err_http", { name: job.name, status: xhr.status });
      else msg = say(st, "err_answer");
      // A refusal of the server is about the file — trying it again changes nothing. A failure of the line may pass.
      job.canRetry = !(data && data.ok === false) || xhr.status >= 500;
      finish(st, job, "error", msg);
    };
    xhr.onerror = function () { job.canRetry = true; finish(st, job, "error", say(st, "err_network", { name: job.name })); };
    xhr.onabort = function () { finish(st, job, "cancelled", ""); };
    xhr.send(fd);
  }

  function finish(st, job, state, msg) {
    if (job.state !== "uploading") return;
    job.state = state;
    job.message = msg;
    job.xhr = null;
    st.active = Math.max(0, st.active - 1);
    if (state === "done") st.batch.done++;
    if (state === "error") {
      announce(st, say(st, "failed", { name: job.name, message: msg }));
      fire(st, "gk:fileserror", { name: job.name, message: msg });
    }
    if (state === "cancelled") {
      // A file the person stopped is not one the batch failed to send.
      st.batch.total = Math.max(0, st.batch.total - 1);
      announce(st, job.name + ": " + say(st, "state_cancelled"));
    }
    paint(st, job);
    pump(st);
    var busy = st.jobs.some(function (j) { return j.state === "waiting" || j.state === "uploading"; });
    if (!busy && st.batch.total > 0) {
      var line = say(st, "batch_done", { done: num(st, st.batch.done), total: num(st, st.batch.total) });
      // Said after the last file's own sentence, not over it.
      setTimeout(function () { announce(st, line); }, 700);
      st.batch.total = 0;
    }
  }

  function jobAction(st, id, kind) {
    var job = null;
    for (var i = 0; i < st.jobs.length; i++) if (String(st.jobs[i].id) === String(id)) job = st.jobs[i];
    if (!job) return;
    if (kind === "cancel") {
      if (job.state === "uploading" && job.xhr) job.xhr.abort();
      else if (job.state === "waiting") {
        job.state = "cancelled";
        st.batch.total = Math.max(0, st.batch.total - 1);
        paint(st, job);
        announce(st, job.name + ": " + say(st, "state_cancelled"));
      }
    } else if (kind === "retry") {
      job.state = "waiting";
      job.percent = 0;
      job.message = "";
      if (st.batch.total === 0) st.batch.done = 0;
      st.batch.total++;
      paint(st, job);
      pump(st);
    } else if (kind === "dismiss") {
      revoke(job);
      var li = job.el, next = li.nextElementSibling || li.previousElementSibling;
      li.remove();
      st.jobs.splice(st.jobs.indexOf(job), 1);
      if (!st.jobs.length) st.queueEl.hidden = true;
      var target = next && next.querySelector("button");
      if (target) target.focus();
      else { var pick = st.root.querySelector(".gk-files-pick input"); if (pick) pick.focus(); }
    }
  }

  /* ── Wiring ──────────────────────────────────────────────────────────── */

  function init(root) {
    if (states.has(root)) return states.get(root);
    var script = root.querySelector("script[data-gk-files-config]");
    var cfg = {};
    try { cfg = JSON.parse(script ? script.textContent : "{}") || {}; } catch (e) { cfg = {}; }
    cfg.t = cfg.t || {};
    cfg.locale = cfg.locale || {};
    cfg.actions = cfg.actions || {};
    cfg.accept = cfg.accept || [];
    cfg.parallel = Math.max(1, Math.min(6, cfg.parallel || 3));
    cfg.maxFiles = cfg.maxFiles || 20;
    cfg.maxSize = cfg.maxSize || 10485760;
    cfg.key = cfg.key || "files";
    var st = {
      root: root, cfg: cfg, files: new Map(), items: new Map(), jobs: [], active: 0, batch: { total: 0, done: 0 },
      view: cfg.view || "grid", sort: cfg.sort || "name-asc", type: cfg.type || "all", q: cfg.q || "",
      openId: null, opener: null, anchor: null, lastSel: null, drag: 0,
      list: root.querySelector(".gk-files-items"), summary: root.querySelector("[data-gk-files-summary]"),
      empty: root.querySelector(".gk-files-empty"), count: root.querySelector("[data-gk-files-count]"),
      all: root.querySelector("[data-gk-files-all]"), details: root.querySelector(".gk-files-details"),
      live: root.querySelector("[data-gk-files-live]"), queueEl: root.querySelector(".gk-files-queue"),
      rtl: getComputedStyle(root).direction === "rtl"
    };
    if (!st.list) return null;
    states.set(root, st);
    (cfg.files || []).forEach(function (raw) {
      var f = normal(st, raw);
      if (f) st.files.set(f.id, f);
    });
    Array.prototype.forEach.call(st.list.querySelectorAll(":scope > [data-gk-file]"), function (li) {
      st.items.set(li.getAttribute("data-gk-file"), li);
    });
    // Files the server did not draw (another folder, another filter) get their element now, so a change of
    // the filter in the browser can show them.
    st.files.forEach(function (f) {
      if (!st.items.has(f.id)) {
        var li = itemEl(st, f);
        li.hidden = true;
        st.items.set(f.id, li);
        st.list.appendChild(li);
      }
    });
    root.classList.add("gk-files-js");
    root.querySelectorAll("[data-gk-files-needs-js]").forEach(function (n) { n.hidden = false; });
    if (st.all) st.all.closest(".gk-files-all").hidden = false;
    if (st.details) {
      var copyBtn = st.details.querySelector("[data-gk-files-copy]");
      if (copyBtn) copyBtn.hidden = false;
      if (!st.details.hidden && cfg.open != null && st.files.has(String(cfg.open))) {
        st.openId = String(cfg.open);
        var cur = st.items.get(st.openId);
        if (cur) cur.classList.add("gk-files-current");
        root.classList.add("gk-files-details-open");
      }
    }
    apply(st, true);
    syncSelection(st, true);
    st.lastSel = selectedIds(st).join("\n");

    // The toolbar acts at once; Enter in the search does not reload the page.
    var tb = root.querySelector(".gk-files-toolbar");
    if (tb) {
      var timer = 0;
      tb.addEventListener("submit", function (e) { e.preventDefault(); readControls(st); apply(st); });
      tb.addEventListener("input", function (e) {
        if (!e.target.matches("[data-gk-files-q]")) return;
        clearTimeout(timer);
        timer = setTimeout(function () { readControls(st); apply(st); }, 200);
      });
      tb.addEventListener("change", function (e) {
        if (e.target.matches("[data-gk-files-q]")) return;
        readControls(st);
        apply(st);
      });
      tb.addEventListener("keydown", function (e) {
        if (e.key === "Escape" && e.target.matches("[data-gk-files-q]") && e.target.value !== "") {
          e.preventDefault();
          e.target.value = "";
          readControls(st);
          apply(st);
        }
      });
    }

    // Selection.
    st.list.addEventListener("change", function (e) {
      if (!e.target.classList.contains("gk-files-check")) return;
      syncSelection(st);
    });
    st.list.addEventListener("click", function (e) {
      var box = e.target.closest(".gk-files-check");
      if (box && e.shiftKey && st.cfg.select === "multi" && st.anchor) {
        var items = visibleItems(st), a = items.indexOf(st.anchor), b = items.indexOf(box.closest(".gk-files-item"));
        if (a >= 0 && b >= 0) {
          var ids = [];
          for (var k = Math.min(a, b); k <= Math.max(a, b); k++) if (items[k].getAttribute("data-gk-file") != null) ids.push(items[k].getAttribute("data-gk-file"));
          setTimeout(function () { setChecked(st, ids, true); }, 0);
        }
      } else if (box) {
        st.anchor = box.closest(".gk-files-item");
      }
      var link = e.target.closest(".gk-files-name");
      if (link && !e.ctrlKey && !e.metaKey && !e.shiftKey && e.button === 0) {
        var li = link.closest("[data-gk-file]");
        if (li) { e.preventDefault(); open(st, li.getAttribute("data-gk-file")); }
      }
    });
    st.list.addEventListener("keydown", function (e) { onListKey(st, e); });
    if (st.all) st.all.addEventListener("change", function () {
      var ids = visibleItems(st).filter(function (x) { return x.getAttribute("data-gk-file") != null; }).map(function (x) { return x.getAttribute("data-gk-file"); });
      setChecked(st, ids, st.all.checked);
    });

    // The bulk actions: posted here, except a download, which the browser fetches as the form says.
    var body = root.querySelector(".gk-files-body");
    if (body) body.addEventListener("submit", function (e) {
      var sub = e.submitter;
      var name = sub && sub.getAttribute("data-gk-files-action");
      if (name === "download" && typeof st.cfg.actions.download === "string") return;
      e.preventDefault();
      if (!name) return;
      var extra = {};
      if (name === "move") {
        var sel = root.querySelector("[data-gk-files-target]");
        extra.target = sel ? sel.value : "";
      }
      if (name === "download") {
        fire(st, "gk:filesaction", { action: "download", ids: selectedIds(st), target: null, name: null, parent: null }, true);
        return;
      }
      act(st, name, selectedIds(st), extra);
    });

    // New folder.
    root.addEventListener("submit", function (e) {
      var form = e.target;
      var kind = form.getAttribute && form.getAttribute("data-gk-files-form");
      if (!kind) return;
      e.preventDefault();
      var input = form.querySelector("input[name=name]");
      var value = input ? input.value.trim() : "";
      if (!value) { if (input) input.focus(); return; }
      if (kind === "folder") act(st, "folder", [], { name: value });
      else if (kind === "rename" && st.openId != null) act(st, "rename", [st.openId], { name: value });
    });

    // Details.
    if (st.details) {
      st.details.addEventListener("click", function (e) {
        if (e.target.closest(".gk-files-details-close")) { e.preventDefault(); close(st); }
        var c = e.target.closest("[data-gk-files-copy]");
        if (c) copyUrl(st, c);
      });
      st.details.addEventListener("keydown", function (e) {
        if (e.key === "Escape") { e.preventDefault(); close(st); return; }
        if (e.key === "Tab" && st.details.getAttribute("aria-modal") === "true") {
          var f = Array.prototype.filter.call(st.details.querySelectorAll("a[href], button, input, select, textarea"), function (n) { return !n.disabled && n.offsetParent !== null; });
          if (!f.length) return;
          if (e.shiftKey && (document.activeElement === f[0] || document.activeElement === st.details)) { e.preventDefault(); f[f.length - 1].focus(); }
          else if (!e.shiftKey && document.activeElement === f[f.length - 1]) { e.preventDefault(); f[0].focus(); }
        }
      });
    }

    // Upload: chosen, dropped, pasted.
    var upForm = root.querySelector(".gk-files-upload");
    if (upForm && cfg.upload) {
      var input = upForm.querySelector("input[type=file]");
      upForm.addEventListener("submit", function (e) {
        e.preventDefault();
        if (input && input.files.length) { queue(st, input.files, "pick"); input.value = ""; }
      });
      if (input) input.addEventListener("change", function () {
        queue(st, input.files, "pick");
        input.value = "";
      });
      if (st.queueEl) st.queueEl.addEventListener("click", function (e) {
        var b = e.target.closest("[data-gk-job-act]");
        if (!b) return;
        jobAction(st, b.closest("[data-gk-job]").getAttribute("data-gk-job"), b.getAttribute("data-gk-job-act"));
      });
      var hasFiles = function (e) {
        var types = e.dataTransfer && e.dataTransfer.types;
        return !!types && Array.prototype.indexOf.call(types, "Files") >= 0;
      };
      root.addEventListener("dragenter", function (e) {
        if (!hasFiles(e)) return;
        e.preventDefault();
        st.drag++;
        if (!root.classList.contains("gk-files-dragover")) {
          root.classList.add("gk-files-dragover");
          var dt = root.querySelector(".gk-files-drop-text");
          if (dt) { dt.setAttribute("data-gk-files-was", dt.textContent); dt.textContent = say(st, "drop_here"); }
        }
      });
      root.addEventListener("dragover", function (e) {
        if (!hasFiles(e)) return;
        e.preventDefault();
        e.dataTransfer.dropEffect = "copy";
      });
      root.addEventListener("dragleave", function () {
        st.drag = Math.max(0, st.drag - 1);
        if (!st.drag) dragEnd(st);
      });
      root.addEventListener("drop", function (e) {
        if (!hasFiles(e)) return;
        e.preventDefault();
        dragEnd(st);
        queue(st, e.dataTransfer.files, "drop");
      });
    }
    return st;
  }

  // A picture pasted while the focus is in a manager — or on the page itself, when it has only one.
  document.addEventListener("paste", function (e) {
    var data = e.clipboardData;
    if (!data || !data.items) return;
    var t = e.target, root = t && t.closest ? t.closest(ROOT) : null;
    if (!root) {
      if (t && (t.isContentEditable || /^(INPUT|TEXTAREA|SELECT)$/.test(t.tagName))) return;
      var all = document.querySelectorAll(ROOT);
      var up = Array.prototype.filter.call(all, function (r) { var s = init(r); return s && s.cfg.upload; });
      if (up.length !== 1) return;
      root = up[0];
    } else if (t.isContentEditable || /^(TEXTAREA)$/.test(t.tagName) || (t.tagName === "INPUT" && t.type !== "file" && t.type !== "checkbox" && t.type !== "radio")) {
      return;
    }
    var st = init(root);
    if (!st || !st.cfg.upload) return;
    var files = [];
    for (var i = 0; i < data.items.length; i++) {
      var it = data.items[i];
      if (it.kind !== "file") continue;
      var blob = it.getAsFile();
      if (!blob || !/^image\//.test(blob.type)) continue;
      var ext = (blob.type.split("/")[1] || "png").replace("jpeg", "jpg").replace(/\+.*$/, "");
      var d = new Date(), two = function (n) { return (n < 10 ? "0" : "") + n; };
      var stamp = d.getFullYear() + two(d.getMonth() + 1) + two(d.getDate()) + "-" + two(d.getHours()) + two(d.getMinutes()) + two(d.getSeconds());
      var name = say(st, "pasted") + " " + stamp + (files.length ? "-" + (files.length + 1) : "") + "." + ext;
      try { files.push(new File([blob], name, { type: blob.type })); } catch (err) { blob.name = name; files.push(blob); }
    }
    if (!files.length) return;
    e.preventDefault();
    queue(st, files, "paste");
  });

  /* ── API ─────────────────────────────────────────────────────────────── */

  function find(ref) {
    var root = null;
    if (typeof ref === "string") {
      root = document.querySelector('[data-gk-files="' + (window.CSS && CSS.escape ? CSS.escape(ref) : ref) + '"]');
      if (!root) { try { root = document.querySelector(ref); } catch (e) { root = null; } }
    } else if (ref && ref.nodeType === 1) {
      root = ref;
    }
    if (root && !root.matches(ROOT)) root = root.closest(ROOT);
    return root ? init(root) : null;
  }

  var api = {
    add: function (ref, file) { var st = find(ref); return st ? add(st, file) : null; },
    remove: function (ref, ids) {
      var st = find(ref);
      if (!st) return;
      (Array.isArray(ids) ? ids : [ids]).forEach(function (id) { if (String(id) === st.openId) close(st); remove(st, id); });
      apply(st, true);
      syncSelection(st);
    },
    selected: function (ref) { var st = find(ref); return st ? selectedIds(st) : []; },
    select: function (ref, ids, on) {
      var st = find(ref);
      if (!st) return [];
      setChecked(st, (Array.isArray(ids) ? ids : [ids]).map(String), on);
      return selectedIds(st);
    },
    upload: function (ref, files) {
      var st = find(ref);
      if (!st) return [];
      return queue(st, files && files.length != null ? files : [files], "api").map(function (j) { return { name: j.name, state: j.state, message: j.message }; });
    },
    view: function (ref, view) {
      var st = find(ref);
      if (!st) return null;
      if (view === "grid" || view === "list") {
        st.view = view;
        controls(st).views.forEach(function (r) { r.checked = r.value === view; });
        apply(st, true);
      }
      return st.view;
    },
    open: function (ref, id) { var st = find(ref); if (st) open(st, id); },
    close: function (ref) { var st = find(ref); if (st) close(st); }
  };

  window.GK = window.GK || {};
  window.GK.files = api;

  function start() {
    document.querySelectorAll(ROOT).forEach(init);
    if (window.MutationObserver) {
      new MutationObserver(function (records) {
        records.forEach(function (r) {
          r.addedNodes.forEach(function (n) {
            if (n.nodeType !== 1) return;
            if (n.matches(ROOT)) init(n);
            n.querySelectorAll && n.querySelectorAll(ROOT).forEach(init);
          });
        });
      }).observe(document.documentElement, { childList: true, subtree: true });
    }
  }
  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", start);
  else start();
})();
