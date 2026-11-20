/*
 * "Latest News" auf der Startseite.
 * Normalerweise setzt home.php den Block serverseitig in #latestNews ein.
 * Ist der Platz leer (z. B. wenn die Startseite als index.html ausgeliefert wird), baut dieses Skript
 * denselben Block aus news-data/news.json: neueste Text-News der Hauptseite.
 */
(function () {
  "use strict";
  function ready(fn) { if (document.readyState === "loading") { document.addEventListener("DOMContentLoaded", fn); } else { fn(); } }
  function el(tag, cls, text) { var n = document.createElement(tag); if (cls) { n.className = cls; } if (text) { n.textContent = text; } return n; }

  // wie tvm_description() in news-seo.php: Namenszeile ("By …") überspringen, Links kürzen, an Wortgrenze schneiden
  function excerpt(text, max) {
    var paras = String(text || "").replace(/\r\n?/g, "\n").trim().split(/\n{2,}/);
    if (paras.length > 1 && /^by\s/i.test(paras[0].trim()) && paras[0].trim().length <= 160) { paras.shift(); }
    var s = paras.join(" ").replace(/\[([^\]\n]{1,200})\]\(https?:\/\/[^\s)]+\)/g, "$1").replace(/\s+/g, " ").trim();
    if (s.length <= max) { return s; }
    var cut = s.slice(0, max - 1), sp = cut.lastIndexOf(" ");
    if (sp > max * 0.6) { cut = cut.slice(0, sp); }
    return cut.replace(/[ ,.;:-]+$/, "") + "\u2026";
  }

  // Namenszeile ("By …") am Anfang des Textes
  function byline(text) {
    var paras = String(text || "").replace(/\r\n?/g, "\n").trim().split(/\n{2,}/);
    var first = (paras[0] || "").trim();
    if (!(paras.length > 1 && /^by\s/i.test(first) && first.length <= 160)) { return ""; }
    // Datum am Ende weglassen: "By …, 14 November 2026:" -> "By …:"
    return first.replace(/\s+/g, " ").replace(/,?\s*\d{1,2}\.?\s+(?:January|February|March|April|May|June|July|August|September|October|November|December|Januar|Februar|M\u00e4rz|Mai|Juni|Juli|Oktober|Dezember)\s+\d{4}(?=\s*:?\s*$)/i, "");
  }

  // Sprachfassungen der News (news-i18n.php): Browser-Sprache wählen, wenn der Artikel sie hat
  function i18nLang(map) {
    var ls = (navigator.languages && navigator.languages.length) ? navigator.languages : [navigator.language || "en"];
    for (var i = 0; i < ls.length; i++) {
      var l = String(ls[i]).slice(0, 2).toLowerCase();
      if (l === "en" || map[l]) { return l; }
    }
    return "en";
  }
  function translateBlock(slot) {
    var card = slot.querySelector(".ln-card"), ta = card && card.querySelector(".ln-title a");
    var m = ta && /[?&]id=([^&]+)/.exec(ta.getAttribute("href") || "");
    if (!m) { return; }
    var id = decodeURIComponent(m[1]);
    fetch("news-i18n.php", { cache: "no-cache" }).then(function (r) { return r.ok ? r.json() : {}; }).then(function (map) {
      var l = i18nLang(map || {}), tr = map && map[l] && map[l][id];
      if (!tr) { return; }
      ta.textContent = tr.t;
      var te = card.querySelector(".ln-text"); if (te) { te.textContent = excerpt(tr.x, 420); }
      var by = card.querySelector(".ln-by"), nb = byline(tr.x);
      if (by) { if (nb) { by.textContent = nb; } else { by.remove(); } }
      else if (nb && te) { var bp = el("p", "ln-by", nb); card.insertBefore(bp, te); }
      card.querySelectorAll("a").forEach(function (a) {
        var h = a.getAttribute("href") || "";
        if (h.indexOf("news-article.php?id=") === 0 && h.indexOf("&lang=") === -1) { a.setAttribute("href", h + "&lang=" + l); }
      });
    }).catch(function () { /* ohne Übersetzungen bleibt der Block wie er ist */ });
  }

  ready(function () {
    var slot = document.getElementById("latestNews");
    if (!slot || slot.getAttribute("data-hidden") === "1") { return; }   // ausgeblendet (siehe index.html)
    if (slot.querySelector(".ln-card")) { translateBlock(slot); return; }
    fetch("news-data/news.json", { cache: "no-cache" }).then(function (r) { return r.ok ? r.json() : []; }).then(function (all) {
      var list = (Array.isArray(all) ? all : []).filter(function (it) {
        return it && it.type === "article" && it.image && /^\d{8}-\d{6}-[a-f0-9]{6}$/.test(it.id || "") && Array.isArray(it.cats) && it.cats.indexOf("home") !== -1;
      }).sort(function (a, b) { return String(b.ts || "").localeCompare(String(a.ts || "")); });
      var a = list[0];
      if (!a) { return; }
      var url = "news-article.php?id=" + encodeURIComponent(a.id);
      var sec = el("section", "latest-news");
      sec.appendChild(el("h2", "section-label", "Latest News"));
      var card = el("article", "ln-card");
      var h = el("h3", "ln-title"); var ha = el("a", "", a.title || a.alt || "News"); ha.href = url; h.appendChild(ha); card.appendChild(h);
      var il = el("a", "ln-img"); il.href = url;
      var img = document.createElement("img"); img.src = a.image; img.alt = a.alt || a.title || ""; img.loading = "lazy";
      if (a.w && a.h) { img.width = a.w; img.height = a.h; }
      // Hochformat-Bilder: unten 40 % abschneiden (dort steht oft eingebrannte Schrift)
      if (a.w && a.h && a.h > a.w) {
        img.style.aspectRatio = a.w + " / " + Math.round(a.h * 0.6);
        img.style.objectFit = "cover"; img.style.objectPosition = "50% 0";
      }
      if (a.webp) {
        var pic = document.createElement("picture"), so = document.createElement("source");
        so.type = "image/webp"; so.srcset = a.webp; pic.appendChild(so); pic.appendChild(img); il.appendChild(pic);
      } else { il.appendChild(img); }
      card.appendChild(il);
      var by = byline(a.text);
      if (by) { card.appendChild(el("p", "ln-by", by)); }
      card.appendChild(el("p", "ln-text", excerpt(a.text, 420)));
      var more = el("a", "ln-more", "Read more"); more.href = url; card.appendChild(more);
      sec.appendChild(card);
      slot.appendChild(sec);
      translateBlock(slot);
    }).catch(function () { /* ohne News-Liste kein Block */ });
  });
})();
