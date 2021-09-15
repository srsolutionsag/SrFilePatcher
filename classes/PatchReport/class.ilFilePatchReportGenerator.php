<?php

/* Copyright (c) 1998-2009 ILIAS open source, Extended GPL, see https://github.com/ILIAS-eLearning/ILIAS/tree/trunk/docs/LICENSE */

require_once __DIR__ . "/../../vendor/autoload.php";

use srag\Plugins\SrFilePatcher\Utils\SrFilePatcherTrait;
use srag\DIC\SrFilePatcher\DICTrait;

/**
 * Class ilFilePatchReportGenerator
 *
 * @author  studer + raimann ag - Team Core 1 <support-core1@studer-raimann.ch>
 *
 * @ilCtrl_IsCalledBy   ilFilePatchReportGenerator: ilUIPluginRouterGUI
 */
class ilFilePatchReportGenerator
{

    use DICTrait;
    use SrFilePatcherTrait;
    const PLUGIN_CLASS_NAME = ilSrFilePatcherPlugin::class;
    /**
     * @var ilCtrl
     */
    private $ctrl;
    /**
     * @var array
     */
    private $previous_error_report;
    /**
     * @var ilLanguage
     */
    private $lng;
    /**
     * @var ilComponentLogger
     */
    private $log;
    /**
     * @var ilSrFilePatcherGUI
     */
    protected $parent;
    /**
     * @var ilSrFilePatcherPlugin
     */
    protected $pl;


    /**
     * ilFileErrorReportGenerator constructor.
     */
    public function __construct(ilSrFilePatcherGUI $a_parent, array $a_previous_error_report)
    {
        $this->log = self::dic()->logger()->root();
        $this->ctrl = self::dic()->ctrl();
        $this->lng = self::dic()->language();
        $this->parent = $a_parent;
        $this->previous_error_report = $a_previous_error_report;
        $this->pl = ilSrFilePatcherPlugin::getInstance();
    }


    /**
     * @param ilObjFile $a_file
     * @param array     $a_previous_error_report
     *
     * @return array
     */
    public function getReport(ilObjFile $a_file)
    {
        $versions = $this->parent->getVersionsFromHistoryOfFile($a_file);

        $report = [];
        // store file ref_id in report to preserve the information regarding which file the report concerns
        $report['file_ref_id'] = $a_file->getRefId();
        // add data concerning the individual file versions to the report
        foreach ($versions as $version) {
            $hist_entry_id = $version['hist_entry_id'];

            $report[$hist_entry_id]['hist_entry_id'] = $hist_entry_id;
            $report[$hist_entry_id]['version'] = $version['version'];
            $report[$hist_entry_id]['previous_version'] = $this->previous_error_report[$hist_entry_id]['version'];
            $report[$hist_entry_id]['correct_version'] = $this->previous_error_report[$hist_entry_id]['correct_version'];
            $report[$hist_entry_id]['rollback_version'] = $version['rollback_version'];
            $report[$hist_entry_id]['rollback_user_id'] = $version['rollback_user_id'];
            $report[$hist_entry_id]['date'] = $version['date'];
            $report[$hist_entry_id]['filename'] = $version['filename'];
            $report[$hist_entry_id]['user_id'] = $version['user_id'];
            $report[$hist_entry_id]['patched_path'] = $this->getPatchedPathForVersion($version, $a_file);
            $report[$hist_entry_id]['previous_path'] = $this->previous_error_report[$hist_entry_id]['current_path'];
            $report[$hist_entry_id]['correct_path'] = $this->previous_error_report[$hist_entry_id]['correct_path'];
            $report[$hist_entry_id]['file_exists'] = $this->previous_error_report[$hist_entry_id]['file_exists'];
            $report[$hist_entry_id]['type'] = $version['action'];
        }
        // add db-data to record
        $report['db_patched_version'] = $a_file->getVersion();
        $report['db_previous_version'] = $this->previous_error_report['db_current_version'];
        $report['db_correct_version'] = $this->previous_error_report['db_correct_version'];
        $report['db_patched_max_version'] = $a_file->getMaxVersion();
        $report['db_previous_max_version'] = $this->previous_error_report['db_current_max_version'];
        $report['db_correct_max_version'] = $this->previous_error_report['db_correct_max_version'];

        return $report;
    }


