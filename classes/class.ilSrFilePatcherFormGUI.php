<?php

/* Copyright (c) 1998-2009 ILIAS open source, Extended GPL, see https://github.com/ILIAS-eLearning/ILIAS/tree/trunk/docs/LICENSE */

require_once __DIR__ . "/../vendor/autoload.php";

use srag\Plugins\SrFilePatcher\Utils\SrFilePatcherTrait;
use srag\DIC\SrFilePatcher\DICTrait;

/**
 * Class ilSrFilePatcherFormGUI
 *
 * @author  studer + raimann ag - Team Core 1 <support-core1@studer-raimann.ch>
 */
class ilSrFilePatcherFormGUI extends ilPropertyFormGUI
{
    use DICTrait;
    use SrFilePatcherTrait;
    const PLUGIN_CLASS_NAME = ilSrFilePatcherPlugin::class;

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
     * @var ilSrFilePatcherGUI
     */
    protected $parent_gui;
    /**
     * @var ilSrFilePatcherPlugin
     */
    protected $pl;
    /**
     * @var ilTemplate
     */
    protected $tpl;


    public function __construct(ilSrFilePatcherGUI $a_parent_gui) {
        parent::__construct();
        $this->ctrl = self::dic()->ctrl();
        $this->lng = self::dic()->language();
        $this->log = self::dic()->logger()->root();
        $this->parent_gui = $a_parent_gui;
        $this->pl = ilSrFilePatcherPlugin::getInstance();
        $this->tpl = self::dic()->ui()->mainTemplate();

        $this->initForm();
        $this->fillForm();
    }


    /**
     * Initializes the test patcher form
     */
    protected function initForm() {
        $this->setFormAction($this->ctrl->getFormAction($this->parent_gui));
        $this->setTitle($this->pl->txt('form_title_single_file_patcher'));

        // input field for ref-id of file which will be used for testing the effects of the plugin
        $input_ref_id = new ilNumberInputGUI($this->pl->txt("form_input_ref_id_file"), "ref_id_file");
        $input_ref_id->setRequired(true);
        $this->addItem($input_ref_id);

        // Save and cancel buttons
        $this->ctrl->saveParameterByClass(ilSrFilePatcherGUI::class, "ref_id");
        $this->addCommandButton(ilSrFilePatcherGUI::CMD_VALIDATE_FORM, $this->pl->txt('form_cmd_button_show_report'));
        $this->addCommandButton(ilSrFilePatcherGUI::CMD_CANCEL, $this->lng->txt('cancel'));
    }


    /**
     * Fills form with post-values
     */
    private function fillForm() {
        if(!empty($_POST)) {
            $this->setValuesByPost();
        }
    }


    public function isValid() {
        $is_valid = false;

        if($this->checkInput()) {
            $ref_id_file = (int)$this->getInput("ref_id_file");
            if(ilObjFile::_exists($ref_id_file, true, "file")) {
                $is_valid = true;
            } else {
                /**
                 * @var $input_ref_id_file ilNumberInputGUI
                 */
                $input_ref_id_file = $this->getItemByPostVar("ref_id_file");
                $input_ref_id_file->setAlert(sprintf($this->pl->txt("error_no_file_for_ref_id"), $ref_id_file));
                ilUtil::sendFailure($this->lng->txt("form_input_not_valid"));
            }
        }

        return $is_valid;
    }
}