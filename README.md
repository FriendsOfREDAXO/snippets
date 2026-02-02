# 🐣 Snippets AddOn

Eine moderne Alternative zum xoutputfilter AddOn für REDAXO CMS.

## Übersicht

Das **Snippets-AddOn** bietet zentrale Verwaltung von wiederverwendbaren Code-Fragmenten und automatische HTML-Manipulation mit PHP 8.4 DOM:

- **Snippets** – Wiederverwendbare HTML/PHP-Fragmente mit Parametern
- **Filter** – 26+ Filter für Textformatierung
- **HTML-Ersetzungen** – CSS-Selektoren, Regex und PHP-Callbacks
- **Abkürzungen** – Automatische `<abbr>`-Tags für Akronyme
- **Helper-Funktionen** – `snippet()`, `snippet_apply()` für PHP-Zugriff
- **Scope-Kontrolle** – Templates, Kategorien, URLs, Backend-Seiten
- **Berechtigungssystem** – Admin, Editor, Viewer Rollen

---

## Installation

1. AddOn im Ordner `/redaxo/src/addons/snippets` installieren
2. Im Backend unter **AddOns** aktivieren
3. Berechtigungen für Benutzer einrichten (optional)

---

## Snippets

### Syntax

```
[[snippet:key_name]]
[[snippet:key_name|param1=wert1|param2=wert2]]
[[snippet:key_name|upper|truncate(100)]]
[[snippet:key_name|filter|param=wert]]
```

### Beispiel: Einfaches Snippet

**Snippet erstellen:**
- Key: `footer_copyright`
- Typ: `html`
- Inhalt: `© 2026 Meine Firma GmbH`

**Verwendung:**
```html
<footer>
    [[snippet:footer_copyright]]
</footer>
```

**Ausgabe:**
```html
<footer>
    © 2026 Meine Firma GmbH
</footer>
```

### Beispiel: Snippet mit Parametern

**Snippet erstellen:**
- Key: `alert`
- Typ: `php`
- Inhalt:

```php
<?php
$typ = $SNIPPET_PARAMS['typ'] ?? 'info';
$text = $SNIPPET_PARAMS['text'] ?? '';
$titel = $SNIPPET_PARAMS['titel'] ?? '';

$icons = [
    'info' => 'fa-info-circle',
    'success' => 'fa-check-circle',
    'warning' => 'fa-exclamation-triangle',
    'danger' => 'fa-times-circle'
];
$icon = $icons[$typ] ?? 'fa-info-circle';
?>
<div class="alert alert-<?= htmlspecialchars($typ) ?>">
    <?php if ($titel): ?>
        <h4><i class="fa <?= $icon ?>"></i> <?= htmlspecialchars($titel) ?></h4>
    <?php endif; ?>
    <p><?= htmlspecialchars($text) ?></p>
</div>
```

**Verwendung:**
```html
[[snippet:alert|typ=success|titel=Gespeichert!|text=Ihre Daten wurden erfolgreich gespeichert.]]
```

**Ausgabe:**
```html
<div class="alert alert-success">
    <h4><i class="fa fa-check-circle"></i> Gespeichert!</h4>
    <p>Ihre Daten wurden erfolgreich gespeichert.</p>
</div>
```

### Beispiel: Kontakt-Box

**Snippet erstellen:**
- Key: `kontakt`
- Typ: `php`
- Inhalt:

```php
<?php
$name = $SNIPPET_PARAMS['name'] ?? '';
$position = $SNIPPET_PARAMS['position'] ?? '';
$tel = $SNIPPET_PARAMS['tel'] ?? '';
$email = $SNIPPET_PARAMS['email'] ?? '';
$foto = $SNIPPET_PARAMS['foto'] ?? '';
?>
<div class="kontaktbox">
    <?php if ($foto): ?>
        <img src="<?= rex_url::media($foto) ?>" alt="<?= htmlspecialchars($name) ?>">
    <?php endif; ?>
    <h4><?= htmlspecialchars($name) ?></h4>
    <?php if ($position): ?>
        <p class="position"><?= htmlspecialchars($position) ?></p>
    <?php endif; ?>
    <?php if ($tel): ?>
        <p><i class="fa fa-phone"></i> <a href="tel:<?= htmlspecialchars($tel) ?>"><?= htmlspecialchars($tel) ?></a></p>
    <?php endif; ?>
    <?php if ($email): ?>
        <p><i class="fa fa-envelope"></i> <a href="mailto:<?= htmlspecialchars($email) ?>"><?= htmlspecialchars($email) ?></a></p>
    <?php endif; ?>
</div>
```

