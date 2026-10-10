/*
 * News-Karussell für tvmartists.com
 * Quelle 1: news-data/news.json (wird über news-admin/ gepflegt)
 * Quelle 2 (Ausweichlösung, solange für diese Seite noch keine eigenen News da sind): WordPress
 * Der Wrapper (.news-carousel-wrapper) trägt data-news-key (home oder der Seitenname)
 * und optional data-category (WP-ID) bzw. data-category-name (WP-Kategoriename).
 */
(function () {
  "use strict";

  var WP_API_BASE = "https://tvmartists.com/wp-json/wp/v2";
  var MAX_ITEMS = 10;

  function ready(fn) {
    if (document.readyState === "loading") { document.addEventListener("DOMContentLoaded", fn); } else { fn(); }
  }

  function stripHtml(html) {
    var tmp = document.createElement("div");
    tmp.innerHTML = html;
    return tmp.textContent || tmp.innerText || "";
  }

  function normalize(s) {
    return stripHtml(s || "").toLowerCase().normalize("NFD").replace(/[̀-ͯ]/g, "").trim();
  }

  // --- Quelle 1: eigene Liste ---------------------------------------------
  function loadOwn(key) {
    if (!key) { return Promise.resolve([]); }
    return fetch("news-data/news.json", { cache: "no-cache" }).then(function (res) {
      if (!res.ok) { return []; }
      return res.json();
    }).then(function (all) {
      if (!Array.isArray(all)) { return []; }
      return all.filter(function (it) {
        return it && it.image && Array.isArray(it.cats) && it.cats.indexOf(key) !== -1;
      }).sort(function (a, b) {
        return String(b.ts || "").localeCompare(String(a.ts || ""));
      }).slice(0, MAX_ITEMS).map(function (it) {
        return { image: it.image, webp: it.webp || "", alt: it.alt || "News", link: it.link || "" };
      });
    }).catch(function () { return []; });
  }

  // --- Quelle 2: WordPress (Ausweichlösung) -----------------------------------
  function wpCategoryId(wrapper) {
    var id = parseInt(wrapper.dataset.category, 10);
    if (id) { return Promise.resolve(id); }
    var name = wrapper.dataset.categoryName || "";
    if (!name) { return Promise.resolve(0); }
    var keyword = name.split(" ").pop();
    return fetch(WP_API_BASE + "/categories?per_page=100&search=" + encodeURIComponent(keyword)).then(function (r) {
      if (!r.ok) { throw new Error("Kategorie-Suche fehlgeschlagen: " + r.status); }
      return r.json();
    }).then(function (cats) {
      var wanted = normalize(name);
      var match = cats.find(function (c) { return normalize(c.name) === wanted; }) ||
                  cats.find(function (c) { return normalize(c.name).indexOf(normalize(keyword)) !== -1; });
      if (!match) { throw new Error("Kategorie nicht gefunden"); }
      return match.id;
    });
  }

  function loadWp(wrapper) {
    return wpCategoryId(wrapper).then(function (catId) {
      if (!catId) { return []; }
      return fetch(WP_API_BASE + "/posts?categories=" + catId + "&per_page=" + MAX_ITEMS + "&_embed").then(function (r) {
        if (!r.ok) { throw new Error("WordPress API Fehler: " + r.status); }
        return r.json();
      }).then(function (posts) {
        return posts.filter(function (p) { return (p.categories || []).indexOf(catId) !== -1; }).map(function (p) {
          var image = "";
          try { image = p._embedded["wp:featuredmedia"][0].source_url; } catch (e) { image = ""; }
          return { image: image, webp: "", alt: stripHtml(p.title.rendered), link: "" };
        });
      });
    });
  }

  // --- Anzeige ------------------------------------------------------------------
  ready(function () {
    var wrapper = document.querySelector(".news-carousel-wrapper");
    var track = document.getElementById("newsTrack");
    var dotsWrap = document.getElementById("newsDots");
    var prevBtn = document.getElementById("newsPrev");
    var nextBtn = document.getElementById("newsNext");
    if (!wrapper || !track) { return; }

    loadOwn(wrapper.dataset.newsKey || "").then(function (items) {
      return items.length ? items : loadWp(wrapper);
    }).then(function (items) {
      items = items.filter(function (it) { return !!it.image; });
      if (items.length === 0) {
        track.innerHTML = "<p style='padding:20px; text-align:center;'>Noch keine News vorhanden.</p>";
        return;
      }

      items.forEach(function (it) {
        var slide = document.createElement("div");
        slide.className = "news-slide";

        var img = document.createElement("img");
        img.src = it.image;
        img.alt = it.alt || "News";
        img.loading = "lazy";

        var visual = img;
        if (it.webp) {
          var pic = document.createElement("picture");
          var src = document.createElement("source");
          src.type = "image/webp";
          src.srcset = it.webp;
          pic.appendChild(src);
          pic.appendChild(img);
          visual = pic;
        }
        if (it.link) {
          var a = document.createElement("a");
          a.href = it.link;
          a.target = "_blank";
          a.rel = "noopener noreferrer";
          a.appendChild(visual);
          visual = a;
        }
        slide.appendChild(visual);
        track.appendChild(slide);
      });

      var slides = Array.from(track.children);
      slides.forEach(function (_, i) {
        var dot = document.createElement("div");
        dot.className = "news-carousel-dot" + (i === 0 ? " active" : "");
        dot.addEventListener("click", function () {
          slides[i].scrollIntoView({ behavior: "smooth", inline: "start", block: "nearest" });
        });
        dotsWrap.appendChild(dot);
      });
      var dots = Array.from(dotsWrap.children);

      function getClosestIndex() {
        var trackRect = track.getBoundingClientRect();
        var closestIndex = 0, closestDist = Infinity;
        slides.forEach(function (slide, i) {
          var dist = Math.abs(slide.getBoundingClientRect().left - trackRect.left);
          if (dist < closestDist) { closestDist = dist; closestIndex = i; }
        });
        return closestIndex;
      }
      function updateActiveDot() {
        var idx = getClosestIndex();
        dots.forEach(function (d, i) { d.classList.toggle("active", i === idx); });
      }
      var scrollTimeout;
      track.addEventListener("scroll", function () {
        clearTimeout(scrollTimeout);
        scrollTimeout = setTimeout(updateActiveDot, 80);
      });
      function goToSlide(index) {
        var clamped = Math.max(0, Math.min(slides.length - 1, index));
        slides[clamped].scrollIntoView({ behavior: "smooth", inline: "start", block: "nearest" });
      }
      if (prevBtn) { prevBtn.addEventListener("click", function () { goToSlide(getClosestIndex() - 1); }); }
      if (nextBtn) { nextBtn.addEventListener("click", function () { goToSlide(getClosestIndex() + 1); }); }
      updateActiveDot();

      // Karussell leicht anscrollen, damit die nächste Karte angeschnitten ist
      if (slides.length > 1) {
        requestAnimationFrame(function () {
          requestAnimationFrame(function () {
            track.scrollLeft = window.innerWidth <= 768 ? 32 : 56;
            updateActiveDot();
          });
        });
      }
    }).catch(function () {
      track.innerHTML = "<p style='padding:20px; text-align:center;'>News konnten nicht geladen werden.</p>";
    });
  });
})();
