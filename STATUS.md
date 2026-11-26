# Stand der Websites

Pflege: Bei jeder abgeschlossenen Änderung im selben Pull Request anpassen. Nur öffentliche Angaben, **keine Zugangsdaten**.

| Seite | Adresse | Ordner | Deployment | Status |
|---|---|---|---|---|
| TVM artists | tvmartists.com | Wurzel | `deploy.yml` (SSH `deploy`, nach Merge auf `main`), Cloudflare davor | live |
| Elli Choi | ellichoi.com | `ellichoi-site/` | `deploy-ellichoi.yml` (FTPS) | live |
| Michael Sanderling | michaelsanderling.de | `michael-sanderling/personal/` | `deploy-sanderling.yml` (FTPS) | live |
| Predan Voigt | predanvoigt.com | `predanvoigt-site/` | `deploy-predanvoigt.yml` (FTPS) | live |
| Mats Thiersch | matsthiersch.com | `thiersch-site/` | `deploy-thiersch.yml` (FTPS, Ziel `/matsthiersch.com/`) | **noindex**, Go-live offen |

## Offen
- **Elli Choi, Bio (Herbst 2027):** Der Satz „Next season she will also appear with the Düsseldorfer Symphoniker and the Korean National Symphony Orchestra, among others.“ in `elli-choi.html` und `ellichoi-site/index.html` bezieht sich auf 2027/28 und muss ab der Saison 2027/28 angepasst werden (z. B. „In 2027/28, …“). Die Orchester sollten vorher öffentlich bestätigt sein.
- **Mats Thiersch, Domain:** Domain liegt bei Wix. DNS auf ALL-INKL umstellen: A-Record `@` → 85.13.168.58, `www` CNAME → matsthiersch.com, MX/TXT behalten. Wix verlangt dafür Abrechnungsrechte (Anleitung als PDF an Thomas übergeben). Testadresse: `https://matsthiersch.com.w02274a9.kasserver.com/`.
- **Mats Thiersch, nach dem Umstellen:** DNS-Verbreitung und SSL (KAS) prüfen, dann `noindex` und `Disallow` in `robots.txt` entfernen, `sitemap.xml` vervollständigen, Impressum/Datenschutz (Telefon, Adresse) prüfen.
- **`termine.php` auf dem Server:** Eintrag `'mats'` (Kalender-Feed) wirkt erst nach FTP-Upload. Bis dahin fällt die Seite auf den direkten Google-Abruf zurück.
- **Mats-Kalender:** Einträge für Konzerthaus Berlin und Opéra de Dijon im Google Sheet nachtragen (Thomas).
- **michaelsanderling.de:** „Presskit“ → „Photo Download“ umbenennen (Angebot, noch nicht beauftragt).
- **Elli-Choi-Pressemitteilung:** noch nicht mit Elli besprochen; Titel, Alter, Zitate und schriftliche Bestätigung klären.

## Technische Eckdaten
- Kalender: Google Sheet → Apps Script (`doGet`) → Blöcke `###EVENT###…`; `termine.php?a=<Schlüssel>` als Cache.
- Mehrsprachige Bio (Mats): `html[data-lang]`, EN | DE | FR | 日本語, Erkennung `?lang=` → `localStorage` → Browsersprache.
- Hintergrundfarbe Künstlerseiten `#17355E`; Startseite pulsiert zwischen `#17355E` und `#5A94C8`.
