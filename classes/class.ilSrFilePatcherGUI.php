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
 * @author              studer + raimann ag - Team Core 1 <support-core1@studer-raimann.ch>
 */
class ilSrFilePatcherGUI
{

    use DICTrait;
    use SrFilePatcherTrait;
    const PLUGIN_CLASS_NAME = ilSrFilePatcherPlugin::class;
    const HIST_ID = 'hist_id';
    const FILE_REF_ID = 'file_ref_id';
    const CMD_DEFAULT = "index";
    const CMD_SHOW_ERROR_REPORT = "showErrorReport";
    const CMD_CANCEL = "cancel";
    const CMD_PATCH = "patch";
    const CMD_DOWNLOAD_VERSION = "downloadVersion";
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


    public function __construct()
    {
        $this->ctrl = self::dic()->ctrl();
        $this->lng = self::dic()->language();
        $this->log = self::dic()->log();
        $this->pl = ilSrFilePatcherPlugin::getInstance();
        $this->tabs = self::dic()->tabs();
        $this->tpl = self::dic()->ui()->mainTemplate();
    }


    public function executeCommand()
    {
        // this class is reachable even when the plugin is not activated as its tab is created inside the (always accessible)
        // plugin configuration. The following if-statement ensures that the functionality of this class is not usable when
        // the plugin is deactivated
        if (!$this->pl->isActive()) {
            ilUtil::sendFailure($this->pl->txt('error_plugin_not_activated'), true);
            $this->ctrl->saveParameterByClass(ilAdministrationGUI::class, "ref_id");
            $this->ctrl->redirectByClass(
                array(
                    ilAdministrationGUI::class,
                    ilObjComponentSettingsGUI::class,
                ),
                "listPlugins"
            );
        }

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
                    case self::CMD_SHOW_ERROR_REPORT:
                    case self::CMD_CANCEL:
                    case self::CMD_PATCH:
                    case self::CMD_DOWNLOAD_VERSION:
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


    private function index()
    {
        // back-tab
        $this->tabs->clearTargets();
        $this->ctrl->saveParameterByClass(ilAdministrationGUI::class, "ref_id");
        $link_target = $this->ctrl->getLinkTargetByClass(array(
                ilAdministrationGUI::class,
                ilObjComponentSettingsGUI::class,
            )
        );
        $this->tabs->setBackTarget($this->lng->txt("back"), $link_target);

        $form = new ilSrFilePatcherFormGUI($this);
        $this->tpl->setContent($form->getHTML());

    }


    private function showErrorReport()
    {
        $template_dir = "Customizing/global/plugins/Services/Cron/CronHook/SrFilePatcher";
        $file = new ilObjFile($_POST['ref_id_file']);

        // back-tab
        $this->tabs->clearTargets();
        $this->ctrl->saveParameterByClass(ilSrFilePatcherGUI::class, "ref_id");
        $link_target = $this->ctrl->getLinkTargetByClass(ilSrFilePatcherGUI::class);
        $this->tabs->setBackTarget($this->lng->txt("back"), $link_target);

        $error_report_generator = new ilFileErrorReportGenerator();
        $error_report = $error_report_generator->getReport($file);

        // db report
        $db_report_tpl = new ilTemplate("tpl.db_report.html", true, true, $template_dir);
        $db_report_tpl->setVariable("DB_REPORT_TITLE", $this->pl->txt("error_report_title_db_report"));
        $db_report_tpl->setVariable(
            "DB_REPORT_LABEL_CURRENT_VERSION",
            $this->pl->txt("error_report_label_db_current_version") . ":"
        );
        $db_report_tpl->setVariable("DB_REPORT_CONTENT_CURRENT_VERSION", $error_report['db_current_version']);
        $db_report_tpl->setVariable(
            "DB_REPORT_LABEL_CORRECT_VERSION",
            $this->pl->txt("error_report_label_db_correct_version") . ":"
        );
        $db_report_tpl->setVariable("DB_REPORT_CONTENT_CORRECT_VERSION", $error_report['db_correct_version']);
        $db_report_tpl->setVariable(
            "DB_REPORT_LABEL_CURRENT_MAX_VERSION",
            $this->pl->txt("error_report_label_db_current_max_version") . ":"
        );
        $db_report_tpl->setVariable("DB_REPORT_CONTENT_CURRENT_MAX_VERSION", $error_report['db_current_max_version']);
        $db_report_tpl->setVariable(
            "DB_REPORT_LABEL_CORRECT_MAX_VERSION",
            $this->pl->txt("error_report_label_db_correct_max_version") . ":"
        );
        $db_report_tpl->setVariable("DB_REPORT_CONTENT_CORRECT_MAX_VERSION", $error_report['db_correct_max_version']);

        // remove db-data and ref_id from error_report to prevent problems in reading the array when passing it on to the table
        unset($error_report['file_ref_id']);
        unset($error_report['db_current_version']);
        unset($error_report['db_correct_version']);
        unset($error_report['db_current_max_version']);
        unset($error_report['db_correct_max_version']);

        // version report table
        $fs_storage_file = new ilFSStorageFile();
        $file_absolute_path = $fs_storage_file->getAbsolutePath();
        $file_dir = substr($file_absolute_path, 0, (strpos($file_absolute_path, "ilFile/") + 7));
        $version_report_table = new ilFileErrorReportTableGUI($this, self::CMD_DEFAULT, $error_report, $file_dir);
        $version_report_table_tpl = new ilTemplate("tpl.version_report_table.html", true, true, $template_dir);
        $version_report_table->setTemplate($version_report_table_tpl);
        $version_report_table_tpl->setVariable("DB_REPORT", $db_report_tpl->get());
        $version_report_table_tpl->setVariable(
            "VERSION_REPORT_TABLE_TITLE",
            $this->pl->txt("error_report_title_version_report")
        );
        $version_report_table_tpl->setVariable(
            "VERSION_REPORT_TABLE_LABEL_FILE_DIR",
            $this->pl->txt("error_report_label_file_dir") . ":"
        );
        $version_report_table_tpl->setVariable("VERSION_REPORT_TABLE_CONTENT_FILE_DIR", ($file_dir . "..."));
        $version_report_table_tpl->setVariable(
            "VERSION_REPORT_TABLE_INFO_FILE_DIR",
            $this->pl->txt("error_report_info_file_dir")
        );
        $version_report_table_tpl->setContent($version_report_table->getHTML());

        // error report
        $error_report_tpl = new ilTemplate("tpl.error_report.html", true, true, $template_dir);
        $error_report_tpl->setVariable(
            "ERROR_REPORT_TITLE",
            sprintf($this->pl->txt("error_report_title"), $error_report['file_ref_id'])
        );
        $error_report_tpl->setVariable("VERSION_REPORT_TABLE", $version_report_table_tpl->get());

        // show error report
        $this->tpl->setContent($error_report_tpl->get());
    }


    /**
     * Return to plugin overview
     */
    private function cancel()
    {
        $this->ctrl->saveParameterByClass(ilAdministrationGUI::class, "ref_id");
        $this->ctrl->redirectByClass(
            array(
                ilAdministrationGUI::class,
                ilObjComponentSettingsGUI::class,
            ),
            "listPlugins"
        );
    }


    private function patch()
    {

    }


    private function downloadVersion()
    {
        $file = new ilObjFile($_GET[self::FILE_REF_ID]);
        $version = (int) $_GET[self::HIST_ID];
        $file->sendFile($version);
    }
}