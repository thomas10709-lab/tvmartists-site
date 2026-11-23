# Mats Thiersch – eigene Website (Entwurf)

Eigenständige One-Page-Seite für Mats Thiersch, später auf eigener Domain im All-Inkl-Unteraccount (Muster: `ellichoi-site/`, `predanvoigt-site/`).
Ersatz für die bisherige Wix-Seite. **Status: Entwurf, nicht online.**

## Ordner
- `index.html` – die Seite (About, Calendar, Media, Videos, Kontakt)
- `imprint.html` (Impressum) und `privacy.html` (Datenschutzerklärung) – nach dem Muster von michaelsanderling.de; vor dem Start Betreiberangaben, Hosting und Cloudflare prüfen
- `fonts/` – Jost (nur Menü) und Source Serif 4 (lokal, kein Google-Abruf)
- `images/` – `hero.jpg`/`hero-mobile.jpg` (vorläufig) und `gallery-01…10.jpg` (Platzhalter) → durch die Originale gleichen Namens ersetzen
- `.htaccess` – nur für den Entwurf auf tvmartists.com: Seite per Link erreichbar (/thiersch-site/), aber mit noindex-Header; `htaccess.prod` gilt auf der echten Domain

## Was vor dem Start erledigt sein muss
1. Domain steht: `matsthiersch.com` ist in `index.html` (canonical, og:url, og:image, JSON-LD) und `sitemap.xml` eingetragen.
2. In `index.html` das `<meta name="robots" content="noindex, nofollow">` und in `imprint.html` und `privacy.html` ebenfalls entfernen; `robots.txt` auf `Allow: /` + Sitemap umstellen.
3. Originalfotos (Titelbild, Handy-Titelbild, 10 Galeriebilder) einsetzen; Fotografen-Nennung (Marco Borggreve) prüfen.
4. Video-Links eintragen (`data-yt="VIDEO-ID"` an den vier Karten).
5. Impressum und Datenschutz ausfüllen und prüfen lassen.
6. Facebook-Adresse ergänzen (Symbol ist auskommentiert).
7. Kalender: später aus der Google-Tabelle wie bei Michael Sanderling (`termine.php`, Schlüssel ergänzen).
8. In GitHub unter Settings → Secrets and variables → Actions anlegen: `THIERSCH_FTP_HOST`, `THIERSCH_FTP_USER`, `THIERSCH_FTP_PASSWORD`, optional `THIERSCH_FTP_PATH`. Danach lädt der Workflow `deploy-thiersch.yml` bei jedem Merge nach `main` die Seite hoch. Solange `DOMAIN-TBD` im Text steht oder Secrets fehlen, wird nichts hochgeladen.

## Wichtig
Dieser Ordner wird beim normalen Deploy von tvmartists.com mit auf den Server kopiert. Die `.htaccess` hier hält ihn dort aus Suchmaschinen heraus (Entwurf, nur per Link erreichbar).
