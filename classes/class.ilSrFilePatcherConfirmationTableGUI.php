<?php

/* Copyright (c) 1998-2009 ILIAS open source, Extended GPL, see https://github.com/ILIAS-eLearning/ILIAS/tree/trunk/docs/LICENSE */

require_once __DIR__ . "/../vendor/autoload.php";

use ILIAS\Data\DataSize;
use ILIAS\Filesystem\Util\LegacyPathHelper;
use srag\Plugins\SrFilePatcher\Utils\SrFilePatcherTrait;
use srag\DIC\SrFilePatcher\DICTrait;

/**
 * Class ilSrFilePatcherConfirmationTableGUI
 *
 * @author  studer + raimann ag - Team Core 1 <support-core1@studer-raimann.ch>
 */
class ilSrFilePatcherConfirmationTableGUI extends ilTable2GUI
{

    use DICTrait;
    use SrFilePatcherTrait;
    const PLUGIN_CLASS_NAME = ilSrFilePatcherPlugin::class;
    /**
     * @var array
     */
    private $error_report_entries;
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
     * ilSrFilePatcherConfirmationTableGUI constructor.
     *
     * @param ilSrFilePatcherGUI $calling_gui_class
     * @param string             $a_parent_cmd
     * @param array              $a_error_report_entries
     */
    public function __construct(
        ilSrFilePatcherGUI $calling_gui_class,
        $a_parent_cmd = ilSrFilePatcherGUI::CMD_DEFAULT,
        $a_error_report_entries
    ) {
        $this->setId(self::class);
        parent::__construct($calling_gui_class, $a_parent_cmd, "");
        $this->error_report_entries = $a_error_report_entries;

        // General
        $this->log = self::dic()->logger()->root();
        $this->ctrl = self::dic()->ctrl();
        $this->lng = self::dic()->language();
        $this->pl = ilSrFilePatcherPlugin::getInstance();

        // Appearance
        $template_dir = "Customizing/global/plugins/Services/Cron/CronHook/SrFilePatcher";
        $this->setRowTemplate("tpl.confirmation_table_row.html", $template_dir);
        $this->setLimit(9999);

        // Form
        $this->setFormAction($this->ctrl->getFormAction($calling_gui_class));

        // Columns
        $this->addColumn($this->pl->txt("table_column_current_version"), "", "1");
        $this->addColumn($this->pl->txt("table_column_correct_version"), "", "1");
        $this->addColumn($this->lng->txt("date"));
        $this->addColumn($this->lng->txt("filename"));
        $this->addColumn($this->pl->txt("table_column_uploaded_by"));

        $this->setDefaultOrderField("version");
        $this->setDefaultOrderDirection("desc");

        $this->initData();
    }


    private function initData()
    {
        $this->setData($this->error_report_entries);
        $this->setMaxCount(is_array($this->error_report_entries) ? count($this->error_report_entries) : 0);
    }


    protected function fillRow($a_set)
    {
        // split params and prepare for output where needed
        $current_version = (int) $a_set["version"];
        $correct_version = (int) $a_set["correct_version"];
        $date = ilDatePresentation::formatDate(new ilDateTime($a_set['date'], IL_CAL_DATETIME));
        $filename = $a_set["filename"];

        // get user name
        $name = ilObjUser::_lookupName($a_set["user_id"]);
        $username = trim($name["title"] . " " . $name["firstname"] . " " . $name["lastname"]);

        // fill template
        $this->tpl->setVariable("TXT_CURRENT_VERSION", $current_version);
        $this->tpl->setVariable("TXT_CORRECT_VERSION", $correct_version);
        $this->tpl->setVariable("TXT_DATE", $date);
        $this->tpl->setVariable("TXT_FILENAME", $filename);
        $this->tpl->setVariable("TXT_UPLOADED_BY", $username);
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