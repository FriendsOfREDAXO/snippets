# Changelog

## [1.0.1] – 2026-02-25

### Behoben

- **HTML-Ersetzung: Editieren zeigte leere Felder** – `id` wurde nur aus GET gelesen, jedoch beim Formular-Submit per POST übertragen; `rex_request::request()` liest nun aus beiden Quellen
- **HTML-Ersetzung: „Name existiert bereits" beim Speichern einer bestehenden Regel** – Folgefehler der fehlenden `id`, `nameExists()` schloss die eigene ID nun korrekt aus
- **HTML-Ersetzung: Suchwert mit führenden/nachfolgenden Leerzeichen wurde nicht gefunden** – `trim()` wird jetzt beim Speichern angewendet
- **Kategorien: `[translate:cancel]` und `[translate:no_data_available]`** – falsche Core-Keys ersetzt durch addon-eigene Keys (`snippets_btn_cancel`, `snippets_category_no_data`)
- **Kategorien/Listing: `[translate:edit]`** – in `categories.php` und `listing.php` durch `snippets_category_edit` / `snippets_btn_edit` ersetzt
- **HTML-Ersetzung: Abbrechen-Button** verzweigte zurück zur leeren Bearbeiten-Seite statt zur Übersicht
- **HTML-Ersetzung: `OUTPUT_FILTER`** in separate Extension mit `rex_extension::LATE` ausgelagert, damit die HTML-Ersetzung garantiert nach allen anderen `NORMAL`-Filtern (z. B. Sprog, YRewrite) ausgeführt wird

### Hinzugefügt

- Neue i18n-Keys: `snippets_category_edit`, `snippets_category_no_data`, `snippets_btn_edit` (de_de + en_gb)

## [1.0.0] – 2026-02-24

- Initiales Release
