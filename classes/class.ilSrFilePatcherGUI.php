<?php

/* Copyright (c) 1998-2009 ILIAS open source, Extended GPL, see https://github.com/ILIAS-eLearning/ILIAS/tree/trunk/docs/LICENSE */

require_once __DIR__ . "/../vendor/autoload.php";

use srag\Plugins\SrFilePatcher\Utils\SrFilePatcherTrait;
use srag\DIC\SrFilePatcher\DICTrait;
use srag\Plugins\SrFilePatcher\Access\Access;
use srag\Plugins\SrFilePatcher\Config\ConfigFormGUI;

/**
 * Class ilSrFilePatcherGUI
 *
 * @ilCtrl_IsCalledBy   ilSrFilePatcherGUI: ilUIPluginRouterGUI
 * @ilCtrl_Calls        ilSrFilePatcherGUI: ilObjComponentSettingsGUI
 *
 * @author  studer + raimann ag - Team Core 1 <support-core1@studer-raimann.ch>
 */
class ilSrFilePatcherGUI
{
    use DICTrait;
    use SrFilePatcherTrait;

    const PLUGIN_CLASS_NAME = ilSrFilePatcherPlugin::class;
    const CMD_DEFAULT = "index";
    const CMD_PATCH = "patch";
    const CMD_CANCEL = "cancel";


    /**
     * @var ilCtrl
     */
    private $ctrl;
    /**
     * @var ilLanguage
     */
    private $lng;
    /**
     * @var ilComponentLogger
     */
    private $log;
    /**
     * @var ilSrFilePatcherPlugin
     */
    protected $pl;
    /**
     * @var ilTabsGUI
     */
    private $tabs;
    /**
     * @var ilTemplate
     */
    private $tpl;




    public function __construct() {
        $this->ctrl = self::dic()->ctrl();
        $this->lng  = self::dic()->language();
        $this->log  = self::dic()->log();
        $this->pl   = ilSrFilePatcherPlugin::getInstance();
        $this->tabs = self::dic()->tabs();
        $this->tpl  = self::dic()->ui()->mainTemplate();
    }


    public function executeCommand() {

        $this->ctrl->saveParameterByClass(ilSrFilePatcherGUI::class, "ref_id");
        $next_class = self::dic()->ctrl()->getNextClass($this);
        $this->tpl->getStandardTemplate();

        switch (strtolower($next_class)) {
            case strtolower(ilObjComponentSettingsGUI::class):
                $gui_obj = new ilObjComponentSettingsGUI("", $_GET['ref_id'], true, false);
                $this->ctrl->forwardCommand($gui_obj);
                break;
            default:
                $cmd = $this->ctrl->getCmd(self::CMD_DEFAULT);
                switch ($cmd) {
                    case self::CMD_DEFAULT:
                    case self::CMD_PATCH:
                    case self::CMD_CANCEL:
                        $this->$cmd();
                        break;
                    default:
                        // Unknown command
                        Access::redirectNonAccess(ilObjComponentSettingsGUI::class);
                        break;
                }
                break;
        }
        $this->tpl->show();
    }


    private function index($a_init_form = true, $a_fill_form = true) {
        // back-tab
        $this->tabs->clearTargets();
        $this->ctrl->saveParameterByClass(ilAdministrationGUI::class, "ref_id");
        $link_target = $this->ctrl->getLinkTargetByClass(array(
            ilAdministrationGUI::class,
            ilObjComponentSettingsGUI::class)
        );
        $this->tabs->setBackTarget($this->lng->txt("back"), $link_target);

        $form = new ilSrFilePatcherFormGUI($this);
        $this->tpl->setContent($form->getHTML());

    }


    protected function patch()
    {
        $this->showErrorReport($_POST['ref_id_file']);
        $this->ctrl->redirectByClass(ilSrFilePatcherGUI::class, self::CMD_DEFAULT);
    }


    private function showErrorReport($a_file_ref_id)
    {
        $file_patcher = new ilSrFilePatcher();
        $file = new ilObjFile($a_file_ref_id);
        $error_report = $file_patcher->getErrorReportOfFileVersioning($file);
        $block = "<div style='display:inline-block;clear:both;width:300px;margin:0'>";
        $tab = "</div><div style='display:inline;'>";
        $end = "</div><br>";
        $html_error_report = "<b>ERROR REPORT FOR FILE " . $a_file_ref_id . ":</b><br>"
            . $block . "num_duplicate_version_numbers:"    . $tab . $error_report['num_duplicate_version_numbers'] . $end
            . $block . "has_wrong_max_version:"            . $tab . ($error_report['has_wrong_max_version']==true?"Yes":"No") . $end
            . $block . "has_wrong_version:"                . $tab . ($error_report['has_wrong_version']==true?"Yes":"No") . $end
            . $block . "num_missing_version_folders:"      . $tab . $error_report['num_missing_version_folders'] . $end
            . $block . "num_redundant_version_folders:"    . $tab . $error_report['num_redundant_version_folders'] . $end
            . $block . "num_lost_old_version_files:"       . $tab . $error_report['num_lost_old_version_files'] . $end
            . $block . "num_lost_new_version_files:"       . $tab . $error_report['num_lost_new_version_files'] . $end
            . $block . "num_misplaced_new_version_files:"  . $tab . $error_report['num_misplaced_new_version_files'] . $end;
        ilUtil::sendInfo($html_error_report, true);
    }



    /**
     * Return to plugin overview
     */
    protected function cancel()
    {
        $this->ctrl->saveParameterByClass(ilAdministrationGUI::class, "ref_id");
        $this->ctrl->redirectByClass(
            array(
                ilAdministrationGUI::class,
                ilObjComponentSettingsGUI::class),
            "listPlugins"
        );
    }


}