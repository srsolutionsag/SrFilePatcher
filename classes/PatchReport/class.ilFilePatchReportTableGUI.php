<?php

/* Copyright (c) 1998-2009 ILIAS open source, Extended GPL, see https://github.com/ILIAS-eLearning/ILIAS/tree/trunk/docs/LICENSE */

require_once __DIR__ . "/../../vendor/autoload.php";

use srag\Plugins\SrFilePatcher\Utils\SrFilePatcherTrait;
use srag\DIC\SrFilePatcher\DICTrait;

/**
 * Class ilFilePatchReportTableGUI
 *
 * @author  studer + raimann ag - Team Core 1 <support-core1@studer-raimann.ch>
 */
class ilFilePatchReportTableGUI extends ilTable2GUI
{

    use DICTrait;
    use SrFilePatcherTrait;
    const PLUGIN_CLASS_NAME = ilSrFilePatcherPlugin::class;
    /**
     * @var array
     */
    private $patch_report;
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
     * @param array              $a_patch_report
     * @param string             $a_file_dir
     */
    public function __construct(
        ilSrFilePatcherGUI $calling_gui_class,
        $a_parent_cmd = ilSrFilePatcherGUI::CMD_DEFAULT,
        $a_patch_report,
        $a_file_dir
    ) {
        $this->setId(self::class);
        parent::__construct($calling_gui_class, $a_parent_cmd, "");
        $this->patch_report = $a_patch_report;
        if(isset($_POST['ref_id_file'])) {
            $this->file_ref_id = $_POST['ref_id_file'];
        } else {
            $this->file_ref_id = $_GET['ref_id_file'];
        }
        $this->file_dir = $a_file_dir;

        // General
        $this->log = self::dic()->logger()->root();
        $this->ctrl = self::dic()->ctrl();
        $this->lng = self::dic()->language();
        $this->pl = ilSrFilePatcherPlugin::getInstance();

        // Appearance
        $template_dir = "Customizing/global/plugins/Services/Cron/CronHook/SrFilePatcher";
        $this->setRowTemplate("tpl.file_patch_report_table_row.html", $template_dir);
        $this->setLimit(9999);

        // Form
        $this->setFormAction($this->ctrl->getFormAction($calling_gui_class));

        // Columns
        $this->addColumn($this->pl->txt("table_column_patched_version"), "", "1");
        $this->addColumn($this->pl->txt("table_column_previous_version"), "", "1");
        $this->addColumn($this->pl->txt("table_column_correct_version"), "", "1");
        $this->addColumn($this->lng->txt("date"));
        $this->addColumn($this->lng->txt("filename"));
        $this->addColumn($this->pl->txt("table_column_uploaded_by"));
        $this->addColumn($this->pl->txt("table_column_patched_path"));
        $this->addColumn($this->pl->txt("table_column_previous_path"));
        $this->addColumn($this->pl->txt("table_column_correct_path"));
        $this->addColumn($this->pl->txt("table_column_file_exists"));
        $this->addColumn($this->lng->txt("type"));

        $this->addHiddenInput("file_ref_id", $this->file_ref_id);

        $this->addCommandButton(ilSrFilePatcherGUI::CMD_DEFAULT, $this->lng->txt("ok"));

        $this->setDefaultOrderField("version");
        $this->setDefaultOrderDirection("desc");

        $this->initData();
    }


    private function initData()
    {
        $this->setData($this->patch_report);
        $this->setMaxCount(is_array($this->patch_report) ? count($this->patch_report) : 0);
    }


    protected function fillRow($a_set)
    {
        $yes = "<div style='color:darkgreen;'>" . $this->lng->txt('yes') . "</div>";
        $no = "<div style='color:darkred;'>" . $this->lng->txt('no') . "</div>";

        // split params and prepare for output where needed
        $hist_entry_id = $a_set["hist_entry_id"];
        $patched_version = (int) $a_set["version"];
        $previous_version = (int) $a_set["previous_version"];
        $correct_version = (int) $a_set["correct_version"];
        $rollback_version = $a_set["rollback_version"];
        $rollback_user_id = $a_set["rollback_user_id"];
        $date = ilDatePresentation::formatDate(new ilDateTime($a_set["date"], IL_CAL_DATETIME));
        $filename = $a_set["filename"];
        $patched_path = ".../" . str_replace($this->file_dir, "", $a_set["patched_path"]);
        $previous_path = ".../" . str_replace($this->file_dir, "", $a_set["previous_path"]);
        $correct_path = ".../" . str_replace($this->file_dir, "", $a_set["correct_path"]);
        $file_exists = ($a_set['file_exists'] == true ? $yes : $no);

        // get download link for file version
        $this->ctrl->setParameter($this->parent_obj, ilSrFilePatcherGUI::FILE_REF_ID, $this->file_ref_id);
        $this->ctrl->setParameter($this->parent_obj, ilSrFilePatcherGUI::HIST_ID, $hist_entry_id);
        $link = $this->ctrl->getLinkTarget($this->parent_obj, ilSrFilePatcherGUI::CMD_DOWNLOAD_VERSION);
        // reset history parameter
        $this->ctrl->setParameter($this->parent_obj, ilSrFilePatcherGUI::HIST_ID, "");

        // get user name
        $name = ilObjUser::_lookupName($a_set["user_id"]);
        $username = trim($name["title"] . " " . $name["firstname"] . " " . $name["lastname"]);

        // get type
        $type = $this->lng->txt("file_version_" . $a_set["type"]); // create, replace, new_version, rollback
        if ($a_set["type"] == "rollback") {
            $rollback_name = ilObjUser::_lookupName($rollback_user_id);
            $rollback_username = trim(
                $rollback_name["title"] . " "
                . $rollback_name["firstname"] . " "
                . $rollback_name["lastname"]
            );
            $type = sprintf($type, $rollback_version, $rollback_username);
        }

        // fill template
        $this->tpl->setVariable("TXT_PATCHED_VERSION", $patched_version);
        $this->tpl->setVariable("TXT_PREVIOUS_VERSION", $previous_version);
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
        $this->tpl->setVariable("TXT_PATCHED_PATH", $patched_path);
        $this->tpl->setVariable("TXT_PREVIOUS_PATH", $previous_path);
        $this->tpl->setVariable("TXT_CORRECT_PATH", $correct_path);
        $this->tpl->setVariable("TXT_FILE_EXISTS", $file_exists);
        $this->tpl->setVariable("TXT_TYPE", $type);
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