**Verwendung:**
```html
[[snippet:kontakt|name=Max Mustermann|position=Geschäftsführer|tel=+49 123 456789|email=max@firma.de|foto=max.jpg]]
```

### Verfügbare Variablen in PHP-Snippets

```php
<?php
// Array mit allen übergebenen Parametern
$wert = $SNIPPET_PARAMS['mein_param'] ?? 'standard';

// Der Snippet-Key als String
echo $SNIPPET_KEY; // z.B. "kontakt"

// Das Snippet-Objekt mit allen Eigenschaften
$snippet->getTitle();
$snippet->getDescription();
$snippet->getId();
```

### Content-Typen

| Typ | Beschreibung |
|-----|--------------|
| `html` | HTML-Code, wird direkt ausgegeben |
| `text` | Reiner Text, wird escaped |
| `php` | PHP-Code mit Zugriff auf `$SNIPPET_PARAMS` (nur Admins) |

---

## Filter

Filter formatieren die Snippet-Ausgabe. Sie werden mit `|` notiert und können kombiniert werden.

### Verfügbare Filter

| Filter | Beschreibung | Beispiel |
|--------|--------------|----------|
| `upper` | GROSSBUCHSTABEN | `[[snippet:key\|upper]]` |
| `lower` | kleinbuchstaben | `[[snippet:key\|lower]]` |
| `title` | Title Case | `[[snippet:key\|title]]` |
| `capitalize` | Erster Buchstabe groß | `[[snippet:key\|capitalize]]` |
| `trim` | Whitespace entfernen | `[[snippet:key\|trim]]` |
| `truncate(n,suffix)` | Auf n Zeichen kürzen | `[[snippet:key\|truncate(100,...)]]` |
| `limit(n,suffix)` | Alias für truncate | `[[snippet:key\|limit(50)]]` |
| `words(n,suffix)` | Auf n Wörter kürzen | `[[snippet:key\|words(20)]]` |
| `nl2br` | Zeilenumbrüche → `<br>` | `[[snippet:key\|nl2br]]` |
| `raw` | Keine nl2br-Konvertierung | `[[snippet:key\|raw]]` |
| `markdown` | Markdown → HTML | `[[snippet:key\|markdown]]` |
| `strip_tags(allowed)` | HTML-Tags entfernen | `[[snippet:key\|strip_tags(<p><br>)]]` |
| `escape` | HTML-Entities escapen | `[[snippet:key\|escape]]` |
| `sanitize` | HTML sicher machen | `[[snippet:key\|sanitize]]` |
| `format(args...)` | sprintf-Formatierung | `[[snippet:key\|format(5,Baum)]]` |
| `default(value)` | Fallback wenn leer | `[[snippet:key\|default(Kein Inhalt)]]` |
| `replace(s,r)` | Text ersetzen | `[[snippet:key\|replace(alt,neu)]]` |
| `prefix(text)` | Text voranstellen | `[[snippet:key\|prefix(>>> )]]` |
| `suffix(text)` | Text anhängen | `[[snippet:key\|suffix( <<<)]]` |
| `wrap(b,a)` | Text umschließen | `[[snippet:key\|wrap(<em>,</em>)]]` |
| `date(format)` | Datum formatieren | `[[snippet:key\|date(d.m.Y)]]` |
| `intldate(format)` | Internationales Datum | `[[snippet:key\|intldate(LONG)]]` |
| `number(d,dp,ts)` | Zahl formatieren | `[[snippet:key\|number(2,",",".")]]` |
| `bytes(precision)` | Bytes formatieren | `[[snippet:key\|bytes(2)]]` |
| `slug` | URL-freundlich | `[[snippet:key\|slug]]` |
| `url` | Als klickbarer Link | `[[snippet:key\|url]]` |
| `email` | Als klickbare E-Mail | `[[snippet:key\|email]]` |
| `widont` | Keine Einzel-Wörter am Ende | `[[snippet:key\|widont]]` |
| `json` | Als JSON ausgeben | `[[snippet:key\|json]]` |
| `base64` | Base64-kodiert | `[[snippet:key\|base64]]` |

