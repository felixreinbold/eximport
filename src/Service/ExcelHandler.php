<?php

namespace Suedlicht\Eximport\Service;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;
use OxidEsales\Eshop\Core\Registry;
use OxidEsales\Eshop\Core\DatabaseProvider;
use Exception;

/**
 * ExcelHandler
 * Ein hochflexibler Service, der OXID-Entitäten (Artikel, Länder, etc.) basierend
 * auf Blueprints verarbeitet. Unterstützt Standard-Datenbankfelder und komplexe
 * virtuelle Felder (Joins).
 */
class ExcelHandler
{
    /**
     * Zentrales Konfigurations-Register (Blueprints)
     * Hier wird definiert, welche Variante welche Daten aus welcher Tabelle nutzt.
     * @param string $variant Der Identifikator der gewünschten Export/Import-Variante
     * @return array Konfigurations-Blueprint
     * @throws Exception Wenn die Variante nicht existiert oder nicht angegeben wurde
     */
    protected function getBlueprint($variant)
    {
        if (empty($variant)) {
            throw new Exception("Fehler: Keine Export-Variante ausgewählt.");
        }

        $blueprints = [
            // BEISPIEL: ARTIKEL MIT KATEGORIE-JOIN
            'article_category' => [
                'model'      => \OxidEsales\Eshop\Application\Model\Article::class,
                'list_model' => \OxidEsales\Eshop\Application\Model\ArticleList::class,
                'table'      => 'oxarticles',
                'columns'    => [
                    'A' => ['field' => 'oxid',     'label' => 'Interne ID (OXID)'],
                    'B' => ['field' => 'oxartnum', 'label' => 'Artikelnummer'],
                    'C' => ['field' => 'oxtitle',  'label' => 'Produktname'],
                    // VIRTUAL: Benötigt eigene Methoden _getVirtualCategories & _setVirtualCategories
                    'D' => ['field' => 'categories', 'label' => 'Kategorie-IDs (kommagetrennt)', 'type' => 'virtual']
                ]
            ],
            // BEISPIEL: LÄNDER (Einfacher Export ohne Joins)
            'standard' => [
                'model'      => \OxidEsales\Eshop\Application\Model\Article::class,
                'list_model' => \OxidEsales\Eshop\Application\Model\ArticleList::class,
                'table'      => 'oxarticles',
                'columns'    => [
                    'A' => ['field' => 'oxid',        'label' => 'OX ID'],
                    'B' => ['field' => 'oxtitle',     'label' => 'oxtitle']
                ]
            ]
        ];

        if (!isset($blueprints[$variant])) {
            throw new Exception("Fehler: Die Variante '{$variant}' ist im ExcelHandler nicht registriert.");
        }

        return $blueprints[$variant];
    }

    /**
     * Hauptmethode für den Datenexport
     * @param string $variant
     */
    public function exportData($variant)
    {
        $bp = $this->getBlueprint($variant);
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // 1. Header-Zeile basierend auf Blueprint-Labels erstellen
        foreach ($bp['columns'] as $col => $conf) {
            $sheet->setCellValue($col . '1', $conf['label']);
        }

        // 2. Datenquelle dynamisch über das Listen-Model laden
        $oList = oxNew($bp['list_model']);
        $oList->selectString("SELECT * FROM " . $bp['table']);

        // 3. Datenzeilen befüllen
        $row = 2;
        foreach ($oList as $oItem) {
            foreach ($bp['columns'] as $col => $conf) {
                // Entscheidung: Standard-Feld oder Virtuelle Logik (Join)?
                if (isset($conf['type']) && $conf['type'] === 'virtual') {
                    $val = $this->_handleVirtualFieldExport($conf['field'], $oItem);
                } else {
                    $fullField = $bp['table'] . "__" . $conf['field'];
                    $val = $oItem->$fullField->value;
                }
                $sheet->setCellValue($col . $row, $val);
            }
            $row++;
        }

        $this->_executeDownload($spreadsheet, "export_{$variant}_" . date('Y-m-d') . ".xlsx");
    }

    /**
     * Hauptmethode für den Datenimport
     * @param string $filePath Pfad zur temporären Upload-Datei
     * @param string $variant Gewählter Blueprint
     * @return array Ergebnis-Statistik
     */
    public function importData($filePath, $variant)
    {
        $bp = $this->getBlueprint($variant);
        $result = ['success' => 0, 'errors' => 0];

        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();
        $highestRow = $sheet->getHighestRow();

        for ($row = 2; $row <= $highestRow; $row++) {
            $oxid = $sheet->getCell('A' . $row)->getValue();
            if (empty($oxid)) continue;

            $oObject = oxNew($bp['model']);
            if ($oObject->load($oxid)) {
                if ($this->_mapRowToObject($oObject, $sheet, $row, $bp)) {
                    $result['success']++;
                } else {
                    $result['errors']++;
                }
            }
        }
        return $result;
    }

