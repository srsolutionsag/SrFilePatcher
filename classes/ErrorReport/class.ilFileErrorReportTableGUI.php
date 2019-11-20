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
     * @var array
     */
    private $error_report;
    /**
     * int
     */
    private $file_ref_id;
    /**
     * string
     */
    private $file_dir;
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
     * ilFileErrorReportTableGUI constructor.
     *
     * @param ilSrFilePatcherGUI $calling_gui_class
     * @param string             $a_parent_cmd
     * @param array              $a_error_report
     * @param string             $a_file_dir
     */
    public function __construct(
        ilSrFilePatcherGUI $calling_gui_class,
        $a_parent_cmd = ilSrFilePatcherGUI::CMD_DEFAULT,
        $a_error_report,
        $a_file_dir
    ) {
        $this->setId(self::class);
        parent::__construct($calling_gui_class, $a_parent_cmd, "");
        $this->error_report = $a_error_report;
        if(isset($_POST['ref_id_file'])) {
            $this->file_ref_id = $_POST['ref_id_file'];
        } else {
            $this->file_ref_id = $_GET['ref_id_file'];
        }
        $this->file_dir = $a_file_dir;
        $this->current_version = (int) $this->error_report['db_current_version'];
        $this->max_version = (int) $this->error_report['db_current_max_version'];

        // General
        $this->log = self::dic()->logger()->root();
        $this->ctrl = self::dic()->ctrl();
        $this->lng = self::dic()->language();
        $this->pl = ilSrFilePatcherPlugin::getInstance();

        // Appearance
        $template_dir = "Customizing/global/plugins/Services/Cron/CronHook/SrFilePatcher";
        $this->setRowTemplate("tpl.version_report_table_row.html", $template_dir);
        $this->setLimit(9999);

        // Form
        $this->setFormAction($this->ctrl->getFormAction($calling_gui_class));

        // Columns
        $this->addColumn($this->pl->txt("table_column_current_version"), "", "1");
        $this->addColumn($this->pl->txt("table_column_correct_version"), "", "1");
        $this->addColumn($this->lng->txt("date"));
        $this->addColumn($this->lng->txt("filename"));
        $this->addColumn($this->pl->txt("table_column_uploaded_by"));
        $this->addColumn($this->pl->txt("table_column_current_path"));
        $this->addColumn($this->pl->txt("table_column_correct_path"));
        $this->addColumn($this->pl->txt("table_column_numbered_correctly"));
        $this->addColumn($this->pl->txt("table_column_stored_correctly"));
        $this->addColumn($this->pl->txt("table_column_folder_exists"));
        $this->addColumn($this->pl->txt("table_column_file_exists"));
        $this->addColumn($this->pl->txt("table_column_patch_possible"));

        $this->addHiddenInput("file_ref_id", $this->file_ref_id);

        $this->addCommandButton(ilSrFilePatcherGUI::CMD_PATCH, $this->pl->txt("table_cmd_button_patch"));
        $this->addCommandButton(ilSrFilePatcherGUI::CMD_DEFAULT, $this->lng->txt('cancel'));

        $this->setDefaultOrderField("version");
        $this->setDefaultOrderDirection("desc");

        $this->initData();
    }


    private function initData()
    {
        $this->setData($this->error_report);
        $this->setMaxCount(is_array($this->error_report) ? count($this->error_report) : 0);
    }


    protected function fillRow($a_set)
    {
        $yes = "<div style='color:darkgreen;'>" . $this->lng->txt('yes') . "</div>";
        $no = "<div style='color:darkred;'>" . $this->lng->txt('no') . "</div>";

        // split params and prepare for output where needed
        $hist_entry_id = $a_set["hist_entry_id"];
        $current_version = (int) $a_set["version"];
        $correct_version = (int) $a_set["correct_version"];
        $date = ilDatePresentation::formatDate(new ilDateTime($a_set['date'], IL_CAL_DATETIME));
        $filename = $a_set["filename"];
        $current_path = ".../" . str_replace($this->file_dir, "", $a_set["current_path"]);
        $correct_path = ".../" . str_replace($this->file_dir, "", $a_set["correct_path"]);
        $numbered_correctly = ($a_set['numbered_correctly'] == true ? $yes : $no);
        $stored_correctly = ($a_set['stored_correctly'] == true ? $yes : $no);
        $folder_exists = ($a_set['folder_exists'] == true ? $yes : $no);
        $file_exists = ($a_set['file_exists'] == true ? $yes : $no);
        $patch_possible = ($a_set['patch_possible'] == true ? $yes : $no);

        // get download link for file version
        $this->ctrl->setParameter($this->parent_obj, ilSrFilePatcherGUI::FILE_REF_ID, $this->file_ref_id);
        $this->ctrl->setParameter($this->parent_obj, ilSrFilePatcherGUI::HIST_ID, $hist_entry_id);
        $link = $this->ctrl->getLinkTarget($this->parent_obj, ilSrFilePatcherGUI::CMD_DOWNLOAD_VERSION);
        // reset history parameter
        $this->ctrl->setParameter($this->parent_obj, ilSrFilePatcherGUI::HIST_ID, "");

        // get user name
        $name = ilObjUser::_lookupName($a_set["user_id"]);
        $username = trim($name["title"] . " " . $name["firstname"] . " " . $name["lastname"]);

        // fill template
        $this->tpl->setVariable("TXT_CURRENT_VERSION", $current_version);
        $this->tpl->setVariable("TXT_CORRECT_VERSION", $correct_version);
        $this->tpl->setVariable("TXT_DATE", $date);
        // only create a link if the file exists
        if ($a_set['file_exists']) {
            $link_opening_tag = "<a href=\"$link\">";
            $link_closing_tag = "</a>";
            $this->tpl->setVariable("LINK_OPENING_TAG", $link_opening_tag);
            $this->tpl->setVariable("LINK_CLOSING_TAG", $link_closing_tag);
        }
        $this->tpl->setVariable("TXT_FILENAME", $filename);
        $this->tpl->setVariable("TXT_UPLOADED_BY", $username);
        $this->tpl->setVariable("TXT_CURRENT_PATH", $current_path);
        $this->tpl->setVariable("TXT_CORRECT_PATH", $correct_path);
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