### Beispiele

```html
<!-- Überschrift in Großbuchstaben -->
[[snippet:headline|upper]]

<!-- Teaser: max 150 Zeichen, ohne HTML -->
[[snippet:description|strip_tags|truncate(150,...)]]

<!-- Markdown-Content sicher ausgeben -->
[[snippet:content|markdown|sanitize]]

<!-- Preis formatieren -->
[[snippet:price|number(2,",",".")]] €

<!-- E-Mail als Link -->
[[snippet:contact_email|email]]

<!-- Filter + Parameter kombiniert -->
[[snippet:teaser|upper|max_length=100|truncate(100)]]
```

---

## Helper-Funktionen

Globale PHP-Funktionen für einfachen Zugriff auf Snippets.

### snippet()

Rendert ein einzelnes Snippet.

```php
// Einfach
echo snippet('footer_text');

// Mit Parametern
echo snippet('greeting', ['name' => 'Max', 'company' => 'ACME']);

// Für andere Sprache (clang_id = 2)
echo snippet('headline', [], 2);

// Im Backend-Context
echo snippet('admin_notice', [], null, 'backend');
```

### snippet_apply()

Ersetzt alle Snippet-Platzhalter in einem Text.

```php
$html = '<h1>[[snippet:headline]]</h1><p>[[snippet:intro|upper]]</p>';
echo snippet_apply($html);

// E-Mail-Template laden und Snippets ersetzen
$template = rex_file::get(rex_path::addon('myAddon', 'templates/mail.html'));
echo snippet_apply($template);
```

### snippet_filtered()

Rendert ein Snippet mit Filtern.

```php
// Mit einzelnem Filter
echo snippet_filtered('headline', [], 'upper');

// Mit mehreren Filtern
echo snippet_filtered('description', [], ['truncate(100)', 'strip_tags']);

// Als Pipe-String
echo snippet_filtered('content', [], 'markdown|sanitize');
```

### snippet_exists()

Prüft ob ein Snippet existiert und aktiv ist.

```php
if (snippet_exists('special_offer')) {
    echo snippet('special_offer');
}
```

### snippet_or()

Gibt ein Snippet zurück oder einen Fallback-Wert.

```php
echo snippet_or('headline', 'Willkommen');
echo snippet_or('greeting', 'Hallo!', ['name' => 'Gast']);
```

### snippet_filter()

Wendet Filter auf beliebigen Text an.

```php
echo snippet_filter($userInput, 'escape|truncate(100)');
echo snippet_filter($content, ['markdown', 'sanitize']);
```

---

## HTML-Ersetzungen

Automatische Manipulation des HTML-Outputs über CSS-Selektoren, Regex oder PHP-Callbacks.

### Ersetzungs-Typen

| Typ | Beschreibung |
|-----|--------------|
| `css` | CSS-Selektoren zum Finden und Manipulieren von Elementen |
| `regex` | Reguläre Ausdrücke für Pattern-basierte Ersetzung |
| `callback` | PHP-Funktion für komplexe Logik |

### Beispiel: Externe Links kennzeichnen

**Regel erstellen:**
- Typ: CSS-Selektor
- Suchwert: `a[href^="http"]:not([href*="meine-domain.de"])`
- Position: Am Ende einfügen
- Ersetzung: `<i class="fa fa-external-link"></i>`

**Vorher:**
```html
<a href="https://example.com">Mehr Info</a>
```

**Nachher:**
```html
<a href="https://example.com">Mehr Info <i class="fa fa-external-link"></i></a>
```

### Beispiel: Lazy-Loading für Bilder

**Regel erstellen:**
- Typ: CSS-Selektor
- Suchwert: `img:not([loading])`
- Position: Attribut setzen
- Attribut: `loading`
- Wert: `lazy`

**Vorher:**
```html
<img src="bild.jpg" alt="Beispiel">
```

