# Snippets AddOn / REDAXO CMS

Eine moderne Alternative zum xoutputfilter (Danke Andreas ❤️) AddOn und als Ergänzung zu Sprog (Danke Thomas ❤️). 
Snippets können Texte sein oder kleine Code-Schnipsel die global verwendet werden können. Die Ausgabe erfolgt über den Outputfilter. 

## Übersicht

Das **Snippets-AddOn** bietet zentrale Verwaltung von wiederverwendbaren Code-Fragmenten und automatische HTML-Manipulation mit PHP 8.4 DOM:

- **Snippets** – Wiederverwendbare HTML/PHP-Fragmente mit Parametern
- **String-Übersetzungen** – Mehrsprachige Key-Value-Übersetzungen (Sprog-Alternative) mit DeepL-Integration
- **Filter** – 26+ Filter für Textformatierung
- **HTML-Ersetzungen** – CSS-Selektoren, Regex und PHP-Callbacks
- **PHP-API** – `Snippets::get()`, `Snippets::apply()`, `SnippetsTranslate::get()` für PHP-Zugriff
- **Scope-Kontrolle** – Templates, Kategorien, URLs, Backend-Seiten
- **Berechtigungssystem** – Admin, Editor, Viewer Rollen

### Neu in Version 1.4.0

- **String-Übersetzungen:** Neues mehrsprachiges Übersetzungssystem – eine schlanke Sprog-Alternative für String-Übersetzungen, direkt im Snippets-AddOn
- **Inline-Bearbeitung:** Alle Sprachen in einer Tabelle, Click-to-Edit, sofortiges Speichern per AJAX
- **DeepL-Integration:** KI-Übersetzung per DeepL – einzeln oder als Batch für eine komplette Zielsprache
- **Nutzt WriteAssist-Token:** Kein separater API-Key nötig – DeepL-Key aus dem WriteAssist-AddOn wird automatisch verwendet
- **PHP-API:** `SnippetsTranslate::get('key')` für direkten Zugriff in Modulen, Templates und PHP-Code
- **Sprog-Import:** Bestehende Sprog-Wildcards können mit einem Klick übernommen werden
- **Paginierung:** Übersetzungsliste mit Seitennavigation bei vielen Keys
- **Seiten-Icons:** Alle Unterseiten haben passende FontAwesome-Icons

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

## String-Übersetzungen

Das Snippets-AddOn enthält ein eigenes mehrsprachiges Übersetzungssystem – eine schlanke Alternative zu Sprog, spezialisiert auf String-Übersetzungen.

### Funktionsweise

Übersetzungsschlüssel werden mit der Syntax `[[ key ]]` im Content platziert und per OUTPUT_FILTER automatisch durch den passenden Wert der aktuellen Sprache ersetzt.

```html
<h1>[[ nav.home ]]</h1>
<p>[[ footer.copyright ]]</p>
<a href="/kontakt">[[ btn.contact ]]</a>
```

### PHP-API für Module und Templates

Für direkten Zugriff in PHP – **ohne OUTPUT_FILTER** – steht die Service-Klasse zur Verfügung:

```php
use FriendsOfREDAXO\Snippets\Service\SnippetsTranslate;

echo SnippetsTranslate::get('nav.home');                       // aktuelle Sprache
echo SnippetsTranslate::get('nav.home', 2);                    // Sprach-ID 2
echo SnippetsTranslate::get('nav.home', null, 'Startseite');   // mit Fallback

// Platzhalter in beliebigem Content ersetzen
$html = SnippetsTranslate::replace($content, rex_clang::getCurrentId());
```

### Verschachtelte Snippets

Snippet-Werte können selbst wieder `[[ key ]]`-Platzhalter enthalten – diese werden **rekursiv aufgelöst** (max. 5 Ebenen).

**Beispiel:**

| Key | Wert |
|-----|------|
| `company.name` | `ACME GmbH` |
| `footer.copyright` | `© 2026 [[ company.name ]]` |
| `footer.full` | `[[ footer.copyright ]] – Alle Rechte vorbehalten` |

**Ergebnis** von `[[ footer.full ]]`:

```
© 2026 ACME GmbH – Alle Rechte vorbehalten
```

Das funktioniert sowohl im OUTPUT_FILTER als auch per PHP-API:

```php
// Verschachtelte Platzhalter werden automatisch aufgelöst
echo SnippetsTranslate::get('footer.full');
// → "© 2026 ACME GmbH – Alle Rechte vorbehalten"
```

> **Hinweis:** Zirkuläre Referenzen (A → B → A) werden nach 5 Durchläufen abgebrochen – der nicht auflösbare Platzhalter bleibt dann stehen.

