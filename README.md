# suedlicht/eximport

OXID eShop Modul für den Massenexport und -import von Artikeldaten via Excel.

## Voraussetzungen

- OXID eShop >= 7.4
- PHP >= 8.2

## Installation

### 1. Repository in der Shop-`composer.json` eintragen

```json
"repositories": [
    {
        "type": "vcs",
        "url": "https://github.com/felixreinbold/eximport.git"
    }
]
```

### 2. Modul installieren

```bash
composer require suedlicht/eximport
vendor/bin/oe-console oe:module:install ./vendor/suedlicht/eximport
vendor/bin/oe-console oe:module:activate eximport
```

## Features

- **Export**: Artikeldaten als `.xlsx`-Datei herunterladen
- **Import**: Excel-Datei hochladen und Artikel massenweise aktualisieren
- **Blueprint-System**: Erweiterbare Varianten für unterschiedliche Exportformate (Standard, Kategorie-Join, eigene)
- **Virtuelle Felder**: Joins über mehrere Tabellen (z. B. Kategorien) per Export/Import bearbeitbar

## Verwendung

Das Modul fügt unter **Admin → (Menüpunkt)** eine Oberfläche hinzu, über die:

1. eine Export-Variante gewählt wird
2. die Artikel als Excel-Datei heruntergeladen werden
3. die bearbeitete Datei wieder hochgeladen wird

## Eigene Varianten entwickeln

Siehe [Entwickler-Leitfaden.md](Entwickler-Leitfaden.md) für eine vollständige Anleitung zum Hinzufügen eigener Export/Import-Varianten (inkl. virtueller Felder).

## Updates einspielen

```bash
composer update suedlicht/eximport
```