**Nachher:**
```html
<img src="bild.jpg" alt="Beispiel" loading="lazy">
```

### Beispiel: Tabellen responsiv machen

**Regel erstellen:**
- Typ: CSS-Selektor
- Suchwert: `table:not(.no-wrap)`
- Position: Umschließen
- Ersetzung: `<div class="table-responsive">|</div>`

**Vorher:**
```html
<table>...</table>
```

**Nachher:**
```html
<div class="table-responsive"><table>...</table></div>
```

### Beispiel: Telefonnummern verlinken (Regex)

**Regel erstellen:**
- Typ: Regex
- Pattern: `/(\+49[\d\s\-]+)/`
- Ersetzung: `<a href="tel:$1">$1</a>`

**Vorher:**
```html
<p>Rufen Sie uns an: +49 123 456789</p>
```

**Nachher:**
```html
<p>Rufen Sie uns an: <a href="tel:+49 123 456789">+49 123 456789</a></p>
```

### Beispiel: PHP-Callback

**Regel erstellen:**
- Typ: PHP Callback
- Suchwert: `{{YEAR}}`
- Ersetzung: `FriendsOfREDAXO\MeinAddon\Replacer::currentYear`

**Callback-Klasse:**
```php
<?php
namespace FriendsOfREDAXO\MeinAddon;

use FriendsOfREDAXO\Snippets\Domain\HtmlReplacement;

class Replacer
{
    public static function currentYear(string $search, string $content, HtmlReplacement $replacement): string
    {
        return str_replace($search, date('Y'), $content);
    }
}
```

### CSS-Selektor Positionen

| Position | Beschreibung |
|----------|--------------|
| Ersetzen | Ersetzt das gesamte Element |
| Davor einfügen | Fügt vor dem Element ein |
| Danach einfügen | Fügt nach dem Element ein |
| Am Anfang einfügen | Fügt innerhalb am Anfang ein (prepend) |
| Am Ende einfügen | Fügt innerhalb am Ende ein (append) |

### Scope-Kontrolle

Jede HTML-Ersetzung kann eingeschränkt werden auf:

- **Context**: Frontend, Backend oder beides
- **Templates**: Nur bestimmte Templates (Frontend)
- **Kategorien**: Nur bestimmte Kategorien mit/ohne Unterkategorien (Frontend)
- **URL-Pattern**: Regex für URL-Matching (Frontend)
- **Backend-Seiten**: Nur bestimmte Backend-Seiten

---

## Abkürzungen

Automatische Kennzeichnung von Abkürzungen und Akronymen mit `<abbr>`-Tags.

### Beispiel

**Abkürzung erstellen:**
- Abkürzung: `HTML`
- Ausschreibung: `HyperText Markup Language`
- Groß-/Kleinschreibung beachten: ✅
- Nur ganze Wörter: ✅

**Vorher:**
```html
<p>HTML und CSS sind die Grundlagen des Webs.</p>
```

**Nachher:**
```html
<p><abbr title="HyperText Markup Language">HTML</abbr> und 
<abbr title="Cascading Style Sheets">CSS</abbr> sind die Grundlagen des Webs.</p>
```

### Optionen

| Option | Beschreibung |
|--------|--------------|
| Groß-/Kleinschreibung | Nur exakte Schreibweise ersetzen |
| Nur ganze Wörter | Keine Teilwörter ersetzen (z.B. "API" nicht in "MyAPIClass") |
| Sprache | Nur für bestimmte REDAXO-Sprache |
| Priorität | Verarbeitungsreihenfolge (höher = früher) |

### Ausschluss-Selektoren

In den Einstellungen können Bereiche definiert werden, die von der Abkürzungs-Ersetzung ausgenommen sind:

```
a
nav
code
pre
.no-abbr
```

---

## Programmierung

### SnippetService API

```php
<?php
use FriendsOfREDAXO\Snippets\Service\SnippetService;

// Snippet rendern
echo SnippetService::render(
    'kontakt',                     // Snippet-Key
    'frontend',                    // Context
    rex_clang::getCurrentId(),     // Sprach-ID
    ['name' => 'Max']              // Parameter
);
```

### ReplacementService API

