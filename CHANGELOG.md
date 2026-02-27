# Changelog

## [1.1.0] – 2026-02-27

### Hinzugefügt

- **Backend-Request-Pattern mit Mehrfachangaben:** mehrere Pattern pro Regel per Zeile oder `||` (ODER), innerhalb eines Patterns `&` (UND)
- **Option in Einstellungen:** „Snippets in HTML-Ersetzungen erlauben“ (`html_replacement_allow_snippets`)
- **UI-Hinweis im Formular:** Hinweis unter „Ersetzungs-Inhalt“, dass `[[snippet:...]]` nur bei aktivierter Option aufgelöst wird

### Dokumentation

- **README erweitert:** neue Scope-/Request-Pattern-Syntax, Mehrfach-URL-Beispiele und Hinweis zur optionalen Snippet-Auflösung in HTML-Ersetzungen

## [1.0.2] – 2026-02-27

### Behoben

- **HTML-Ersetzung (Backend-Scope):** Regeln mit Auswahl wie `mediapool` griffen nicht auf Unterseiten wie `mediapool/media`; Backend-Seiten-Matching prüft nun exakt **und** per Präfix
- **HTML-Ersetzung (Backend-Kontext):** Seitenbestimmung war in einigen Fällen unzuverlässig; Ermittlung erfolgt jetzt primär über `rex_be_controller::getCurrentPage()`
- **HTML-Ersetzung (Aktiv-Status):** Aktiv-Checkbox konnte nach dem Bearbeiten als inaktiv gespeichert werden; Status wird nun explizit als `0/1` geführt und robust aus Legacy-Werten (z. B. `|1|`) normalisiert
- **HTML-Ersetzung (Backend-Seiten-Label):** doppelt escapte Titel (`&amp;`) in der Seitenauswahl wurden korrigiert

### Hinzugefügt

- **Dynamische Backend-Seitenauswahl:** statt statischer Liste werden alle verfügbaren Backend-/Addon-Seiten hierarchisch aus der Navigation angeboten
- **Selectpicker für Backend-Seiten:** Mehrfachauswahl mit Suche und Auswahl-Aktionen in der HTML-Ersetzungsmaske
- **Backend-Request-Pattern:** optionale freie Eingabe für Request-Filter (z. B. `page=content/edit&function=add` oder Teilstring-Match auf URL)
- **Datenmigration in `update.php`:** Normalisierung bestehender `status`-Werte und Backend-Scope-Daten, inkl. neuem Feld `scope_backend_request_pattern`

## [1.0.1] – 2026-02-25

### Behoben

- **Kategorien/Listing: `[translate:edit]`** – in `categories.php` und `listing.php` durch `snippets_category_edit` / `snippets_btn_edit` ersetzt
- **HTML-Ersetzung: Abbrechen-Button** verzweigte zurück zur leeren Bearbeiten-Seite statt zur Übersicht
- **HTML-Ersetzung: `OUTPUT_FILTER`** in separate Extension mit `rex_extension::LATE` ausgelagert, damit die HTML-Ersetzung garantiert nach allen anderen `NORMAL`-Filtern (z. B. Sprog, YRewrite) ausgeführt wird

### Hinzugefügt

- Neue i18n-Keys: `snippets_category_edit`, `snippets_category_no_data`, `snippets_btn_edit` (de_de + en_gb)

## [1.0.0] – 2026-02-24

- Initiales Release
