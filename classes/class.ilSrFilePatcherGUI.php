<?php

/* Copyright (c) 1998-2009 ILIAS open source, Extended GPL, see https://github.com/ILIAS-eLearning/ILIAS/tree/trunk/docs/LICENSE */

require_once __DIR__ . "/../vendor/autoload.php";

use srag\Plugins\SrFilePatcher\Utils\SrFilePatcherTrait;
use srag\DIC\SrFilePatcher\DICTrait;
use srag\Plugins\SrFilePatcher\Access\Access;

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
    const TEMP_APPENDIX = '.temporary_appendix_to_prevent_overwriting';
    const TEMPLATE_DIR = 'Customizing/global/plugins/Services/Cron/CronHook/SrFilePatcher';
    const CMD_DEFAULT = "index";
    const CMD_VALIDATE_FORM = "validateForm";
    const CMD_SHOW_ERROR_REPORT = "showErrorReport";
    const CMD_CANCEL = "cancel";
    const CMD_PATCH = "patch";
    const CMD_DOWNLOAD_VERSION = "downloadVersion";
    const CMD_SHOW_CONFIRMATION_REQUEST = "showConfirmationRequest";
    const CMD_CANCEL_PATCH = "cancelPatch";
    const CMD_CONFIRMED_PATCH = "confirmPatch";
    /**
     * @var ilCtrl
     */
    private $ctrl;
    /**
     * @var ilDBInterface
     */
    private $db;
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


    /**
     * ilSrFilePatcherGUI constructor.
     */
    public function __construct()
    {
        $this->ctrl = self::dic()->ctrl();
        $this->db = self::dic()->database();
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
                    case self::CMD_VALIDATE_FORM:
                    case self::CMD_SHOW_ERROR_REPORT:
                    case self::CMD_CANCEL:
                    case self::CMD_PATCH:
                    case self::CMD_DOWNLOAD_VERSION:
                    case self::CMD_CANCEL_PATCH:
                    case self::CMD_CONFIRMED_PATCH:
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


    private function index(ilSrFilePatcherFormGUI $a_existing_form = null)
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

        if($a_existing_form === null) {
            $form = new ilSrFilePatcherFormGUI($this);
        } else {
            $form = $a_existing_form;
        }
        $form->setValuesByPost();
        $this->tpl->setContent($form->getHTML());

    }


    private function validateForm() {
        $file_patcher_form = new ilSrFilePatcherFormGUI($this);

        if($file_patcher_form->isValid()) {
            $this->showErrorReport();
        } else {
            $this->index($file_patcher_form);
        }
    }


    private function showErrorReport()
    {
        // back-tab
        $this->tabs->clearTargets();
        $this->ctrl->saveParameterByClass(ilSrFilePatcherGUI::class, "ref_id");
        $link_target = $this->ctrl->getLinkTargetByClass(ilSrFilePatcherGUI::class);
        $this->tabs->setBackTarget($this->lng->txt("back"), $link_target);

        $error_report_generator = new ilFileErrorReportGenerator($this);
        $error_report_html = $error_report_generator->getReportHTML();
        $this->tpl->setContent($error_report_html);
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
        $this->showConfirmationRequest();
    }


    private function showConfirmationRequest() {
        // back-tab
        $this->tabs->clearTargets();
        $this->ctrl->setParameterByClass(self::class, "ref_id_file", $_POST['file_ref_id']);
        $link_target = $this->ctrl->getLinkTargetByClass(self::class, self::CMD_SHOW_ERROR_REPORT);
        $this->tabs->setBackTarget($this->lng->txt("back"), $link_target);

        $patch_confirmation_request_generator = new ilFilePatchConfirmationRequestGenerator($this);
        $patch_confirmation_request_html = $patch_confirmation_request_generator->getRequestHTML();
        $this->tpl->setContent($patch_confirmation_request_html);
    }


    private function cancelPatch() {
        $this->ctrl->setParameterByClass(ilSrFilePatcherGUI::class, "ref_id_file", $_POST['file_ref_id']);
        $this->ctrl->redirectByClass(self::class, self::CMD_SHOW_ERROR_REPORT);
    }


    private function confirmPatch() {
        // error report needs to be generated again as the confirmation-gui's hidden input can't send arrays
        $file = new ilObjFile($_POST['ref_id_file']);
        $error_report_generator = new ilFileErrorReportGenerator($this);
        $error_report = $error_report_generator->getReport($file);

        $this->fixDatabaseEntryOfFile($file, $error_report);
        $this->fixHistoryEntriesOfFile($file, $error_report);
        $this->fixFilesystemVersionsOfFile($file, $error_report);

        ilUtil::sendSuccess(sprintf($this->pl->txt("success_file_patched"), $_POST['ref_id_file']), true);
        $patch_report_generator = new ilFilePatchReportGenerator($this, $error_report);
        $patch_report_html = $patch_report_generator->getReportHTML();
        $this->tpl->setContent($patch_report_html);
    }


    /**
     * @param ilObjFile $a_file
     * @param array     $a_error_report
     */
    private function fixDatabaseEntryOfFile(ilObjFile &$a_file, array $a_error_report) {
        $a_file->setVersion($a_error_report['db_correct_version']);
        $a_file->setMaxVersion($a_error_report['db_correct_max_version']);
        $a_file->update();
    }


    /**
     * @param ilObjFile $a_file
     * @param array     $a_error_report
     */
    private function fixHistoryEntriesOfFile(ilObjFile $a_file, array &$a_error_report) {
        $broken_history_entries = $this->getVersionsFromHistoryOfFile($a_file);

        foreach ($broken_history_entries as $broken_history_entry) {
            $hist_entry_id = $broken_history_entry['hist_entry_id'];
            $patched_comment = "patched: " . ilUtil::now();
            foreach ($a_error_report as $error_report_entry) {
                if(isset($error_report_entry['hist_entry_id'])
                    && ($error_report_entry['hist_entry_id']) == $broken_history_entry['hist_entry_id']) {
                    // fix the history entry if a patch is possible or mark the version as lost otherwise
                    if($error_report_entry['patch_possible']) {
                        // obtain data for fixing the history entry
                        $fixed_info_params = $this->getFixedInfoParams($broken_history_entry, $error_report_entry);
                        // fix history entry with correct info params and mark it as patched
                        $update_query_info_params =
                            "UPDATE history SET info_params = " . $this->db->quote($fixed_info_params, "text")
                            . ", user_comment = " . $this->db->quote($patched_comment, "text")
                            . "WHERE id = " . $this->db->quote($hist_entry_id, "integer");
                        $this->db->query($update_query_info_params);
                    } else {
                        // mark version as lost but still fix history versions to prevent duplicates from showing in version view
                        $fixed_info_params = $this->getFixedInfoParams($broken_history_entry, $error_report_entry);
                        $update_query_action =
                            "UPDATE history SET action = " . $this->db->quote("lost", "text")
                            . ", info_params = " . $this->db->quote($fixed_info_params, "text")
                            . ", user_comment = " . $this->db->quote($patched_comment, "text")
                            . "WHERE id = " . $this->db->quote($hist_entry_id, "integer");
                        $this->db->query($update_query_action);
                    }
                }
            }
        }
    }


    /**
     * @param ilObjFile $a_file
     * @param array     $a_error_report
     */
    private function fixFilesystemVersionsOfFile(ilObjFile $a_file, array $a_error_report) {
        $error_report_entries = $a_error_report;

        foreach ($error_report_entries as $error_report_entry) {
            // only try to fix the version if a patch is possible i.e. the file-version wasn't deleted
            if(isset($error_report_entry['patch_possible']) && $error_report_entry['patch_possible']) {
                $current_path = $error_report_entry['current_path'];
                $correct_path = $error_report_entry['correct_path'];

                $correct_path_without_filename = str_replace($error_report_entry['filename'], "", $correct_path);
                // create directories of correct path if they don't yet exist
                if(!is_dir($correct_path_without_filename)) {
                    mkdir($correct_path_without_filename, 0755, true);
                }

                // rename the file to be moved in case a name-duplicate exists in its correct path to prevent overwriting.
                // (the renaming will be undone once the name duplicate has been moved too)
                if(file_exists($correct_path)) {
                    $correct_path .= self::TEMP_APPENDIX;
                }

                // move the file to its correct location
                rename($current_path, $correct_path);
            }
        }

        // remove the temporary appendixes that were used to prevent overwriting and delete no longer needed directories
        $this->cleanUpFilesystemVersionsOfFile($a_file, $a_error_report);
    }


    /**
     * @param ilObjFile $a_file
     * @param array     $a_error_report
     */
    private function cleanUpFilesystemVersionsOfFile(ilObjFile $a_file, array $a_error_report) {
        $file_directory = $a_file->getDirectory();
        $version_directories = glob($file_directory . "*", GLOB_ONLYDIR);

        // iterate through the version directories
        foreach ($version_directories as $version_directory) {
            // remove temporary appendixes
            $files_with_appendix = glob($version_directory . "/*" . self::TEMP_APPENDIX . "*");
            if (count($files_with_appendix) > 0) {
                foreach ($files_with_appendix as $file_with_appendix) {
                    $file_without_appendix = str_replace(self::TEMP_APPENDIX, "", $file_with_appendix);
                    rename($file_with_appendix, $file_without_appendix);
                }
            }

            // delete no longer needed directories (whose eponymous version does not match any correct version)
            $matches_any_correct_version = false;
            foreach ($a_error_report as $error_report_entry) {
                if(isset($error_report_entry['correct_path']) && isset($error_report_entry['filename'])) {
                    $correct_path = $error_report_entry['correct_path'];
                    $filename = $error_report_entry['filename'];
                    $correct_path_without_filename = str_replace("/" . $filename, "", $correct_path);

                    if($version_directory == $correct_path_without_filename) {
                        $matches_any_correct_version = true;
                    }
                }
            }
            if(!$matches_any_correct_version) {
                rmdir($version_directory);
            }
        }
    }


    /**
     * @param ilObjFile $a_file
     *
     * @return array
     */
    public function getVersionsFromHistoryOfFile(ilObjFile $a_file)
    {
        $versions = (array) ilHistory::_getEntriesForObject($a_file->getId(), $a_file->getType());

        // extract information from info_params (contains version and max_version)
        foreach ($versions as $index => $version) {
            $params = $this->parseInfoParams($version);
            $versions[$index] = array_merge($version, $params);
        }

        return $versions;
    }


    /**
     * @param array $a_broken_history_entry
     * @param array $a_error_report_entry
     *
     * @return string
     */
    private function getFixedInfoParams(array $a_broken_history_entry, array $a_error_report_entry) {
        $info_params_array =  explode(",", $a_broken_history_entry['info_params']);

        if(count($info_params_array) >= 2) {
            // set correct version in info params array
            $info_params_array[1] = $a_error_report_entry['correct_version'];
            // add additional information in case of a rollback
            if($a_broken_history_entry['action'] === "rollback") {
                $rollback_version = $a_broken_history_entry['rollback_version'];
                $rollback_user_id = $a_broken_history_entry['rollback_user_id'];
                $info_params_array[1] .= "|" . $rollback_version . "|" . $rollback_user_id;
            }
            // set or add correct max version in info params array
            if(count($info_params_array) === 3) {
                $info_params_array[2] = $a_error_report_entry['correct_version'];
            } elseif (count($info_params_array) === 2) {
                $info_params_array[] = $a_error_report_entry['correct_version'];
            }
        }
        $fixed_info_params = implode(",", $info_params_array);

        return $fixed_info_params;
    }


    /**
     * Function copied from ilObjFile
     *
     * @param $entry
     *
     * @return array
     */
    private function parseInfoParams($entry)
    {
        $data = explode(",", $entry["info_params"]);

        // bugfix: first created file had no version number
        // this is a workaround for all files created before the bug was fixed
        if (empty($data[1])) {
            $data[1] = "1";
        }

        if (empty($data[2])) {
            $data[2] = "1";
        }

        $result = array(
            "filename"         => $data[0],
            "version"          => $data[1],
            "max_version"      => $data[2],
            "rollback_version" => "",
            "rollback_user_id" => "",
        );

        // if rollback, the version contains the rollback version as well
        // bugfix mantis 26236: rollback info is read from version to ensure compatibility with older ilias versions
        if ($entry["action"] == "rollback") {
            $tokens = explode("|", $result["version"]);
            if (count($tokens) > 1) {
                $result["version"] = $tokens[0];
                $result["rollback_version"] = $tokens[1];

                if (count($tokens) > 2) {
                    $result["rollback_user_id"] = $tokens[2];
                }
            }
        }

        return $result;
    }


    private function downloadVersion()
    {
        $file = new ilObjFile($_GET[self::FILE_REF_ID]);
        $version = (int) $_GET[self::HIST_ID];
        $file->sendFile($version);
    }
}