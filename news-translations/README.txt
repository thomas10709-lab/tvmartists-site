Deutsche Fassungen von News-Artikeln (liegen im Repository, nicht in news-data/).
Pro Artikel eine Datei <Artikel-ID>.json mit "title_de" und "text_de". news-article.php und die News-Listen nutzen sie automatisch,
sobald der Artikel noch keine deutsche Fassung im Admin hat. Eine im Admin gespeicherte deutsche Fassung hat Vorrang.

Weitere Sprachen und Originalsprache: Die Datei darf zusätzlich "title_en"/"text_en" und "title_fr"/"text_fr" enthalten. Mit "base": "de" gilt der im Admin gespeicherte Titel/Text
als deutsch (Originalsprache): Die Seite öffnet dann auf Deutsch, EN und FR kommen aus der Datei. Die Seite zeigt den Umschalter EN | DE | FR für alle vorhandenen Sprachen.
