# Clear Content

Clear Content löscht gezielt Inhalte aus einer bestehenden REDAXO-Installation. Das Add-on ist für Entwicklungs-, Demo- und Migrationssysteme gedacht, in denen vorhandene Inhalte kontrolliert entfernt werden sollen.

<div class="alert alert-danger" role="alert" style="display: flex; align-items: center; gap: 18px; padding: 20px 24px; color: #fff; background: #d9534f; border-color: #d43f3a; font-size: 16px; line-height: 1.5;">
    <span aria-hidden="true" style="flex: 0 0 auto; font-size: 38px; line-height: 1;">⚠</span>
    <div><strong style="font-size: 18px;">Achtung:</strong> Löschvorgänge sind dauerhaft. Vor der Ausführung sollte ein aktuelles Datenbank- und Medienbackup vorliegen. Löschaktionen werden nie direkt ausgeführt: Zuerst erscheint immer ein Testlauf mit dem Kontext und der Anzahl der betroffenen Datensätze.</div>
</div>

## Funktionen

- alle aktuellen und historischen Slices in frei auswählbaren Sprachen löschen
- alle aktuellen und historischen Slices mehrerer ausgewählter Artikel in den ausgewählten Sprachen löschen; Artikel und andere Sprachen bleiben erhalten
- alle Slices frei auswählbarer Module in sämtlichen Artikeln und Sprachen löschen; verschachtelte Gridblock-Inhalte und die Slice-Historie werden berücksichtigt, die Module selbst bleiben erhalten
- mehrere ausgewählte Kategorien sprachübergreifend einschließlich Artikeln, Unterstruktur und Slice-Historie löschen
- normale Artikel direkt in mehreren ausgewählten Kategorien sprachübergreifend einschließlich Slice-Historie löschen, wahlweise nach dem letzten Änderungsdatum aller Sprachen; geschützte und Kategorie-Startartikel bleiben erhalten
- vorhandene Developer-Synchronisationsdateien und bei installiertem Theme-Add-on automatisch auch `theme/private/redaxo` löschen, ohne Synchronisationseinstellungen zu verändern
- ausgewählte Medienkategorien löschen; ihre Medien und nicht ausgewählte Unterkategorien werden an die nächste erhaltene übergeordnete Kategorie verschoben
- alle Medien mehrerer ausgewählter Medienkategorien einschließlich ihrer Dateien löschen; die Kategorien bleiben erhalten
- Inhalte ausgewählter YForm-TableManager-Tabellen einschließlich zugehöriger YForm-Historieneinträge löschen; Schema, Tabellenkonfiguration und Relationsfelder bleiben erhalten
- ausgewählte YForm-Tabellen vollständig entfernen, einschließlich Schema, TableManager-Konfiguration, Historie und eindeutig zugehöriger Relationsreste

Die Funktionen sind im REDAXO-Backend auf drei eigenständige Tabs verteilt: **Inhalte löschen**, **Medien löschen** und **Tabelleninhalte löschen**.

Das Löschen der gesamten Seitenstruktur ist unabhängig von der Sprachauswahl und wirkt in allen Sprachen. Der Website-Startartikel, der Fehlerartikel, deren Inhalte und gegebenenfalls notwendige Elternkategorien bleiben erhalten. Eine einzelne Kategorie, die einen dieser Systemartikel enthält, kann nicht vollständig gelöscht werden. Löschaktionen sind ausschließlich für Administratoren verfügbar und benötigen sowohl ein gültiges CSRF-Token als auch eine zusätzliche Sicherheitsbestätigung.

## Verpflichtender Testlauf

Jede Aktion beginnt mit einem Testlauf. Die Ergebnisseite zeigt die gewählte Aktion, ihren Kontext und die Anzahl der aktuell betroffenen Datensätze. Erst dort kann die Bereinigung ausdrücklich bestätigt werden.

