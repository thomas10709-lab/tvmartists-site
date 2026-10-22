/* Synchronisiert die Hintergrund-Farbanimation (60 s) über alle Seiten:
   Der erste Seitenaufruf einer Sitzung startet immer mit dem dunklen Blau (Phase 0);
   weitere Seiten übernehmen die Phase relativ zu diesem Start.
   Ein Neuladen (F5 / Hard Reload) startet die Animation ebenfalls neu bei Dunkelblau.
   Muss synchron im <head> geladen werden (kein defer). */
(function () {
    var DURATION = 60000;
    var KEY = 'bgStart';
    var start = Date.now();
    var isReload = false;
    try {
        var nav = performance.getEntriesByType('navigation')[0];
        isReload = !!nav && nav.type === 'reload';
    } catch (e) {}
    try {
        var stored = parseInt(sessionStorage.getItem(KEY), 10);
        if (!isReload && stored > 0 && stored <= start) start = stored;
        else sessionStorage.setItem(KEY, String(start));
    } catch (e) {}
    var offset = -(((Date.now() - start) % DURATION) / 1000);
    document.documentElement.style.setProperty('--bg-delay', offset.toFixed(3) + 's');
})();
