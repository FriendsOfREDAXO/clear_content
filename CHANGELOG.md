# Changelog

Alle wesentlichen Änderungen an Clear Content werden in dieser Datei dokumentiert. Das Format orientiert sich an [Keep a Changelog](https://keepachangelog.com/de/1.1.0/) und [Semantic Versioning](https://semver.org/lang/de/).

## [2.0.0-beta2] - 2026-08-18

### Hinzugefügt

- GitHub-Actions-Workflow für automatisierte Prüfungen und Releases ergänzt. Danke an [@skerbis](https://github.com/skerbis).

## [2.0.0-beta1] - 2026-08-18

### Hinzugefügt

- namespaced Cleanup-Services für Slices, Struktur, Medien, YForm-Tabellen und Synchronisationsdateien
- Mehrfachauswahl zum Löschen aller Slices bestimmter Module samt Historie und verschachtelten Gridblock-Inhalten; damit ist [Issue #9](https://github.com/FriendsOfREDAXO/clear_content/issues/9) umgesetzt
- gemeinsame Mehrfachauswahl der Sprachen für sprachbezogene Slice-Aktionen
- gemeinsame Mehrfachauswahl zum Löschen ausgewählter Medienkategorien oder aller Medien aus diesen Kategorien
- Mehrfachauswahl zum Löschen aller Slices ausgewählter Artikel in den gewählten Sprachen samt Historie; damit ist [Issue #17](https://github.com/FriendsOfREDAXO/clear_content/issues/17) umgesetzt
- gemeinsame Mehrfachauswahl für das vollständige Löschen von Kategorien und die Massenlöschung normaler Artikel direkt in diesen Kategorien, optional anhand des letzten Änderungsdatums aller Sprachen; damit ist [Issue #12](https://github.com/FriendsOfREDAXO/clear_content/issues/12) umgesetzt
- gemeinsame Bereinigung tatsächlich vorhandener Developer-Synchronisationsdateien und bei installiertem Theme-Add-on automatisch des konfigurierten Ordners `theme/private/redaxo`, ohne Synchronisationseinstellungen zu verändern; damit ist [Issue #16](https://github.com/FriendsOfREDAXO/clear_content/issues/16) umgesetzt
- verpflichtender, serverseitig abgesicherter Testlauf mit Ergebnisseite vor jeder Löschung
- einmal verwendbare, benutzergebundene Vorschau-Tokens mit 15 Minuten Gültigkeit
- erneute Bestandsprüfung unmittelbar vor der Ausführung
- drei eigenständige Backend-Tabs für Inhalte, Medien und Tabelleninhalte
- sichere Bereinigung frei auswählbarer YForm-TableManager-Tabellen mit Allowlist; damit ist [Issue #5](https://github.com/FriendsOfREDAXO/clear_content/issues/5) umgesetzt
- gemeinsame Mehrfachauswahl der YForm-Tabellen für das Leeren und das vollständige Entfernen
- optionales vollständiges Entfernen ausgewählter YForm-Tabellen samt Schema, Konfiguration, Historie und erkennbaren Relationsresten
- Schutz vor dem Entfernen gemeinsam genutzter Relationstabellen, geschützter Tabellen und extern referenzierter Tabellen
- Bereinigung zugehöriger YForm-Historieneinträge
- serverseitige Admin-, CSRF- und Sicherheitsbestätigungsprüfung
- englische Übersetzung
- Dokumentation der in 2.0.0-beta1 umgesetzten Issues sowie des bewusst ausgeschlossenen Issue #6

### Geändert

- Mindestversionen auf REDAXO 5.21 und PHP 8.3 angehoben
- Abhängigkeiten auf Structure, Structure/Content und Mediapool korrigiert
- YForm 5 als Abhängigkeit für die Tabellenbereinigung ergänzt
- Löschaktionen voneinander getrennt und nicht mehr dauerhaft in der Add-on-Konfiguration gespeichert
- die redundante Aktion für alle Sprachen durch die verpflichtende gemeinsame Sprachauswahl ersetzt; alle Sprachen können weiterhin gemeinsam ausgewählt werden
- Website-Startartikel und Fehlerartikel werden beim Leeren der Struktur bewahrt
- direkte Tabellen-TRUNCATE-Operationen durch kontrollierte Löschabläufe ersetzt
- Backend-Markup und Hilfeseite modernisiert
- Backend-Oberfläche auf neutrale REDAXO-Panels, Standard-Formularfragmente, kompakte Selectpicker und eine responsive Testlauf-Tabelle umgestellt
- Aktionsseiten in ein responsives Zwei-Spalten-Layout mit Aktionen links und kontextbezogenen Hinweisen rechts gegliedert
- allgemeinen Testlauf-Hinweis von den Aktionsseiten in die README verlagert
- Abstand zwischen Auswahlfeldern und nachfolgenden Aktionsbuttons vereinheitlicht
- getrennte Developer- und Theme-Dateiaktionen zu einer automatisch ermittelten Aktion zusammengeführt; nicht vorhandene Zielordner werden ausgeblendet
- Kategorieauswahl, Artikelstichtag und zugehörige Aktionen kompakt zusammengefasst; die unabhängige Löschung der gesamten Seitenstruktur klar abgetrennt
- sprachübergreifende Wirkung der Strukturlöschung und Schutz von Website-Start- und Fehlerartikel in Oberfläche und README klargestellt
- Bestätigungsbereich auf allen Ergebnisseiten größer, besser lesbar und responsiv umbrechend gestaltet
- Testlaufergebnis mit Statusblock, hervorgehobener Trefferzahl und klarer Auswahlliste optisch strukturiert
- kontextbezogene Hinweisboxen auf allen Aktionsseiten mit der tatsächlichen Löschlogik abgeglichen und nicht verfügbare Aktionen samt Hinweisen ausgeblendet
- dauerhaften Löschhinweis am Anfang der README als große rote Warnbox mit weißer Schrift und Warnsymbol hervorgehoben
- Versionsnummer automatisch im REDAXO-Seitenkopf ergänzt
- Aktionsbuttons auf kurze Beschriftungen ohne „Testlauf“-Präfix reduziert; Ordner- und Bildaktion im Medienpool sind zur Unterscheidung gezielt gekennzeichnet
- pauschale Aktion „Alle Medien löschen“ zugunsten der gezielten Kategorieauswahl entfernt
- Info-Navigation mit Hilfe, Changelog und Lizenz nach aktuellem REDAXO-Muster ergänzt

### Behoben

- wiederholte Löschungen durch nicht zurückgesetzte Konfigurationswerte
- wirkungslose Reset-Ausdrücke mit Vergleichsoperatoren
- leere Sprachdateien und nicht geschlossene Fieldsets
- fehlende Fehlerbehandlung bei Datei- und Datenbankoperationen
- verbliebene Slice-History-Einträge beim Löschen vollständiger Kategorien und Artikelstrukturen

### Entfernt

- veraltete PHP-5.6-/REDAXO-5.2-Kompatibilität
- nicht verwendete Media-Manager-Abhängigkeit
- alte Installations- und Deinstallationsvorlagen ohne Funktion

## [1.1.1] - 2020-09-02

- letzter veröffentlichter Stand der ursprünglichen Implementierung
