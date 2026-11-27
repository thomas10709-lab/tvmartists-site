# Mats Thiersch – eigene Website

Eigenständige One-Page-Seite für Mats Thiersch, später auf eigener Domain im All-Inkl-Unteraccount (Muster: `ellichoi-site/`, `predanvoigt-site/`).
Ersatz für die bisherige Wix-Seite. **Status: online auf matsthiersch.com (seit 27.11.2026), für Suchmaschinen freigegeben.**

## Ordner
- `index.html` – die Seite (About, Calendar, Media, Videos, Kontakt)
- `imprint.html` (Impressum) und `privacy.html` (Datenschutzerklärung) – nach dem Muster von michaelsanderling.de; vor dem Start Betreiberangaben, Hosting und Cloudflare prüfen
- `fonts/` – Jost (nur Menü) und Source Serif 4 (lokal, kein Google-Abruf)
- `images/` – `hero.jpg`/`hero-mobile.jpg` (vorläufig) und `gallery-01…10.jpg` (Platzhalter) → durch die Originale gleichen Namens ersetzen
- `.htaccess` – nur für die Kopie auf tvmartists.com: Seite per Link erreichbar (/thiersch-site/), aber mit noindex-Header; `htaccess.prod` gilt auf der echten Domain (leitet http → https und www → Hauptadresse um)

## Was vor dem Start erledigt sein muss
1. Domain steht: `matsthiersch.com` ist in `index.html` (canonical, og:url, og:image, JSON-LD) und `sitemap.xml` eingetragen.
2. ~~noindex entfernen, `robots.txt` auf `Allow: /` + Sitemap~~ – erledigt am 27.11.2026 (Sitemap: Startseite, Impressum, Datenschutz). Offen: Sitemap in der Google Search Console einreichen.
3. Originalfotos (Titelbild, Handy-Titelbild, 10 Galeriebilder) einsetzen; Fotografen-Nennung (Marco Borggreve) prüfen.
4. Video-Links eintragen (`data-yt="VIDEO-ID"` an den vier Karten).
5. Impressum und Datenschutz ausfüllen und prüfen lassen.
6. Facebook-Adresse ergänzen (Symbol ist auskommentiert).
7. Kalender: später aus der Google-Tabelle wie bei Michael Sanderling (`termine.php`, Schlüssel ergänzen).
8. In GitHub unter Settings → Secrets and variables → Actions anlegen: `THIERSCH_FTP_HOST`, `THIERSCH_FTP_USER`, `THIERSCH_FTP_PASSWORD`, optional `THIERSCH_FTP_PATH`. Danach lädt der Workflow `deploy-thiersch.yml` bei jedem Merge nach `main` die Seite hoch. Solange `DOMAIN-TBD` im Text steht oder Secrets fehlen, wird nichts hochgeladen.

## Wichtig
Dieser Ordner wird beim normalen Deploy von tvmartists.com mit auf den Server kopiert. Die `.htaccess` hier hält diese Kopie dort aus Suchmaschinen heraus (nur per Link erreichbar), damit kein doppelter Inhalt neben matsthiersch.com entsteht.
