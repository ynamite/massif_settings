# massif_settings für REDAXO 5

Das Addon liefert Einstellungsseiten für Stammdaten und erlaubt die einfache Erstellung von weiteren Backend-Einstellungen für REDAXO Installationen.
Weiterhin liefert das Addon über `massif_settings\Seo::getTags()` eine einfache Möglichkeit SEO-Tags in Templates zu generieren (`yrewrite` und `redaxo_url` kompatibel).

Einstellungen werden in der rex_config-Tabelle gespeichert und können über rex_config::get('massif_settings', 'einstellung') abgerufen werden.
Zudem bietet das Addon einige Utility-Funktionen um Einstellungen abzufragen und Platzhalter, für diese Variablen, in Templates zu ersetzen.

Die Eingabefelder für die Einstellungen werden in der package.yml Datei des Addons definiert.
Das Addon erstellt automatisch eine Seite im Backend mit den definierten Eingabefeldern.

Es können auch eigene Seiten und Felder über Extensionpoints hinzugefügt werden.

## Voraussetzungen

- REDAXO 5.0 oder höher
- PHP 7.4 oder höher
- [massif Addon](https://github.com/ynamite/redaxo_massif) Addon Version 1.0.0 oder höher

## Eigene Seite hinzufügen

```php
  rex_extension::register('PAGES_PREPARED', function (rex_extension_point $ep) {
    /** @var rex_be_page_main $pageObject */
    $pageObject = \rex_be_controller::getPageObject('massif_settings');
    $newPage = (new rex_be_page('test', 'Testpage'));
    $pageObject->addSubpage($newPage);
  });
```

## Eigene Felder hinzufügen

```php
  rex_extension::register('MASSIF_CONFIG_FORM_FIELDS', function (rex_extension_point $ep) {
    $fields = $ep->getSubject();
    $fields[] = [
      'name' => 'feld_name',
      'label' => 'Feld Label',
      'type' => 'text', // text, textarea, rex_media
      'class' => 'form-control',
      'active' => true, // false = ausgeblendet
      'rows' => 5, // nur für textarea
      'style' => 'height: 100px;',
      'notice' => 'Dies ist ein neu hinzugefügtes Feld über eine Extension.', // Hinweistext
      'data' => [ // data-Attribute
        'foo' => 'bar',
        'baz' => 'qux',
      ],
      'formatter' => 'email', // Formatierung (phone, fax, email, url), wenn gesetzt wird zusätzlich ein feld_name_formatted Eintrag mit dem formatierten Wert erstellt
    ];
    return $fields;
  });
```

## Eigene Felder im Frontend automatisch ersetzen und für Utils verfügbar machen:

```php
rex_extension::register('MASSIF_SETTINGS_CUSTOM_FIELDS', function (rex_extension_point $ep) {
  $fields = $ep->getSubject();
  // einfache Variante:
  $fields = [
    ...$fields,
    ...[
      'feld_name'
    ]
  ];
  // oder so (auch beide Varianten kombinierbar):
  $fields = [
    ...$fields,
    ...[
      ['name' => 'feld_name'],
      ['name' => 'feld_name_email', 'formatter' => 'email'],
      ['name' => 'feld_name_phone', 'formatter' => 'phone']
    ]
  ];
  $ep->setSubject($fields);
}, rex_extension::EARLY);
```

---

## Lizenz

[The MIT License (MIT)](LICENSE.md)

## Credits

- Project Lead: [Yves Torres](https://github.com/ynamite)
