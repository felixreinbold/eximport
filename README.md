# suedlicht/eximport

OXID eShop Modul für den Massenexport und -import von Artikeldaten via Excel.

Artikeldaten können als `.xlsx`-Datei exportiert, in Excel bearbeitet und anschließend wieder importiert werden — ohne einzelne Artikel manuell im Admin anzufassen.

## Voraussetzungen

- OXID eShop >= 7.4
- PHP >= 8.2
- Composer

## Installation

### Schritt 1 — Repository bekannt machen

Composer weiß standardmäßig nicht, wo dieses Modul liegt. Deshalb muss das GitHub-Repository einmalig in der `composer.json` deines Shops eingetragen werden.

Öffne die `composer.json` im Shop-Root und füge den `repositories`-Block hinzu (falls noch kein solcher Block existiert, wird er neu angelegt; falls bereits Einträge vorhanden sind, einfach das Objekt ergänzen):

```json
"repositories": [
    {
        "type": "vcs",
        "url": "https://github.com/felixreinbold/eximport.git"
    }
]
```

> **Hinweis:** Das Repository ist aktuell privat. Du benötigst einen GitHub-Token mit Lesezugriff in deiner `auth.json`:
> ```json
> {
>     "github-oauth": {
>         "github.com": "DEIN_GITHUB_TOKEN"
>     }
> }
> ```
> Einen Token erstellst du unter: GitHub → Settings → Developer Settings → Personal Access Tokens → Tokens (classic) → Scope: `repo`

### Schritt 2 — Modul per Composer installieren

```bash
composer require suedlicht/eximport:^1.0.0
```

Composer lädt das Modul herunter und legt es unter `vendor/suedlicht/eximport` ab.

> Ohne Versionsangabe installiert Composer die neueste verfügbare Version. Mit `^1.0.0` wird gezielt die aktuelle stabile Version installiert und bei `composer update` automatisch auf kompatible Folgeversionen (1.x.x) aktualisiert.

### Schritt 3 — Modul im Shop registrieren und aktivieren

```bash
vendor/bin/oe-console oe:module:install ./vendor/suedlicht/eximport
vendor/bin/oe-console oe:module:activate eximport
```

Der erste Befehl registriert das Modul im Shop. Der zweite Befehl aktiviert es, sodass es im Admin erscheint.

## Verwendung

Nach der Aktivierung erscheint im OXID-Admin ein neuer Menüpunkt. Dort stehen zwei Funktionen zur Verfügung:

**Export**
1. Export-Variante aus dem Dropdown wählen (z. B. "Standard" oder "Kategorie")
2. Auf "Artikel exportieren" klicken
3. Die erzeugte `.xlsx`-Datei wird automatisch heruntergeladen

**Import**
1. Exportierte und bearbeitete Excel-Datei auswählen
2. Dieselbe Variante wie beim Export wählen
3. Auf "Artikel importieren" klicken
4. Das Ergebnis (Anzahl Erfolge / Fehler) wird direkt angezeigt

> Die Spaltenstruktur der Excel-Datei muss mit der gewählten Variante übereinstimmen. Verändere keine Spaltenreihenfolge oder Überschriften.

## Export-Varianten

| Variante | Inhalt |
|---|---|
| `standard` | Artikel-ID und Titel aus `oxarticles` |
| `article_category` | Artikel-ID, Artikelnummer, Titel und zugeordnete Kategorie-IDs |

Eigene Varianten können ohne Änderung am Kern-Code hinzugefügt werden — siehe [Entwickler-Leitfaden.md](Entwickler-Leitfaden.md).

## Updates einspielen

```bash
composer update suedlicht/eximport
```