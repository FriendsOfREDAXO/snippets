# Changelog

## [1.4.0] – 2026-03-10

### Hinzugefügt

- **String-Übersetzungen (Sprog-Alternative):** Neues Übersetzungssystem für mehrsprachige Strings, direkt im Snippets-AddOn integriert
  - Eigene DB-Tabellen (`rex_snippets_string`, `rex_snippets_string_value`) für Key-Value-Übersetzungen pro Sprache
  - Inline-Bearbeitung: Click-to-Edit direkt in der Tabelle, Escape zum Abbrechen, Enter zum Speichern
  - Alle im Frontend verfügbaren Sprachen sind direkt sichtbar und editierbar
  - Platzhalter-Syntax `[[ key ]]` – wird automatisch per OUTPUT_FILTER ersetzt
  - Optionale Sprog-Kompatibilität: `{{ key }}`-Syntax zusätzlich aktivierbar in den Einstellungen
  - **Sprog-Import:** Bestehende Sprog-Wildcards können per Klick importiert werden
- **DeepL-Integration:** KI-Übersetzung per DeepL direkt im Backend
  - Einzelübersetzung: DeepL-Button pro Sprachzelle, übersetzt und speichert sofort
  - **Batch-Übersetzung:** Alle Strings einer Zielsprache serverseitig in einem Request übersetzen (PHP-only, robust)
  - Nutzt den DeepL-API-Key aus dem WriteAssist-AddOn – kein separater Key nötig
  - Automatisches Sprach-Mapping (REDAXO-Code → DeepL-Code), konfigurierbar in Einstellungen
- **PHP-API für direkte Nutzung:**
  - `SnippetsTranslate::get('key')` – übersetzten Wert in Modulen/Templates abrufen
  - `SnippetsTranslate::get('key', 2)` – bestimmte Sprache
  - `SnippetsTranslate::get('key', null, 'Fallback')` – mit Standardwert
  - `SnippetsTranslate::replace($content, $clangId)` – Platzhalter in beliebigem Content ersetzen
- **Verschachtelte Snippets:** Platzhalter `[[ key ]]` in Übersetzungswerten werden rekursiv aufgelöst (max. 5 Ebenen), Zirkelbezüge werden erkannt und ignoriert
- **SnippetsInstaller API:** Neue Klasse für AddOn-Entwickler, um eigene Daten programmatisch zu installieren/entfernen
  - `SnippetsInstaller::installTranslations()` – Übersetzungen mit Sprach-Codes installieren
  - `SnippetsInstaller::installSnippets()` – Snippets installieren
  - `SnippetsInstaller::installHtmlReplacements()` – HTML-Ersetzungen installieren
  - `SnippetsInstaller::installFromFile()` – Import aus JSON-Export-Datei
  - Drei Konflikt-Modi: `SKIP`, `OVERWRITE`, `FILL_EMPTY` (nur leere Sprachwerte ergänzen)
  - Deinstallations-Methoden: `removeTranslationsByPrefix()`, `removeSnippetsByPrefix()`, `removeHtmlReplacementsByPrefix()`
  - Existenz-Prüfung: `translationExists()`, `snippetExists()`
- **Import/Export für Übersetzungen:** Translations können jetzt exportiert und importiert werden
  - Portabler Export mit Sprach-Codes statt IDs – funktioniert zwischen verschiedenen REDAXO-Installationen
  - Kategorien werden per Name exportiert und beim Import automatisch angelegt
- **Zweistufiger Import:** Alle Importtypen (Snippets, HTML-Ersetzungen, Abkürzungen, Übersetzungen) zeigen jetzt eine Vorschau mit Konfliktanalyse, bevor der Import startet
  - Anzeige von Typ, Anzahl, neuen vs. bestehenden Einträgen, Export-Datum
  - Sprachmapping-Tabelle bei Übersetzungen: Zuordnung/Überspringen pro Sprache
  - Automatische Erkennung passender lokaler Sprachen per Code
- **Paginierung:** Bei vielen Keys wird die Übersetzungsliste paginiert (50 pro Seite)
- **Seiten-Icons:** Alle AddOn-Unterseiten haben jetzt passende FontAwesome-Icons

### Verbessert

- **Kategorie-Auswahl:** Selectpicker durch natives `<select>` mit slideToggle-Dropdown ersetzt – stabiler und ohne Bootstrap-Selectpicker-Abhängigkeit
- **Responsive Tabelle:** Sticky Key-Spalte, horizontales Scrollen bei vielen Sprachen
- **PJAX-Integration:** Alle Aktionen (Speichern, Löschen, Status-Toggle, Batch) nutzen PJAX für flüssige Navigation

## [1.2.0] – 2026-03-09

### Behoben

- **Import/Export (Import-Upload):** TypeError beim Dateiupload behoben; Import verarbeitet Upload-Feld wieder robust
- **Import/Export (HTML-Ersetzungen):** `scope_backend_request_pattern` wird nun korrekt mit exportiert/importiert
- **Backend-Scope-Matching:** Kombination aus Backend-Seiten und Backend-Request-Pattern wurde vereinheitlicht; bei gesetzten beiden Scopes reicht ein Treffer in einer Bedingung
- **Backend-Request-Pattern:** `key=value`-Abgleich unterstützt Teilstring-Matching für Parameterwerte (z. B. `page=content/edit` matcht auch `page=content/edit&article_id=1`)
- **Backend Edit-Kontexte:** HTML-Ersetzungen können im Edit-Kontext angewendet werden, formularnahe Bereiche bleiben geschützt

### Verbessert

- **Kategorien-UI:** Kategorie-Icons werden in Kategorienliste, Kategorie-Select im Snippet-Formular und Kategorie-Filter in der Übersicht angezeigt
- **HTML-Ersetzungsmaske:** Backend-Seiten-Auswahlhöhe begrenzt; zusätzliche Hilfetexte und Beispiele für Backend-Request-Pattern direkt in der Maske

### Dokumentation

- **README aktualisiert:** Verhalten von Backend-Seiten vs. Backend-Request-Pattern präzisiert (Kombination/Matching-Logik), neue Pattern-Beispiele ergänzt
- **Release-Version erhöht:** AddOn-Version auf `1.2.0` angehoben

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
