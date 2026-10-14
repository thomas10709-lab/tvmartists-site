/* Synchronisiert die Hintergrund-Farbanimation (60 s) über alle Seiten:
   Die Phase ergibt sich aus der Uhrzeit, nicht aus dem Seitenstart.
   Muss synchron im <head> geladen werden (kein defer). */
(function () {
    var DURATION = 60000;
    var offset = -((Date.now() % DURATION) / 1000);
    document.documentElement.style.setProperty('--bg-delay', offset.toFixed(3) + 's');
})();
