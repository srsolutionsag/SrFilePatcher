<?php

/* Copyright (c) 1998-2009 ILIAS open source, Extended GPL, see https://github.com/ILIAS-eLearning/ILIAS/tree/trunk/docs/LICENSE */

require_once __DIR__ . "/../../vendor/autoload.php";

use srag\Plugins\SrFilePatcher\Utils\SrFilePatcherTrait;
use srag\DIC\SrFilePatcher\DICTrait;

/**
 * Class ilFileErrorReportTableGUI
 *
 * @author  studer + raimann ag - Team Core 1 <support-core1@studer-raimann.ch>
 */
class ilFileErrorReportTableGUI extends ilTable2GUI
{

    use DICTrait;
    use SrFilePatcherTrait;
    const PLUGIN_CLASS_NAME = ilSrFilePatcherPlugin::class;
    /**
     * @var int
     */
    private $max_version;
    /**
     * @var int
     */
    private $current_version;
    /**
     * @var ilObjFile
     */
    private $file;
    /**
     * @var ilCtrl
     */
    protected $ctrl;
    /**
     * @var ilLanguage
     */
    protected $lng;
    /**
     * @var ilComponentLogger
     */
    protected $log;
    /**
     * @var ilSrFilePatcherPlugin
     */
    protected $pl;


    /**
     * ilFileVersionsTableGUI constructor.
     *
     * @param ilFileVersionsGUI $calling_gui_class
     * @param string            $a_parent_cmd
     */
    public function __construct(
        ilSrFilePatcherGUI $calling_gui_class,
        $a_parent_cmd = ilSrFilePatcherGUI::CMD_DEFAULT,
        $a_file_ref_id
    ) {
        $this->setId(self::class);
        parent::__construct($calling_gui_class, $a_parent_cmd, "");
        $this->file = new ilObjFile($a_file_ref_id);
        $this->current_version = (int) $this->file->getVersion();
        $this->max_version = (int) $this->file->getMaxVersion();

        // General
        $this->log = self::dic()->logger()->root();
        $this->ctrl = self::dic()->ctrl();
        $this->lng = self::dic()->language();
        $this->pl = ilSrFilePatcherPlugin::getInstance();

        // Appearance
        $this->setTitle(sprintf($this->pl->txt("table_title_error_report"), $a_file_ref_id));
        $template_dir = "Customizing/global/plugins/Services/Cron/CronHook/SrFilePatcher";
        $this->setRowTemplate("tpl.file_error_report_row.html", $template_dir);
        $this->setLimit(9999);

        // Form
        $this->setFormAction($this->ctrl->getFormAction($calling_gui_class));

        // Columns
        $this->addColumn($this->lng->txt("version"), "", "1");
        $this->addColumn($this->lng->txt("date"));
        $this->addColumn($this->lng->txt("filename"));
        $this->addColumn($this->pl->txt("table_column_numbered_correctly"));
        $this->addColumn($this->pl->txt("table_column_stored_correctly"));
        $this->addColumn($this->pl->txt("table_column_folder_exists"));
        $this->addColumn($this->pl->txt("table_column_file_exists"));
        $this->addColumn($this->pl->txt("table_column_patch_possible"));

        $this->addCommandButton(ilSrFilePatcherGUI::CMD_PATCH, $this->pl->txt("table_cmd_button_patch"));
        $this->addCommandButton(ilSrFilePatcherGUI::CMD_DEFAULT, $this->lng->txt('cancel'));

        $this->setDefaultOrderField("version");
        $this->setDefaultOrderDirection("desc");

        $this->initData();
    }


    private function initData()
    {
        $report_generator = new ilFileErrorReportGenerator();
        $report = $report_generator->getReport($this->file);
        $this->setData($report);
        $this->setMaxCount(is_array($report) ? count($report) : 0);
    }


    protected function fillRow($a_set)
    {
        $yes = "<div style='color:darkgreen;'>" . $this->lng->txt('yes') . "</div>";
        $no = "<div style='color:darkred;'>" . $this->lng->txt('no') . "</div>";

        // split params and prepare for output where needed
        $hist_entry_id = $a_set["hist_entry_id"];
        $version = (int) $a_set["version"];
        $date = ilDatePresentation::formatDate(new ilDateTime($a_set['date'], IL_CAL_DATETIME));
        $filename = $a_set["filename"];
        $numbered_correctly = ($a_set['numbered_correctly'] == true ? $yes : $no);
        $stored_correctly = ($a_set['stored_correctly'] == true ? $yes : $no);
        $folder_exists = ($a_set['folder_exists'] == true ? $yes : $no);
        $file_exists = ($a_set['file_exists'] == true ? $yes : $no);
        $patch_possible = ($a_set['patch_possible'] == true ? $yes : $no);

        // get download link for file version
        $this->ctrl->setParameter($this->parent_obj, 'hist_id', $hist_entry_id);
        $dl_link = $this->ctrl->getLinkTarget($this->parent_obj, ilFileVersionsGUI::CMD_DOWNLOAD_VERSION);
        // reset history parameter
        $this->ctrl->setParameter($this->parent_obj, 'hist_id', "");

        // fill template
        $this->tpl->setVariable("TXT_VERSION", $version);
        $this->tpl->setVariable("TXT_DATE", $date);
        $this->tpl->setVariable("DL_LINK", $dl_link);
        $this->tpl->setVariable("TXT_FILENAME", $filename);
        $this->tpl->setVariable("TXT_NUMBERED_CORRECTLY", $numbered_correctly);
        $this->tpl->setVariable("TXT_STORED_CORRECTLY", $stored_correctly);
        $this->tpl->setVariable("TXT_FOLDER_EXISTS", $folder_exists);
        $this->tpl->setVariable("TXT_FILE_EXISTS", $file_exists);
        $this->tpl->setVariable("TXT_PATCH_POSSIBLE", $patch_possible);
    }


    function numericOrdering($a_field)
    {
        if ($a_field === "version") {
            return true;
        } else {
            return false;
        }
    }
}