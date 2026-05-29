# 📘 Entwickler-Leitfaden: EXIM Varianten-System

## 🏗 Die Architektur auf einen Blick

Das System unterscheidet zwischen zwei Arten von Daten:

- **Standard-Felder**: Daten, die direkt in der Haupttabelle (z. B. `oxarticles`) liegen.
- **Virtuelle Felder**: Daten aus anderen Tabellen (z. B. Attribute, Kategorien, Preise mit Logik), die über "Joins" oder Berechnungen geholt werden müssen.

## 1. Schritt: Die Variante im Admin registrieren

Damit der Admin die neue Variante auswählen kann, muss sie im Twig-Template hinterlegt werden.

**Datei:** `views/admin/tpl/exim_main.html.twig`  
Suche das `<select>`-Feld für `exportVariant` und füge deine neue Option hinzu:

```html
<select name="exportVariant">
    <option value="standard">Standard Artikel</option>
    <option value="seo_meta">SEO & Metadaten</option>
    <option value="countries">Länder-Liste</option> ggf. translations nutzen
</select>
```

## 2. Schritt: Den "Blueprint" (Bauplan) definieren

In der `getBlueprint()`-Methode des ExcelHandler definierst du nun, was bei dieser Variante passieren soll.

**Datei:** `src/Service/ExcelHandler.php`

```php
'seo_meta' => [
    'model'      => \OxidEsales\Eshop\Application\Model\Article::class,
    'list_model' => \OxidEsales\Eshop\Application\Model\ArticleList::class,
    'table'      => 'oxarticles',
    'columns'    => [
        'A' => ['field' => 'oxid',       'label' => 'ID'],
        'B' => ['field' => 'oxartnum',   'label' => 'ArtNr'],
        'C' => ['field' => 'oxtitle',    'label' => 'Titel'],
        'D' => ['field' => 'oxkeywords', 'label' => 'SEO Keywords'], // Standard-Feld
        'E' => ['field' => 'oxdescription', 'label' => 'SEO Description'] // Standard-Feld
    ]
]
```

> **Tipp:** Da dies nur Standard-Felder aus `oxarticles` sind, bist du hier bereits fertig! Der Export und Import funktioniert sofort.

## 3. Spezialfall: Virtuelle Felder (Joins)

Wenn du Daten aus einer anderen Tabelle benötigst (z. B. die Attribute eines Artikels), nutzt du den `type => virtual`.

### Schritt 3a: Blueprint erweitern

Füge das Feld im Blueprint hinzu und markiere es als `virtual`.

```php
'article_attributes' => [
    'model' => \OxidEsales\Eshop\Application\Model\Article::class,
    'table' => 'oxarticles',
    'columns' => [
        'A' => ['field' => 'oxid', 'label' => 'ID'],
        'B' => ['field' => 'attributes', 'label' => 'Attribute (Name:Wert|...)', 'type' => 'virtual']
    ]
]
```

### Schritt 3b: Die Export-Logik (`_get...`)

Erstelle eine Methode, die exakt `_getVirtual` + Feldname (mit Großbuchstabe) heißt.

```php
/**
 * Sammelt alle Attribute eines Artikels und formatiert sie für Excel.
 * Format: "Farbe:Rot | Größe:XL"
 */
protected function _getVirtualAttributes($oItem) {
    $db = DatabaseProvider::getDb();
    $sql = "SELECT a.OXTITLE, v.OXVALUE 
            FROM oxobject2attribute v 
            JOIN oxattribute a ON a.OXID = v.OXATTRID 
            WHERE v.OXOBJECTID = ?";
    
    $rs = $db->getAll($sql, [$oItem->getId()]);
    $parts = [];
    foreach($rs as $row) {
        $parts[] = $row[0] . ":" . $row[1];
    }
    return implode(' | ', $parts);
}
```

### Schritt 3c: Die Import-Logik (`_set...`)

Erstelle die entsprechende Gegen-Methode für den Import. Diese erhält die ID des Hauptobjekts und den Text aus der Excel-Zelle.

```php
/**
 * Zerlegt den String aus Excel und schreibt ihn in die oxobject2attribute Tabelle.
 */
protected function _setVirtualAttributes($id, $value) {
    $db = DatabaseProvider::getDb();
    // 1. Bestehende Attribute löschen (Clean Slate)
    $db->execute("DELETE FROM oxobject2attribute WHERE OXOBJECTID = ?", [$id]);

    // 2. String zerlegen (Farbe:Rot | Größe:XL)
    $pairs = explode('|', $value);
    foreach($pairs as $pair) {
        list($attrName, $attrValue) = explode(':', $pair);
        
        // Hier müsste man logischerweise erst die OXID des Attributs via Name finden
        // und dann in oxobject2attribute inserten.
    }
}
```

## 🛠 Checkliste für neue Varianten

| Aufgabe | Erledigt? |
|---------|-----------|
| Option in `exim_main.html.twig` hinzugefügt | ☐ |
| Blueprint in `getBlueprint()` definiert | ☐ |
| Richtige Tabelle (`table`) im Blueprint eingetragen | ☐ |
| Richtige Models (`model` / `list_model`) gewählt | ☐ |
| Falls virtual: Methode `_getVirtual[Feld]` implementiert | ☐ |
| Falls virtual: Methode `_setVirtual[Feld]` implementiert | ☐ |
