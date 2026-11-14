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
    return (paras.length > 1 && /^by\s/i.test(first) && first.length <= 160) ? first.replace(/\s+/g, " ") : "";
  }

  ready(function () {
    var slot = document.getElementById("latestNews");
    if (!slot || slot.querySelector(".ln-card")) { return; }
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
    }).catch(function () { /* ohne News-Liste kein Block */ });
  });
})();
