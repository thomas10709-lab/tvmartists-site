/*
 * "News"-Button auf den Künstlerseiten (tvmartists.com)
 * Erscheint ganz links in der Button-Reihe, aber erst, wenn für diesen Künstler
 * mindestens eine News MIT TEXT (type "article") vorliegt (news-data/news.json, gepflegt über news-admin/).
 * Der Künstler wird wie beim Karussell über data-news-key am .news-carousel-wrapper erkannt.
 */
(function () {
  "use strict";
  function ready(fn) {
    if (document.readyState === "loading") { document.addEventListener("DOMContentLoaded", fn); } else { fn(); }
  }
  ready(function () {
    var wrapper = document.querySelector(".news-carousel-wrapper");
    var row = document.getElementById("top-button-row");
    var key = wrapper && wrapper.dataset.newsKey;
    if (!row || !key || key === "home" || row.querySelector("[data-news-button]")) { return; }
    fetch("news-data/news.json", { cache: "no-cache" }).then(function (res) {
      return res.ok ? res.json() : [];
    }).then(function (all) {
      var has = Array.isArray(all) && all.some(function (it) {
        return it && it.type === "article" && Array.isArray(it.cats) && it.cats.indexOf(key) !== -1;
      });
      if (!has) { return; }
      var a = document.createElement("a");
      a.href = "news.html?artist=" + encodeURIComponent(key);
      a.className = "custom-btn";
      a.setAttribute("data-news-button", "");
      a.textContent = "News";
      row.insertBefore(a, row.firstChild);
    }).catch(function () { /* ohne News-Liste einfach kein Button */ });
  });
})();
