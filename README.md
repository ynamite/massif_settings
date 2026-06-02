# massif_settings für REDAXO 5

Das Addon liefert Einstellungsseiten für Stammdaten und erlaubt die einfache Erstellung von weiteren Backend-Einstellungen für REDAXO Installationen.
Weiterhin liefert das Addon über `massif_settings\Seo::getTags()` eine einfache Möglichkeit SEO-Tags in Templates zu generieren (`yrewrite` und `redaxo_url` kompatibel).

Einstellungen werden in der `rex_config`-Tabelle gespeichert und können über `rex_config::get('massif_settings', 'einstellung')` abgerufen werden.
Zudem bietet das Addon einige Utility-Funktionen um Einstellungen abzufragen und Platzhalter, für diese Variablen, in Templates zu ersetzen.

Ab Version `1.1.0` werden die Einstellungsfelder in einer YAML-Datei im Projekt-Datenverzeichnis konfiguriert. Beim ersten Aktivieren des Addons wird eine Default-Datei nach `redaxo/data/addons/massif_settings/fields.yml` kopiert. Diese Datei gehört dem Projekt und wird durch Addon-Updates nicht überschrieben.

## Voraussetzungen

- REDAXO 5.0 oder höher
- PHP 7.4 oder höher
- [massif Addon](https://github.com/ynamite/redaxo_massif) Addon Version 1.0.0 oder höher

## Eigene Felder und Seiten registrieren

### Empfohlen: `fields.yml` bearbeiten

Datei: `redaxo/data/addons/massif_settings/fields.yml`

```yaml
subpages:
  address:
    title: Stammdaten
    icon: fa fa-map-pin
    fields:
      - { name: firma, label: Name, notice: '{{address_firma}}' }
      - { name: phone, label: Telefon, formatter: phone, notice: '{{address_phone}}' }
      # neues Feld:
      - { name: phone_emergency, label: 'Notfall-Telefon', formatter: phone }
      # Default-Feld ausblenden:
      - { name: linkedin, active: false }

  # neue Subpage – erscheint automatisch im Backend-Menü
  newsletter:
    title: Newsletter
    icon: fa fa-envelope
    priority: 50
    fields:
      - { name: mailchimp_id, label: 'Mailchimp Listen-ID' }

placeholders:
  # reine Frontend-Platzhalter (kein Backend-Input)
  - { name: foo_token, package: foo_addon, key: token, formatter: url }
```

Unterstützte Feld-Keys: `name` (erforderlich), `label`, `type` (`text` (default), `textarea`, `checkbox`, `rex_media`), `options` (für Checkbox), `class`, `rows`, `style`, `data` (key-value), `attributes` (key-value), `notice` (Hinweistext, akzeptiert `{{platzhalter}}`), `active` (false versteckt das Feld), `default` (einmaliger Initialwert beim Bootstrap), `formatter` (`phone` | `fax` | `email` | `url`), `priority` (kleiner = früher, default `100`), `placeholder` (auf `false` setzen, um ein Feld nicht als `{{...}}` verfügbar zu machen), `placeholderAlias`.

### Erweitert: PHP-API für dynamische Fälle

Für Szenarien, die YAML nicht abbilden kann (Callable-Formatter, Resolver-Platzhalter, bedingte Registrierung), nutze den Extensionpoint `MASSIF_SETTINGS_REGISTER`:

```php
use Ynamite\MassifSettings\Registry;

rex_extension::register('MASSIF_SETTINGS_REGISTER', function () {
  // Feld mit eigenem Formatter
  Registry::field('address', [
    'name'      => 'phone_emergency',
    'label'     => 'Notfall-Telefon',
    'formatter' => fn ($v) => '<a href="tel:' . preg_replace('/\s+/', '', $v) . '">' . $v . '</a>',
  ]);

  // Platzhalter mit dynamischem Resolver
  Registry::placeholder('current_year', [
    'resolver' => fn () => (string) date('Y'),
  ]);

  // Default-Feld entfernen
  Registry::removeField('social', 'linkedin');
});
```

Verfügbare API: `Registry::subpage()`, `Registry::field()`, `Registry::placeholder()`, `Registry::removeSubpage()`, `Registry::removeField()`, `Registry::disableField()`, `Registry::removePlaceholder()`.

### Eigene Backend-Seite hinzufügen (außerhalb von `fields.yml`)

Reine Custom-Seiten ohne ConfigForm können wie zuvor über `PAGES_PREPARED` ergänzt werden:

```php
rex_extension::register('PAGES_PREPARED', function () {
  $pageObject = \rex_be_controller::getPageObject('massif_settings');
  $pageObject->addSubpage(new rex_be_page('test', 'Testpage'));
});
```

### Legacy-Extensionpoints

Werden weiterhin unterstützt, neue Integrationen sollten aber `MASSIF_SETTINGS_REGISTER` bzw. `fields.yml` verwenden:

- `MASSIF_CONFIG_FORM_FIELDS` (im Parent-Addon `massif`) – fügt Felder direkt in das ConfigForm ein. Wird intern weiterhin gefeuert.
- `MASSIF_SETTINGS_CUSTOM_FIELDS` – meldet `rex_config`-Keys als Platzhalter an. Wird beim Bootstrap automatisch in `Registry::placeholder()`-Aufrufe übersetzt.

---

## Lizenz

[The MIT License (MIT)](LICENSE.md)

## Credits

- Project Lead: [Yves Torres](https://github.com/ynamite)
