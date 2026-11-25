# TVM artists – Websites: Arbeitsregeln für Claude

Sprache mit dem Inhaber (Thomas Voigt, Berlin): **Deutsch**. Website-Texte in der Sprache der jeweiligen Seite.

## Arbeitsweise (feste Regeln)
- Ein Branch (`claude/…`), **ein Pull Request pro Änderung**. PR anlegen und **mergen nur, wenn Thomas „merge“ sagt**. „merge“ mitten im Auftrag = PR anlegen und zusammenführen.
- Branch nie zurücksetzen, solange ein PR offen ist.
- Nach dem Merge live prüfen (curl, Playwright). Hinter Cloudflare werden Dateien und 404-Antworten gecacht: bei neuen Dateien Cache-Buster `?v=N` verwenden.
- **Nichts erfinden**: Fakten, Zitate, Daten nur aus Vorlagen oder belegten Quellen. Ungeprüftes kennzeichnen.
- Neutrale Pronomen (they/them), wenn nichts anderes bekannt ist.
- Keine Modellbezeichnung in Commits, PRs oder Code.
- **Keine Geheimnisse** in Chat oder Repo. FTP-Zugänge usw. trägt Thomas selbst in die GitHub Secrets ein.
- Commit-Nachricht endet mit `Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>` und der `Claude-Session`-Zeile aus der Sitzung; PR-Text endet mit „🤖 Generated with [Claude Code](https://claude.com/claude-code)“ und der Session-URL.

## Aufbau
Ein Repository, mehrere Seiten (Übersicht mit Adresse, Ordner, Deployment: siehe `STATUS.md`).
- tvmartists.com: Dateien im Wurzelverzeichnis (`index.html`, Künstlerseiten `*.html`, News, `termine.php`). Deployment: `deploy.yml` (SSH-Befehl `deploy` auf dem ALL-INKL-Server nach Merge auf `main`).
- Weitere Seiten per FTPS-Workflow (`lftp`), jeweils nur bei Änderungen im eigenen Ordner: `ellichoi-site/`, `michael-sanderling/personal/`, `predanvoigt-site/`, `thiersch-site/`.
- Schriften liegen selbst gehostet (OFL) in den `fonts/`-Ordnern, kein Google-Fonts-Abruf.

## Prüfen
- Lokal: `php -S` bzw. Playwright (vorinstalliert, `require('playwright')`).
- Testskripte und Screenshots in den Scratchpad, nicht ins Repo.