Der Testlauf wird serverseitig für den angemeldeten Administrator hinterlegt, ist 15 Minuten gültig und nur einmal verwendbar. Ändern sich Anzahl oder beschriebener Kontext der betroffenen Datensätze vor der Ausführung, wird die Löschung abgebrochen und ein neuer Testlauf verlangt. Ein direkter Aufruf der Löschaktion ohne gültige Vorschau ist nicht möglich.

Bei der Auswahl nach Modul werden neben eigenständigen Slices auch verschachtelte Inhalte des optionalen Gridblock-Add-ons erkannt. Clear Content ermittelt Gridblock-Container über deren Modulkennung, entfernt passende Einträge aus der Modulbelegung, den zugehörigen Spaltenwerten und der Sortierreihenfolge und bereinigt dieselben Strukturen in der Slice-Historie. Unbekannte JSON-Strukturen anderer Add-ons werden bewusst nicht automatisch verändert.

Die Bereinigung von Synchronisationsdateien berücksichtigt ausschließlich tatsächlich vorhandene Ordner. Sie entfernt den Datenordner des Developer-Add-ons und bei installiertem Theme-Add-on automatisch den Unterordner `private/redaxo` des konfigurierten Theme-Verzeichnisses. Andere Theme-Dateien bleiben erhalten. Developer- und Theme-Konfiguration werden nicht verändert. Eine weiterhin aktive Synchronisation kann die gelöschten Dateien später erneut erzeugen.

Beim vollständigen Entfernen von YForm-Tabellen zeigt der Testlauf zusätzlich alle zu löschenden Tabellen und eingehenden Relationsfelder. Eigene Relationstabellen werden nur entfernt, wenn sie nicht gemeinsam mit anderen Relationen verwendet werden. Externe Datenbank-Fremdschlüssel führen zum Abbruch. Verweise in individuellem PHP-Code, Modulausgaben oder freien Textfeldern lassen sich technisch nicht zuverlässig erkennen und müssen vorab manuell geprüft werden.

## Voraussetzungen

- REDAXO 5.21 oder neuer
- PHP 8.3 oder neuer
- Structure einschließlich Content-Plugin
- Mediapool
- YForm 5

## Architektur

Die Backend-Seite enthält nur die Formular-, Vorschau- und Ablaufsteuerung. Die eigentliche Analyse und Bereinigung ist in unabhängige Services aufgeteilt:

- `FriendsOfRedaxo\ClearContent\Cleanup\SliceCleaner`
- `FriendsOfRedaxo\ClearContent\Cleanup\StructureCleaner`
- `FriendsOfRedaxo\ClearContent\Cleanup\MediaCleaner`
- `FriendsOfRedaxo\ClearContent\Cleanup\TableCleaner`
- `FriendsOfRedaxo\ClearContent\Cleanup\GridblockNestedModuleCleaner`
- `FriendsOfRedaxo\ClearContent\Cleanup\SynchronizationFileCleaner`
- `FriendsOfRedaxo\ClearContent\Cleanup\CleanupPreviewService`

Neue Bereinigungsarten können dadurch ergänzt werden, ohne die Backend-Seite wieder mit Löschlogik zu vermischen.

Die Issues [#5](https://github.com/FriendsOfREDAXO/clear_content/issues/5), [#9](https://github.com/FriendsOfREDAXO/clear_content/issues/9), [#12](https://github.com/FriendsOfREDAXO/clear_content/issues/12), [#16](https://github.com/FriendsOfREDAXO/clear_content/issues/16) und [#17](https://github.com/FriendsOfREDAXO/clear_content/issues/17) sind in Version 2.0.0-beta2 umgesetzt. Optionale Integrationen für weitere Add-ons aus [Issue #6](https://github.com/FriendsOfREDAXO/clear_content/issues/6) gehören ausdrücklich nicht zum Funktionsumfang.

## Support

Fehler, Ideen und Wünsche bitte im [GitHub-Repository](https://github.com/FriendsOfREDAXO/clear_content/issues) melden.

## Lizenz und Credits

MIT-Lizenz. Entwickelt von [Friends Of REDAXO](https://github.com/FriendsOfREDAXO). Idee und erste Umsetzung: [Oliver Kreischer / getaweb](https://getaweb.de).
