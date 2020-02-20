<?php

/* Copyright (c) 1998-2009 ILIAS open source, Extended GPL, see https://github.com/ILIAS-eLearning/ILIAS/tree/trunk/docs/LICENSE */

require_once __DIR__ . "/../../vendor/autoload.php";

use srag\Plugins\SrFilePatcher\Utils\SrFilePatcherTrait;
use srag\DIC\SrFilePatcher\DICTrait;

/**
 * Class ilFilePatchConfirmationRequestGenerator
 *
 * @author  studer + raimann ag - Team Core 1 <support-core1@studer-raimann.ch>
 *
 * @ilCtrl_IsCalledBy   ilFilePatchConfirmationRequestGenerator: ilUIPluginRouterGUI
 */
class ilFilePatchConfirmationRequestGenerator
{

    use DICTrait;
    use SrFilePatcherTrait;
    const PLUGIN_CLASS_NAME = ilSrFilePatcherPlugin::class;
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
     * @var ilSrFilePatcherGUI
     */
    protected $parent;
    /**
     * @var ilSrFilePatcherPlugin
     */
    protected $pl;


    /**
     * ilFilePatchConfirmationRequestGenerator constructor.
     */
    public function __construct(ilSrFilePatcherGUI $a_parent)
    {
        $this->log = self::dic()->logger()->root();
        $this->ctrl = self::dic()->ctrl();
        $this->lng = self::dic()->language();
        $this->parent = $a_parent;
        $this->pl = ilSrFilePatcherPlugin::getInstance();
    }


    public function getRequestHTML() {
        // set request elements that are the same no matter the number of fixable versions
        $conf_gui = new ilConfirmationGUI();
        $conf_gui->setFormAction($this->ctrl->getFormAction($this->parent, ilSrFilePatcherGUI::CMD_DEFAULT));
        $conf_gui->setConfirm($this->lng->txt("confirm"), ilSrFilePatcherGUI::CMD_CONFIRMED_PATCH);
        $conf_gui->setCancel($this->lng->txt("cancel"), ilSrFilePatcherGUI::CMD_SHOW_ERROR_REPORT);
        $conf_gui->addHiddenItem("ref_id_file", $_POST['file_ref_id']);

        // ask for confirmation providing the information which versions will be patched and which ones will be marked as lost
        ilUtil::sendQuestion(sprintf($this->pl->txt("confirmation_question_patch"), $_POST['file_ref_id']));

        // determine which versions are fixable and which are not
        $file = new ilObjFile($_POST['file_ref_id']);
        $error_report_generator = new ilFileErrorReportGenerator($this->parent);
        $error_report = $error_report_generator->getReport($file);
        $fixable_versions = [];
        $unfixable_versions = [];
        foreach ($error_report as $error_report_entry) {
            if(isset($error_report_entry['patch_possible'])) {
                if($error_report_entry['patch_possible']) {
                    $fixable_versions[] = $error_report_entry;
                } else {
                    $unfixable_versions[] = $error_report_entry;
                }
            }
        }

        $confirmation_request_tpl = new ilTemplate(
            "tpl.file_patch_confirmation_request.html",
            true,
            true,
            ilSrFilePatcherGUI::TEMPLATE_DIR
        );
        $confirmation_request_tpl->setVariable(
            "CONFIRMATION_BUTTONS_TOP",
            $conf_gui->getHTML()
        );
        $confirmation_request_tpl->setVariable(
            "FIXABLE_VERSIONS_TABLE_TITLE",
            $this->pl->txt('confirmation_table_title_fixable_versions')
        );
        if(!empty($fixable_versions)) {
            // show versions that would be patched
            $fixable_versions_table = new ilFilePatchConfirmationRequestTableGUI(
                $this->parent,
                ilSrFilePatcherGUI::CMD_DEFAULT,
                $fixable_versions
            );
            $confirmation_request_tpl->setVariable(
                "FIXABLE_VERSIONS_TABLE",
                $fixable_versions_table->getHTML()
            );
        } else {
            $confirmation_request_tpl->setVariable(
                "FIXABLE_VERSIONS_TABLE",
                $this->pl->txt("confirmation_table_replacement_no_fixable_versions") . "<br><br>"
            );
        }
        $confirmation_request_tpl->setVariable(
            "UNFIXABLE_VERSIONS_TABLE_TITLE",
            $this->pl->txt('confirmation_table_title_unfixable_versions')
        );
        if(!empty($unfixable_versions)) {
            // show versions that would be marked as lost
            $unfixable_versions_table = new ilFilePatchConfirmationRequestTableGUI(
                $this->parent,
                ilSrFilePatcherGUI::CMD_DEFAULT,
                $unfixable_versions
            );
            $confirmation_request_tpl->setVariable(
                "UNFIXABLE_VERSIONS_TABLE",
                $unfixable_versions_table->getHTML()
            );
        } else {
            $confirmation_request_tpl->setVariable(
                "UNFIXABLE_VERSIONS_TABLE",
                $this->pl->txt("confirmation_table_replacement_no_unfixable_versions") . "<br><br>"
            );
        }
        $confirmation_request_tpl->setVariable(
            "CONFIRMATION_BUTTONS_BOTTOM",
            $conf_gui->getHTML()
        );

        return $confirmation_request_tpl->get();
    }
}