```php
<?php
use FriendsOfREDAXO\Snippets\Service\ReplacementService;

$content = 'Hallo [[snippet:kontakt|name=Max]]';

$service = new ReplacementService();
$ersetzt = $service->replace($content, [
    'context' => 'frontend',
    'clang_id' => rex_clang::getCurrentId()
]);

echo $ersetzt;
```

### HtmlReplacementService API

```php
<?php
use FriendsOfREDAXO\Snippets\Service\HtmlReplacementService;

$content = '<h1>Überschrift</h1><p>Text</p>';
$processed = HtmlReplacementService::process($content, 'frontend');

echo $processed;
```

### FilterService API

```php
<?php
use FriendsOfREDAXO\Snippets\Service\FilterService;

$text = 'Mein langer Text hier...';
$filtered = FilterService::apply($text, [
    ['name' => 'truncate', 'args' => ['50', '...']],
    ['name' => 'upper', 'args' => []]
]);

echo $filtered;
```

---

## Berechtigungen

| Rolle | Rechte |
|-------|--------|
| `snippets[admin]` | Vollzugriff, PHP-Snippets bearbeiten, Einstellungen |
| `snippets[editor]` | HTML/Text-Snippets erstellen und bearbeiten |
| `snippets[viewer]` | Nur lesen |

---

## Einstellungen

### Allgemein

- **Snippet-Ersetzung aktiv**: Frontend/Backend aktivieren
- **HTML-Ersetzungen aktiv**: Frontend/Backend aktivieren
- **Abkürzungen aktiv**: Frontend/Backend aktivieren
- **Debug-Modus**: Erweiterte Fehlerausgabe

### Sprog-Integration

Das Snippets-AddOn ist vollständig mit **Sprog** kombinierbar. Beide AddOns ergänzen sich:

- **Sprog** für mehrsprachige Platzhalter und Übersetzungen
- **Snippets** für wiederverwendbare Code-Fragmente mit Parametern

Falls Sprog installiert ist, können Sprog-Wildcards direkt im Content verwendet werden:

```html
<!-- Sprog für Übersetzungen -->
{{ firmenname }}
{{ kontakt_email }}

<!-- Snippets für Komponenten -->
[[snippet:kontaktbox|name={{ ansprechpartner }}|tel={{ telefon }}]]
```

---

## Sicherheit

### Context-Detection

Snippets werden **nicht ersetzt** in:
- Struktur-Edit-Modus
- YForm Table Manager Edit
- Module-Edit / Template-Edit
- AJAX Edit-Requests
- Snippets-AddOn Backend-Seiten

### PHP-Snippets

- Nur für `snippets[admin]`-Benutzer
- Alle Ausführungen werden protokolliert
- Fehler werden im Debug-Modus sichtbar
- PHP-Callbacks nur aus erlaubten Namespaces (`FriendsOfREDAXO\`, `rex_`)

---

## Troubleshooting

### Snippet wird nicht ersetzt

1. **Status prüfen**: Snippet muss aktiv sein
2. **Context prüfen**: Frontend-Snippet nicht im Backend sichtbar
3. **Key prüfen**: Groß-/Kleinschreibung beachten
4. **Edit-Modus**: Snippets werden beim Bearbeiten nicht ersetzt

### HTML-Ersetzung funktioniert nicht

1. **Status prüfen**: Regel muss aktiv sein
2. **Scope prüfen**: Template/Kategorie/URL stimmen?
3. **Selektor testen**: CSS-Selektor korrekt?
4. **Debug aktivieren**: In Einstellungen aktivieren

### PHP-Fehler anzeigen

Debug-Modus aktivieren → Fehler erscheinen als HTML-Kommentare:
```html
<!-- PHP Snippet Error (mein_snippet): Undefined variable $test -->
```

---

## Datenbanktabellen

| Tabelle | Beschreibung |
|---------|--------------|
| `rex_snippets_snippet` | Snippets |
| `rex_snippets_translation` | Übersetzungen |
| `rex_snippets_category` | Kategorien |
| `rex_snippets_html_replacement` | HTML-Ersetzungen |
| `rex_snippets_abbreviation` | Abkürzungen |
| `rex_snippets_log` | Audit-Log |

---

## Lizenz

MIT License

Entwickelt von der REDAXO-Community
