/*
 * News-Karussell für tvmartists.com
 * Quelle: news-data/news.json (wird über news-admin/ gepflegt)
 * Der Wrapper (.news-carousel-wrapper) trägt data-news-key (home oder der Seitenname)
 */
(function () {
  "use strict";

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

  // Relative Bildpfade aus news.json beziehen sich auf die Wurzel (base).
  function abs(base, p) { return !p || /^(https?:)?\/\//.test(p) ? p : base + p; }

  // --- Quelle: eigene Liste ---------------------------------------------
  // base darf mehrere Kandidaten enthalten, getrennt durch "|" (z. B. "|../../"): der erste, der news.json liefert, gewinnt.
  function loadOwn(key, bases) {
    if (!key) { return Promise.resolve([]); }
    var list = String(bases || "").split("|");
    function attempt(i) {
      if (i >= list.length) { return Promise.resolve([]); }
      var base = list[i];
      return fetch(base + "news-data/news.json", { cache: "no-cache" }).then(function (res) {
        if (!res.ok) { throw new Error("no news.json at " + base); }
        return res.json();
      }).then(function (all) { return build(all, key, base); }).catch(function () { return attempt(i + 1); });
    }
    return attempt(0);
  }
  function build(all, key, base) {
    return Promise.resolve(all).then(function (all) {
      if (!Array.isArray(all)) { return []; }
      return all.filter(function (it) {
        return it && (it.image || it.type === "card") && Array.isArray(it.cats) && it.cats.indexOf(key) !== -1;
      }).sort(function (a, b) {
        return String(b.ts || "").localeCompare(String(a.ts || ""));
      }).slice(0, MAX_ITEMS).map(function (it) {
        return { image: abs(base, it.image), webp: abs(base, it.webp || ""), alt: it.alt || "News", link: it.link || "", type: it.type || "", title: it.title || "", text: it.text || "", source: it.source || "" };
      });
    });
  }

  // --- Link-Karte (Pressekritik o. ä.): Bild, Titel, Auszug, Quelle ---------------
  function injectCardStyles() {
    if (document.getElementById("news-card-styles")) { return; }
    var st = document.createElement("style");
    st.id = "news-card-styles";
    st.textContent =
      ".news-card-slide{display:flex}" +
      ".news-card{display:flex;flex-direction:column;width:100%;background:#fff;border:1px solid #d9e1e8;border-radius:8px;overflow:hidden;color:#2C3E50;text-decoration:none}" +
      ".news-card:hover{box-shadow:0 2px 10px rgba(0,0,0,.12)}" +
      ".news-card img{width:100%;aspect-ratio:3/2;object-fit:cover;border-radius:0;display:block}" +
      ".news-card-body{padding:16px 18px 18px;display:flex;flex-direction:column;gap:10px;flex:1}" +
      ".news-card-title{font-size:19px;font-weight:700;line-height:1.25;text-transform:uppercase;margin:0}" +
      ".news-card-text{font-size:14px;line-height:1.5;color:#556;margin:0;display:-webkit-box;-webkit-line-clamp:7;-webkit-box-orient:vertical;overflow:hidden}" +
      ".news-card-foot{margin-top:auto;font-size:13px;display:flex;justify-content:space-between;gap:10px}" +
      ".news-card-src{font-weight:600}" +
      ".news-card-more{text-decoration:underline;color:#1d63c1}";
    document.head.appendChild(st);
  }

  function buildCard(it) {
    injectCardStyles();
    var a = document.createElement(it.link ? "a" : "div");
    a.className = "news-card";
    if (it.link) { a.href = it.link; a.target = "_blank"; a.rel = "noopener noreferrer"; }
    if (it.image) {
      var img = document.createElement("img");
      img.src = it.image; img.alt = it.alt || it.title || ""; img.loading = "lazy";
      if (it.webp) {
        var pic = document.createElement("picture"), src = document.createElement("source");
        src.type = "image/webp"; src.srcset = it.webp;
        pic.appendChild(src); pic.appendChild(img); a.appendChild(pic);
      } else { a.appendChild(img); }
    }
    var body = document.createElement("div"); body.className = "news-card-body";
    var h = document.createElement("h3"); h.className = "news-card-title"; h.textContent = it.title; body.appendChild(h);
    if (it.text) { var p = document.createElement("p"); p.className = "news-card-text"; p.textContent = it.text; body.appendChild(p); }
    var foot = document.createElement("div"); foot.className = "news-card-foot";
    var s = document.createElement("span"); s.className = "news-card-src"; s.textContent = it.source || ""; foot.appendChild(s);
    if (it.link) { var m = document.createElement("span"); m.className = "news-card-more"; m.textContent = "Read more"; foot.appendChild(m); }
    body.appendChild(foot); a.appendChild(body);
    return a;
  }

  // --- Anzeige ------------------------------------------------------------------
  ready(function () {
    var wrapper = document.querySelector(".news-carousel-wrapper");
    var track = document.getElementById("newsTrack");
    var dotsWrap = document.getElementById("newsDots");
    var prevBtn = document.getElementById("newsPrev");
    var nextBtn = document.getElementById("newsNext");
    if (!wrapper || !track) { return; }

    loadOwn(wrapper.dataset.newsKey || "", wrapper.dataset.newsBase || "").then(function (items) {
      return items;
    }).then(function (items) {
      items = items.filter(function (it) { return !!it.image || it.type === "card"; });
      if (items.length === 0) {
        track.innerHTML = "<p style='padding:20px; text-align:center;'>Noch keine News vorhanden.</p>";
        return;
      }

      items.forEach(function (it) {
        var slide = document.createElement("div");
        slide.className = "news-slide";

        if (it.type === "card") {
          slide.className = "news-slide news-card-slide";
          slide.appendChild(buildCard(it));
          track.appendChild(slide);
          return;
        }

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
        hintOnFirstView();
      }

      // Handy: Beim ersten Erreichen der News kurz nach rechts anscrollen und zurück (Wisch-Hinweis)
      function hintOnFirstView() {
        if (window.innerWidth > 768 || !("IntersectionObserver" in window)) { return; }
        if (window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches) { return; }
        var cancelled = false;
        function cancel() { cancelled = true; }
        track.addEventListener("touchstart", cancel, { passive: true, once: true });
        var io = new IntersectionObserver(function (entries) {
          if (!entries[0].isIntersecting) { return; }
          io.disconnect();
          if (cancelled || track.scrollLeft > 40) { return; }
          var start = track.scrollLeft, dist = 140, half = 550;
          var prevSnap = track.style.scrollSnapType;
          track.style.scrollSnapType = "none";
          var t0 = null;
          function ease(t) { return t < 0.5 ? 2 * t * t : 1 - Math.pow(-2 * t + 2, 2) / 2; }
          function step(ts) {
            if (cancelled) { track.style.scrollSnapType = prevSnap; return; }
            if (t0 === null) { t0 = ts; }
            var p = (ts - t0) / (half * 2);
            if (p >= 1) {
              track.scrollLeft = start;
              track.style.scrollSnapType = prevSnap;
              updateActiveDot();
              return;
            }
            var f = p < 0.5 ? ease(p * 2) : 1 - ease((p - 0.5) * 2);
            track.scrollLeft = start + dist * f;
            requestAnimationFrame(step);
          }
          setTimeout(function () { requestAnimationFrame(step); }, 350);
        }, { threshold: 0.6 });
        io.observe(track);
      }
    }).catch(function () {
      track.innerHTML = "<p style='padding:20px; text-align:center;'>News konnten nicht geladen werden.</p>";
    });
  });
})();