### DeepL-Integration

Wenn das **WriteAssist**-AddOn installiert und ein DeepL-API-Key konfiguriert ist, stehen KI-Übersetzungen zur Verfügung:

- **Einzelübersetzung:** DeepL-Button (🔤) in jeder Sprachzelle – übersetzt den Quelltext und speichert sofort
- **Batch-Übersetzung:** Zielsprache wählen → alle leeren (oder alle) Strings werden serverseitig per DeepL übersetzt

Ein separater API-Key ist **nicht** nötig – der DeepL-Token aus WriteAssist wird automatisch verwendet.

### Sprog-Kompatibilität

- **Syntax:** Optional kann zusätzlich die `{{ key }}`-Syntax aktiviert werden (Einstellungen → „Sprog-Syntax unterstützen")
- **Import:** Bestehende Sprog-Wildcards können mit einem Klick in die Snippets-Übersetzungen importiert werden (Admin-Bereich)
- Das Sprog-AddOn wird dafür **nicht** benötigt – der Import liest direkt aus der `rex_sprog_wildcard`-Tabelle

### Sprachmapping

REDAXO-Sprachcodes werden automatisch auf DeepL-Codes gemappt (z. B. `de` → `DE`, `en` → `EN-GB`). Falls ein Code nicht erkannt wird, kann das Mapping in den Einstellungen manuell konfiguriert werden:

```
sl=SL
hr=HR
```

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

## PHP-API

### Snippets-Klasse

Die zentrale API-Klasse für den Zugriff auf Snippets.

```php
use FriendsOfREDAXO\Snippets\Snippets;

// Snippet abrufen
echo Snippets::get('footer_text');
echo Snippets::get('greeting', ['name' => 'Max']);

// Mit Filtern
echo Snippets::filtered('headline', [], 'upper');
echo Snippets::filtered('content', [], 'markdown|sanitize');

// Platzhalter in Text ersetzen
$html = '<h1>[[snippet:headline]]</h1>';
echo Snippets::apply($html);

// Existenz prüfen
if (Snippets::exists('special_offer')) {
    echo Snippets::get('special_offer');
}

// Mit Fallback
echo Snippets::getOr('headline', 'Willkommen');

// Beliebigen Text filtern
echo Snippets::filter($content, 'escape|truncate(100)');
```

### Methoden-Übersicht

| Methode | Beschreibung |
|---------|--------------|
| `Snippets::get($key, $params, $clangId)` | Snippet abrufen |
| `Snippets::filtered($key, $params, $filters, $clangId)` | Snippet mit Filtern |
| `Snippets::apply($text, $clangId, $context)` | Platzhalter ersetzen |
| `Snippets::exists($key)` | Existenz prüfen |
| `Snippets::getOr($key, $fallback, $params, $clangId)` | Mit Fallback |
| `Snippets::filter($content, $filters)` | Text filtern |

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
- **Backend-Request-Pattern**: Optionaler Filter auf konkrete Backend-Requests inkl. Query-Parameter

### Backend-Request-Pattern (Backend)

Mit diesem Feld lässt sich eine Regel sehr gezielt auf einzelne Backend-Requests begrenzen.

- **UND innerhalb einer Zeile**: Parameter mit `&` verknüpfen
- **ODER zwischen mehreren Varianten**: Jede Zeile ist ein eigenes Pattern
- **Alternativ ODER mit `||`** in einer Zeile
- **Ohne `=`**: Teilstring-Match auf die komplette Request-URL
- **Mit `key=value`**: Teilstring-Match auf den Parameterwert (nicht nur exakte Gleichheit)

Beispiele:

```text
page=content/edit&function=add
page=content/edit&function=edit
page=content/edit
func=delete
page=mediapool/media
```

oder in einer Zeile:

```text
page=content/edit&function=add || page=content/edit&function=edit
```

Hinweis: Die klassische Backend-Seiten-Auswahl und das Backend-Request-Pattern wirken zusammen. Wenn beide gesetzt sind, reicht ein Treffer in einer der beiden Bedingungen.

### Snippets in HTML-Ersetzungsinhalten

Standardmäßig werden Snippet-Platzhalter im Feld **Ersetzungs-Inhalt** nicht aufgelöst. 
Wenn gewünscht, kann dies in den Einstellungen aktiviert werden:

- **Snippets → Einstellungen → „Snippets in HTML-Ersetzungen erlauben“**

Dann werden Platzhalter wie `[[snippet:mein_key]]` im Ersetzungs-Inhalt vor dem Einfügen verarbeitet.

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

### SnippetsInstaller API (für AddOn-Entwickler)

Das Snippets-AddOn bietet eine **Installer-API**, mit der andere AddOns eigene Übersetzungen, Snippets und HTML-Ersetzungen programmatisch installieren, aktualisieren und entfernen können.

#### Schnellstart

```php
<?php
use FriendsOfREDAXO\Snippets\Service\SnippetsInstaller;

// In eurer install.php oder update.php:
if (rex_addon::get('snippets')->isAvailable()) {
    SnippetsInstaller::installTranslations([
        'my_addon.greeting' => ['de' => 'Hallo Welt', 'en' => 'Hello World'],
        'my_addon.farewell' => ['de' => 'Auf Wiedersehen', 'en' => 'Goodbye'],
    ]);
}
```

#### Konflikt-Modi

Jede `install*`-Methode akzeptiert einen `$conflictMode`-Parameter:

| Modus | Konstante | Beschreibung |
|-------|-----------|--------------|
| **Skip** | `SnippetsInstaller::SKIP` | Bestehende Einträge überspringen (Standard) |
| **Overwrite** | `SnippetsInstaller::OVERWRITE` | Bestehende Einträge komplett überschreiben |
| **Fill Empty** | `SnippetsInstaller::FILL_EMPTY` | Nur leere Sprachwerte füllen, vorhandene Werte behalten (nur Translations) |

`FILL_EMPTY` ist ideal für AddOn-Updates: Neue Sprachen werden ergänzt, ohne vom Benutzer angepasste Texte zu überschreiben.

#### Übersetzungen installieren

```php
<?php
use FriendsOfREDAXO\Snippets\Service\SnippetsInstaller;

// Einfach: Key => [Sprach-Code => Wert]
$result = SnippetsInstaller::installTranslations([
    'shop.cart.empty' => ['de' => 'Warenkorb ist leer', 'en' => 'Cart is empty'],
    'shop.cart.add'   => ['de' => 'In den Warenkorb', 'en' => 'Add to cart'],
    'shop.cart.total'  => ['de' => 'Gesamtsumme', 'en' => 'Total'],
], SnippetsInstaller::FILL_EMPTY);
// $result = ['imported' => 3, 'skipped' => 0, 'updated' => 0]

// Mit Kategorie und Icon
SnippetsInstaller::installTranslations(
    [
        'shop.nav.home'  => ['de' => 'Startseite', 'en' => 'Home'],
        'shop.nav.about' => ['de' => 'Über uns', 'en' => 'About'],
    ],
    SnippetsInstaller::SKIP,
    'Shop',            // Kategoriename (wird erstellt falls nötig)
    'fa-shopping-cart', // Icon für neue Kategorie
);
```

> **Wichtig:** Sprachen werden per Code (`de`, `en`, `fr`, …) angegeben – nicht per ID. Dadurch funktioniert der Code in jeder REDAXO-Installation unabhängig von der Sprach-Konfiguration.

#### Snippets installieren

```php
<?php
use FriendsOfREDAXO\Snippets\Service\SnippetsInstaller;

$result = SnippetsInstaller::installSnippets([
    'my_addon.footer' => [
        'content' => '<div class="footer">© {{ year }}</div>',
        'title' => 'Footer',
        'content_type' => 'html',    // html, text, php
        'context' => 'frontend',     // frontend, backend, both
    ],
    'my_addon.copyright' => [
        'content' => '© 2025 Meine Firma',
        'title' => 'Copyright',
    ],
], SnippetsInstaller::SKIP);
```

#### HTML-Ersetzungen installieren

```php
<?php
use FriendsOfREDAXO\Snippets\Service\SnippetsInstaller;

$result = SnippetsInstaller::installHtmlReplacements([
    'my_addon.lazy_images' => [
        'type' => 'css_selector',
        'search_value' => 'img:not([loading])',
        'replacement' => 'loading="lazy"',
        'position' => 'add_attribute',
        'scope_context' => 'frontend',
        'description' => 'Lazy Loading für alle Bilder',
        'priority' => 10,
    ],
], SnippetsInstaller::SKIP);
```

#### Aus JSON-Datei installieren

```php
<?php
use FriendsOfREDAXO\Snippets\Service\SnippetsInstaller;

// Lädt eine zuvor exportierte JSON-Datei
$result = SnippetsInstaller::installFromFile(
    rex_path::addon('my_addon', 'data/translations.json'),
    SnippetsInstaller::FILL_EMPTY,
);
```

#### Einträge entfernen (Deinstallation)

```php
<?php
// In eurer uninstall.php:
use FriendsOfREDAXO\Snippets\Service\SnippetsInstaller;

if (rex_addon::get('snippets')->isAvailable()) {
    // Per Prefix: Entfernt alle Einträge die mit 'my_addon.' beginnen
    SnippetsInstaller::removeTranslationsByPrefix('my_addon.');
    SnippetsInstaller::removeSnippetsByPrefix('my_addon.');
    SnippetsInstaller::removeHtmlReplacementsByPrefix('my_addon.');

    // Oder einzelne Keys entfernen
    SnippetsInstaller::removeTranslationsByKeys([
        'my_addon.greeting',
        'my_addon.farewell',
    ]);
}
```

#### Existenz prüfen

```php
<?php
use FriendsOfREDAXO\Snippets\Service\SnippetsInstaller;

if (SnippetsInstaller::translationExists('my_addon.greeting')) {
    // Key existiert bereits
}

if (SnippetsInstaller::snippetExists('my_addon.footer')) {
    // Snippet existiert bereits
}
```

#### Best Practices für AddOn-Entwickler

1. **Keys mit AddOn-Name prefixen**: `my_addon.section.key` – vermeidet Konflikte mit anderen AddOns
2. **`FILL_EMPTY` in `update.php`** verwenden – ergänzt neue Sprachen ohne Benutzer-Anpassungen zu überschreiben
3. **`SKIP` in `install.php`** verwenden – überschreibt keine vorhandenen Daten bei Neuinstallation
4. **`snippets`-Verfügbarkeit prüfen** vor dem Aufruf: `if (rex_addon::get('snippets')->isAvailable())`
5. **Aufräumen in `uninstall.php`** mit `removeByPrefix()` – hinterlässt keine verwaisten Daten
6. **Sprach-Codes statt IDs** verwenden – macht den Code portabel

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
- **Debug-Modus**: Erweiterte Fehlerausgabe

### TinyMCE-Integration

Das AddOn integriert sich nahtlos in den TinyMCE-Editor (AddOn `tinymce` ab Version 5).

#### Installation & Aktivierung

Damit der Snippet-Button im Editor erscheint, muss das TinyMCE-Profil angepasst werden:

1. Gehe in das **TinyMCE AddOn** -> **Profile**.
2. Wähle das gewünschte Profil (z.B. `default`) zum Bearbeiten aus.
3. Füge im Feld **Plugins** den Eintrag `redaxo_snippets` hinzu.
   Beispiel: `... image link media table redaxo_snippets ...`
4. Füge im Feld **Toolbar** den Eintrag `redaxo_snippets` an der gewünschten Stelle hinzu.
   Beispiel: `... | link unlink | redaxo_snippets | ...`
5. Speichere das Profil.

#### Funktionsweise

- Im Editor erscheint nun ein **Snippet-Icon** (Code-Symbol) in der Toolbar.
- Klick darauf öffnet einen Dialog mit allen verfügbaren Snippets.
- Wähle ein Snippet aus der Liste, um den Platzhalter `[[snippet:key]]` an der Cursor-Position einzufügen.
- Wenn das Snippet Parameter unterstützt, können diese im Platzhalter ergänzt werden, z.B. `[[snippet:key|param1=wert]]`.

> **Hinweis:** Nur Benutzer mit Zugriff auf das Snippets-AddOn können die Snippet-Liste im Editor sehen.

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

### Backend-Regel greift nicht wie erwartet

1. **Backend-Seite prüfen**: Bei Unterseiten ggf. den übergeordneten Key oder die konkrete Unterseite wählen
2. **Request-Pattern prüfen**: Parameternamen müssen stimmen, z. B. `function=add` vs. `func=add` (Werte dürfen Teilstrings sein)
3. **Mehrere URLs**: Je URL eine eigene Zeile oder mit `||` trennen
4. **Kombinationslogik beachten**: Bei gesetzten Backend-Seiten und Request-Pattern reicht ein Treffer in einer Bedingung

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
| `rex_snippets_translation` | Übersetzungen (Snippet-Werte pro Sprache) |
| `rex_snippets_category` | Kategorien |
| `rex_snippets_html_replacement` | HTML-Ersetzungen |
| `rex_snippets_log` | Audit-Log |
| `rex_snippets_string` | String-Übersetzungen (Keys, Kategorie, Status) |
| `rex_snippets_string_value` | Übersetzungswerte pro Key und Sprache |

---

## Lizenz

MIT License

Entwickelt von der REDAXO-Community

Basiert auf Ideen vom AddOn XOutputFilter, Danke an: [Andreas Eberhard / aesoft.de](http://aesoft.de) und [Peter Bickel / polarpixel.de](http://polarpixel.de)