    /**
     * ÜBERTRAGUNG: Excel-Daten -> OXID Objekt
     * Verarbeitet Standard-Zuweisungen und triggert virtuelle Felder.
     */
    protected function _mapRowToObject($oObject, $sheet, $row, $bp)
{
    $data = [];
    $virtualData = [];

    foreach ($bp['columns'] as $col => $conf) {
        if ($conf['field'] === 'oxid') continue; 
        
        // SICHERHEIT: Wert explizit als String laden (behebt RichText-Probleme)
        $val = (string)$sheet->getCell($col . $row)->getValue();

        if (isset($conf['type']) && $conf['type'] === 'virtual') {
            $virtualData[$conf['field']] = $val;
        } else {
            $data[$bp['table'] . "__" . $conf['field']] = $val;
        }
    }

    // 1. Daten dem Objekt zuweisen
    $oObject->assign($data);

    // 2. Speichern des Hauptobjekts
    // Wir erzwingen hier die Ausführung der virtuellen Logik, 
    // solange kein technischer Fehler beim Speichern auftritt.
    try {
        $saveResult = $oObject->save();
        
        // Wir holen die ID direkt vom Objekt, um sicher zu sein
        $sObjectId = $oObject->getId();

        // 3. Virtuelle Felder abarbeiten (auch wenn save() nur 'true' oder die ID lieferte)
        if ($saveResult !== false && !empty($sObjectId)) {
            foreach ($virtualData as $fieldName => $value) {
                $this->_handleVirtualFieldImport($fieldName, $sObjectId, $value);
            }
            return true;
        }
    } catch (\Exception $e) {
        // Optional: Hier Log-Eintrag schreiben
        return false;
    }

    return false;
}

    // =========================================================================
    // VIRTUAL FIELD DISPATCHER (DIE "KOMPLIZIERTE" LOGIK)
    // =========================================================================
    
    /**
     * DER DISPATCHER (Export):
     * Diese Methode ist der Verkehrspolizist. Wenn im Blueprint ein Feld "categories"
     * als "virtual" markiert ist, sucht sie automatisch nach der Methode 
     * "_getVirtualCategories". So bleibt der Hauptcode generisch.
     */
    protected function _handleVirtualFieldExport($fieldName, $oItem)
    {
        $method = '_getVirtual' . ucfirst($fieldName);
        return method_exists($this, $method) ? $this->$method($oItem) : '';
    }

    /**
     * DER DISPATCHER (Import):
     * Sucht automatisch nach Methoden wie "_setVirtualCategories".
     */
    protected function _handleVirtualFieldImport($fieldName, $id, $value)
{
    // Wir normalisieren den Namen, um Tippfehler im Blueprint zu verzeihen
    $method = '_setVirtual' . ucfirst(strtolower($fieldName));
    
    if (method_exists($this, $method)) {
        $this->$method($id, $value);
    } else {
        // DEBUG-HILFE: Wenn du das hier in ein Log schreibst, 
        // siehst du ob die Methode gesucht aber nicht gefunden wurde.
    }
}

    /**
     * SPEZIAL-LOGIK: Kategorien (Export)
     * Holt via SQL alle zugeordneten Kategorien eines Objekts aus der Join-Tabelle.
     */
    protected function _getVirtualCategories($oItem)
    {
        $db = DatabaseProvider::getDb();
        $sql = "SELECT OXCATNID FROM oxobject2category WHERE OXOBJECTID = ?";
        return implode(',', $db->getCol($sql, [$oItem->getId()]));
    }

    /**
     * SPEZIAL-LOGIK: Kategorien (Import)
     * Löscht alte Verknüpfungen und setzt neue IDs aus der Excel-Zelle.
     */
   protected function _setVirtualCategories($id, $value)
{
    // TEST: Wenn du das hier aktivierst, muss der Import mit dieser Meldung abbrechen.
    // So weißt du zu 100%, dass die Methode aufgerufen wird!
    // die("ID: $id | Wert: $value"); 

    $db = DatabaseProvider::getDb();
    $db->execute("DELETE FROM oxobject2category WHERE OXOBJECTID = ?", [$id]);
    
    // IDs säubern
    $categoryIds = array_filter(array_map('trim', explode(',', (string)$value)));
    
    foreach ($categoryIds as $catId) {
        if (empty($catId)) continue;
        
        $newId = Registry::getUtilsObject()->generateUId();
        $db->execute(
            "INSERT INTO oxobject2category (OXID, OXOBJECTID, OXCATNID, OXTIME) VALUES (?,?,?,?)",
            [$newId, $id, $catId, time()]
        );
    }
}

    // =========================================================================
    // HILFSMETHODEN
    // =========================================================================

    protected function _executeDownload($spreadsheet, $filename)
    {
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        
        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        Registry::getUtils()->showMessageAndExit('');
    }
}