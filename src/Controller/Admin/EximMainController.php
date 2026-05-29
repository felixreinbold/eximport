<?php



namespace Suedlicht\Eximport\Controller\Admin;

use OxidEsales\Eshop\Application\Controller\Admin\AdminController;
use OxidEsales\Eshop\Core\Registry;
use OxidEsales\Eshop\Core\Request;
use Suedlicht\Eximport\Service\ExcelHandler;
use Exception;

/**
 * EximMainController
 * Steuert die Interaktion zwischen der Admin-Oberfläche und dem Excel-Service.
 */
class EximMainController extends AdminController
{
    /** @var string Pfad zum Twig-Template */
    protected $_sThisTemplate = '@eximport/admin/exim_main.html.twig';

    /**
     * Erlaubt den Zugriff auf diesen Controller.
     * @return bool
     */
    protected function _authorize() 
    { 
        return true; 
    }

    /**
     * Rendert die Admin-Seite.
     * @return string
     */
    public function render()
    {
        parent::render();
        return $this->_sThisTemplate;
    }

    /**
     * Führt den Datenexport aus.
     * Nutzt das moderne Request-Objekt für den Parameter-Zugriff.
     */
    public function exportToExcel()
    {
        try {
            $oRequest = Registry::get(Request::class);
            $sVariant = $oRequest->getRequestParameter('exportVariant');

            $oHandler = new ExcelHandler();
            // Strikter Aufruf: Falls $sVariant leer ist, wirft der Service eine Exception
            $oHandler->exportData($sVariant);

        } catch (Exception $e) {
            // Fehlermeldung (z.B. "Keine Variante gewählt") im Admin anzeigen
            Registry::getUtilsView()->addErrorToDisplay($e->getMessage());
        }
    }

    /**
     * Verarbeitet den Import einer Excel-Datei.
     */
    public function importFromExcel()
    {
        $oUtilsView = Registry::getUtilsView();

        // 1. Basis-Check: Wurde eine Datei hochgeladen?
        if (!isset($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
            $oUtilsView->addErrorToDisplay('Fehler: Bitte wählen Sie eine gültige Excel-Datei aus.');
            return;
        }

        try {
            $oRequest = Registry::get(Request::class);
            $sVariant = $oRequest->getRequestParameter('exportVariant');
            $sTmpPath = $_FILES['import_file']['tmp_name'];

            $oHandler = new ExcelHandler();
            $aResult = $oHandler->importData($sTmpPath, $sVariant);

            // 2. Ergebnis-Feedback an den User
            $sMsg = "Import abgeschlossen. Erfolg: {$aResult['success']} | Fehler: {$aResult['errors']}";
            $oUtilsView->addErrorToDisplay($sMsg);

        } catch (Exception $e) {
            // Fängt Fehler wie "Variante nicht definiert" oder Dateifehler ab
            $oUtilsView->addErrorToDisplay("Import-Abbruch: " . $e->getMessage());
        }
    }
}