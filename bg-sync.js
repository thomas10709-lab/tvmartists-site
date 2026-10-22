/* Synchronisiert die Hintergrund-Farbanimation (60 s) über alle Seiten:
   Der erste Seitenaufruf einer Sitzung startet immer mit dem dunklen Blau (Phase 0);
   weitere Seiten übernehmen die Phase relativ zu diesem Start.
   Muss synchron im <head> geladen werden (kein defer). */
(function () {
    var DURATION = 60000;
    var KEY = 'bgStart';
    var start = Date.now();
    try {
        var stored = parseInt(sessionStorage.getItem(KEY), 10);
        if (stored > 0 && stored <= start) start = stored;
        else sessionStorage.setItem(KEY, String(start));
    } catch (e) {}
    var offset = -(((Date.now() - start) % DURATION) / 1000);
    document.documentElement.style.setProperty('--bg-delay', offset.toFixed(3) + 's');
})();