    public function getReportHTML() {
        if(isset($_POST['ref_id_file'])) {
            $ref_id_file = $_POST['ref_id_file'];
        } else {
            $ref_id_file = $_GET['ref_id_file'];
        }
        $file = new ilObjFile($ref_id_file);

        // patch report
        $patch_report = $this->getReport($file);
        $patch_report_tpl = new ilTemplate("tpl.report.html", true, true, ilSrFilePatcherGUI::TEMPLATE_DIR);
        $patch_report_tpl->setVariable(
            "REPORT_TITLE",
            sprintf($this->pl->txt("patch_report_title"), $patch_report['file_ref_id'])
        );

        // db report
        $db_report_tpl = new ilTemplate("tpl.file_patch_report_db_section.html", true, true, ilSrFilePatcherGUI::TEMPLATE_DIR);
        $db_report_tpl->setVariable("DB_REPORT_TITLE", $this->pl->txt("report_title_db_report"));
        $db_report_tpl->setVariable(
            "DB_REPORT_LABEL_PATCHED_VERSION",
            $this->pl->txt("report_label_db_patched_version") . ":"
        );
        $db_report_tpl->setVariable("DB_REPORT_CONTENT_PATCHED_VERSION", $patch_report['db_patched_version']);
        $db_report_tpl->setVariable(
            "DB_REPORT_LABEL_PREVIOUS_VERSION",
            $this->pl->txt("report_label_db_previous_version") . ":"
        );
        $db_report_tpl->setVariable("DB_REPORT_CONTENT_PREVIOUS_VERSION", $patch_report['db_previous_version']);
        $db_report_tpl->setVariable(
            "DB_REPORT_LABEL_CORRECT_VERSION",
            $this->pl->txt("report_label_db_correct_version") . ":"
        );
        $db_report_tpl->setVariable("DB_REPORT_CONTENT_CORRECT_VERSION", $patch_report['db_correct_version']);
        $db_report_tpl->setVariable(
            "DB_REPORT_LABEL_PATCHED_MAX_VERSION",
            $this->pl->txt("report_label_db_patched_max_version") . ":"
        );
        $db_report_tpl->setVariable("DB_REPORT_CONTENT_PATCHED_MAX_VERSION", $patch_report['db_patched_max_version']);
        $db_report_tpl->setVariable(
            "DB_REPORT_LABEL_PREVIOUS_MAX_VERSION",
            $this->pl->txt("report_label_db_previous_max_version") . ":"
        );
        $db_report_tpl->setVariable("DB_REPORT_CONTENT_PREVIOUS_MAX_VERSION", $patch_report['db_previous_max_version']);
        $db_report_tpl->setVariable(
            "DB_REPORT_LABEL_CORRECT_MAX_VERSION",
            $this->pl->txt("report_label_db_correct_max_version") . ":"
        );
        $db_report_tpl->setVariable("DB_REPORT_CONTENT_CORRECT_MAX_VERSION", $patch_report['db_correct_max_version']);

        // remove db-data and ref_id from patch_report to prevent problems in reading the array when passing it on to the table
        unset($patch_report['file_ref_id']);
        unset($patch_report['db_patched_version']);
        unset($patch_report['db_previous_version']);
        unset($patch_report['db_correct_version']);
        unset($patch_report['db_patched_max_version']);
        unset($patch_report['db_previous_max_version']);
        unset($patch_report['db_correct_max_version']);

        // version report table
        $fs_storage_file = new ilFSStorageFile();
        $file_absolute_path = $fs_storage_file->getAbsolutePath();
        $file_dir = substr($file_absolute_path, 0, (strpos($file_absolute_path, "ilFile/") + 7));
        $version_report_table = new ilFilePatchReportTableGUI(
            $this->parent,
            ilSrFilePatcherGUI::CMD_DEFAULT,
            $patch_report,
            $file_dir
        );
        $version_report_table_tpl = new ilTemplate("tpl.report_table.html", true, true, ilSrFilePatcherGUI::TEMPLATE_DIR);
        $version_report_table->setTemplate($version_report_table_tpl);
        $version_report_table_tpl->setVariable("DB_REPORT", $db_report_tpl->get());
        $version_report_table_tpl->setVariable(
            "REPORT_TABLE_TITLE",
            $this->pl->txt("report_title_version_report")
        );
        $version_report_table_tpl->setVariable(
            "REPORT_TABLE_LABEL_FILE_DIR",
            $this->pl->txt("report_label_file_dir") . ":"
        );
        $version_report_table_tpl->setVariable("REPORT_TABLE_CONTENT_FILE_DIR", ($file_dir . "..."));
        $version_report_table_tpl->setVariable(
            "REPORT_TABLE_INFO_FILE_DIR",
            $this->pl->txt("report_info_file_dir")
        );
        $version_report_table_tpl->setVariable('TBL_CONTENT', $version_report_table->getHTML());
        $patch_report_tpl->setVariable("REPORT_TABLE", $version_report_table_tpl->get());

        return $patch_report_tpl->get();
    }


    private function getPatchedPathForVersion(array $a_version, ilObjFile $a_file)
    {
        $file_directory = $a_file->getDirectory();
        $file_name = $a_version['filename'];
        $sub_directories = glob($file_directory . "/*", GLOB_ONLYDIR);

        $patched_path = "-";
        foreach ($sub_directories as $sub_directory) {
            $directory_version = (string) (int) str_replace($file_directory . "/", "", $sub_directory);
            if ($a_version['version'] === $directory_version) {
                $sub_directory = str_replace("//", "/", $sub_directory);
                $patched_path = $sub_directory . "/" . $file_name;
            }
        }

        return $patched_path;
    }
}
