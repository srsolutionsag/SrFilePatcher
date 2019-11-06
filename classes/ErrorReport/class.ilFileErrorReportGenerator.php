<?php

/* Copyright (c) 1998-2009 ILIAS open source, Extended GPL, see https://github.com/ILIAS-eLearning/ILIAS/tree/trunk/docs/LICENSE */

require_once __DIR__ . "/../../vendor/autoload.php";

use srag\Plugins\SrFilePatcher\Utils\SrFilePatcherTrait;
use srag\DIC\SrFilePatcher\DICTrait;

/**
 * Class ilFileErrorReportGenerator
 *
 * @author  studer + raimann ag - Team Core 1 <support-core1@studer-raimann.ch>
 */
class ilFileErrorReportGenerator
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
     * @var ilSrFilePatcherPlugin
     */
    protected $pl;


    /**
     * ilFileErrorReportGenerator constructor.
     */
    public function __construct()
    {
        $this->log = self::dic()->logger()->root();
        $this->ctrl = self::dic()->ctrl();
        $this->lng = self::dic()->language();
        $this->pl = ilSrFilePatcherPlugin::getInstance();
    }


    /**
     * @param ilObjFile $a_file
     *
     * @return array
     */
    public function getReport(ilObjFile $a_file)
    {
        $versions = $this->getVersions($a_file);

        $incorrectly_numbered_versions = $this->getIncorrectlyNumberedVersions($a_file);
        $misplaced_versions = $this->getMisplacedNewVersions($a_file);
        $versions_without_folder = $this->getVersionsWithoutFolder($a_file);
        $lost_old_versions = $this->getLostOldVersions($a_file);
        $lost_new_versions = $this->getLostNewVersions($a_file);
        $lost_versions = array_merge($lost_old_versions, $lost_new_versions);

        $report = [];
        foreach ($versions as $version) {
            $hist_entry_id = $version['hist_entry_id'];

            $report[$hist_entry_id]['version'] = $version['version'];
            $report[$hist_entry_id]['date'] = $version['date'];
            $report[$hist_entry_id]['filename'] = $version['filename'];

            if (in_array($version, $incorrectly_numbered_versions)) {
                $report[$hist_entry_id]['numbered_correctly'] = false;
            } else {
                $report[$hist_entry_id]['numbered_correctly'] = true;
            }

            if (in_array($version, $misplaced_versions)) {
                $report[$hist_entry_id]['stored_correctly'] = false;
            } else {
                $report[$hist_entry_id]['stored_correctly'] = true;
            }

            if (in_array($version, $versions_without_folder)) {
                $report[$hist_entry_id]['folder_exists'] = false;
            } else {
                $report[$hist_entry_id]['folder_exists'] = true;
            }

            if (in_array($version, $lost_versions)) {
                $report[$hist_entry_id]['file_exists'] = false;
                $report[$hist_entry_id]['patch_possible'] = false;
            } else {
                $report[$hist_entry_id]['file_exists'] = true;
                $report[$hist_entry_id]['patch_possible'] = true;
            }
        }

        return $report;
    }


    /**
     * @param ilObjFile $a_file
     *
     * @return bool
     */
    public function hasWrongMaxVersion(ilObjFile $a_file)
    {
        $correct_max_version = $this->getCorrectMaxVersion($this->getVersions($a_file));
        $file_max_version = $a_file->getMaxVersion();

        if ($file_max_version !== $correct_max_version) {
            return true;
        } else {
            return false;
        }
    }


    /**
     * @param ilObjFile $a_file
     *
     * @return bool
     */
    public function hasWrongCurrentVersion(ilObjFile $a_file)
    {
        $correct_current_version = $this->getCorrectCurrentVersion($this->getVersions($a_file));
        $file_current_version = $a_file->getVersion();

        if ($file_current_version !== $correct_current_version) {
            return true;
        } else {
            return false;
        }
    }


    /**
     * @param ilObjFile $a_file
     *
     * @return array
     */
    public function getRedundantVersionNumbersInFileSystem(ilObjFile $a_file)
    {
        $versions_with_correct_numbers = $this->addCorrectVersionNumbersToVersions($this->getVersions($a_file));
        $version_numbers_in_filesystem = $this->getVersionNumbersInFilesystem($a_file);

        $redundant_version_numbers_in_filesystem = [];
        foreach ($version_numbers_in_filesystem as $version_number_in_filesystem) {
            $only_in_file_system = true;
            foreach ($versions_with_correct_numbers as $version_with_correct_number) {
                if ($version_numbers_in_filesystem === $version_with_correct_number['correct_version']) {
                    $only_in_file_system = false;
                }
            }
            if ($only_in_file_system) {
                $redundant_version_numbers_in_filesystem[] = $version_number_in_filesystem;
            }
        }

        return $redundant_version_numbers_in_filesystem;
    }


    /**
     * @param ilObjFile $a_file
     *
     * @return array
     */
    private function getVersions(ilObjFile $a_file)
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
     * @param ilObjFile $a_file
     *
     * @return array
     */
    private function getIncorrectlyNumberedVersions(ilObjFile $a_file)
    {
        $versions_with_correct_numbers = $this->addCorrectVersionNumbersToVersions($this->getVersions($a_file));

        $incorrectly_numbered_versions = [];
        foreach ($versions_with_correct_numbers as $version_with_correct_number) {
            if ($version_with_correct_number['version'] !== $version_with_correct_number['correct_version']) {
                $incorrectly_numbered_version = $version_with_correct_number;
                // the correct-version array entry must be removed to ensure that "in_array"-checks
                // between the incorrectly_numbered_versions-array and the version-array still work
                // (the existence of an additional array-entry would always cause a mismatch)
                unset($incorrectly_numbered_version['correct_version']);
                $incorrectly_numbered_versions[] = $incorrectly_numbered_version;
            }
        }

        return $incorrectly_numbered_versions;
    }


    /**
     * @param ilObjFile $a_file
     *
     * @return array
     */
    private function getMisplacedNewVersions(ilObjFile $a_file)
    {
        $misplaced_new_versions = []; // new versions that are inside a folder that doesn't match their (correct) version number

        $new_versions = $this->getNewVersions($this->getVersions($a_file));
        $lost_new_versions = $this->getLostNewVersions($a_file);

        // new versions that have not been lost due to bearing the same name as an old version have inadvertently been misplaced
        foreach ($new_versions as $new_version) {
            if (!in_array($new_version, $lost_new_versions)) {
                $misplaced_new_versions[] = $new_version;
            }
        }

        return $misplaced_new_versions;
    }


    /**
     * @param ilObjFile $a_file
     *
     * @return array
     */
    private function getVersionsWithoutFolder(ilObjFile $a_file)
    {
        $versions_with_correct_numbers = $this->addCorrectVersionNumbersToVersions($this->getVersions($a_file));
        $version_numbers_in_filesystem = $this->getVersionNumbersInFilesystem($a_file);

        $versions_without_folder = [];
        foreach ($versions_with_correct_numbers as $version_with_correct_number) {
            if (!in_array($version_with_correct_number['correct_version'], $version_numbers_in_filesystem)) {
                $version_without_folder = $version_with_correct_number;
                // the correct-version array entry must be removed to ensure that "in_array"-checks between the
                // versions_without_folder-array and the version-array still work
                // (the existence of an additional array-entry would always cause a mismatch)
                unset($version_without_folder['correct_version']);
                $versions_without_folder[] = $version_without_folder;
            }
        }

        return $versions_without_folder;
    }


    /**
     * @param ilObjFile $a_file
     *
     * @return array
     */
    private function getLostOldVersions(ilObjFile $a_file)
    {
        $old_versions = $this->getOldVersions($this->getVersions($a_file));
        $version_numbers_in_filesystem = $this->getVersionNumbersInFilesystem($a_file);

        $lost_old_versions = []; // old versions that were mistakenly deleted by a command targeting their duplicate
        foreach ($old_versions as $old_version) {
            if (!in_array($old_version['version'], $version_numbers_in_filesystem)) {
                $lost_old_versions[] = $old_version;
            }
        }

        return $lost_old_versions;
    }


    /**
     * @param ilObjFile $a_file
     *
     * @return array
     */
    private function getLostNewVersions(ilObjFile $a_file)
    {
        $lost_new_versions = [];

        $duplicate_versions = $this->getDuplicateVersions($this->getVersions($a_file));
        $duplicated_old_versions = $duplicate_versions['duplicated_old_versions'];
        $duplicated_new_versions = $duplicate_versions['duplicated_new_versions'];

        foreach ($duplicated_old_versions as $duplicated_old_version) {
            foreach ($duplicated_new_versions as $duplicated_new_version) {
                if ((!in_array($duplicated_new_version, $lost_new_versions))
                    AND ($duplicated_old_version['filename'] === $duplicated_new_version['filename'])
                    AND ($duplicated_old_version['filetype'] === $duplicated_new_version['filetype'])
                ) {
                    $lost_new_versions[] = $duplicated_new_version;
                }
            }
        }

        return $lost_new_versions;
    }


    /**
     * @param array $a_versions
     *
     * @return int
     */
    private function getHighestVersion(array $a_versions)
    {
        $highest_version = 0;

        foreach ($a_versions as $version) {
            if ($version['version'] > $highest_version) {
                $highest_version = $version['version'];
            }
        }

        return $highest_version;
    }


    /**
     * @param array $a_versions
     *
     * @return int
     */
    private function getCorrectMaxVersion(array $a_versions)
    {
        $old_versions = $this->getOldVersions($a_versions);
        $new_versions = $this->getNewVersions($a_versions);

        $highest_old_version = $this->getHighestVersion($old_versions);
        $highest_new_version = $this->getHighestVersion($new_versions);

        return $correct_max_version = $highest_old_version + $highest_new_version - 1;
    }


    /**
     * @param array $a_versions
     *
     * @return int
     */
    private function getCorrectCurrentVersion(array $a_versions)
    {
        $versions_with_correct_numbers = $this->addCorrectVersionNumbersToVersions($a_versions);

        $correct_current_version = 0;
        foreach ($versions_with_correct_numbers as $version_with_correct_number) {
            if ($version_with_correct_number['correct_version'] > $correct_current_version) {
                $correct_current_version = $version_with_correct_number['correct_version'];
            }
        }

        return $correct_current_version;
    }


    /**
     * @param array $a_versions
     *
     * @return array
     */
    private function addCorrectVersionNumbersToVersions(array $a_versions)
    {
        $versions_with_correct_numbers = [];

        // add already correct old versions to the array
        $old_versions = $this->getOldVersions($a_versions);
        foreach ($old_versions as $old_version) {
            $old_version['correct_version'] = $old_version['version'];
            $versions_with_correct_numbers[] = $old_version;
        }

        // determine what the correct version numbers would be for the broken new versions and add them to the array
        $new_versions = $this->getNewVersions($a_versions);
        $highest_old_version = $this->getHighestVersion($old_versions);
        foreach ($new_versions as $new_version) {
            $new_version['correct_version'] = $new_version['version'] + $highest_old_version - 1;
            $versions_with_correct_numbers[] = $new_version;
        }

        return $versions_with_correct_numbers;
    }


    /**
     * @param array $a_versions
     *
     * @return array
     */
    private function getOldVersions(array $a_versions)
    {
        $old_versions = [];
        foreach ($a_versions as $version) {
            if (!isset($version['max_version']) OR $version['max_version'] == 1) {
                $old_versions[] = $version;
            }
        }
        return $old_versions;
    }


    /**
     * @param array $a_versions
     *
     * @return array
     */
    private function getNewVersions(array $a_versions)
    {
        $new_versions = [];
        foreach ($a_versions as $version) {
            if ($version['max_version'] > 1) {
                $new_versions[] = $version;
            }
        }
        return $new_versions;
    }


    /**
     * @param array $a_versions
     *
     * @return array
     */
    private function getDuplicateVersions(array $a_versions)
    {
        $duplicate_versions = [];
        $duplicated_old_versions = [];
        $duplicated_new_versions = [];

        $old_versions = $this->getOldVersions($a_versions);
        $new_versions = $this->getNewVersions($a_versions);

        foreach ($old_versions as $old_version) {
            foreach ($new_versions as $new_version) {
                if ($old_version['version'] === $new_version['version']) {
                    if (!in_array($old_version, $duplicated_old_versions)) {
                        $duplicated_old_versions[] = $old_version;
                    }
                    if (!in_array($new_versions, $duplicated_new_versions)) {
                        $duplicated_new_versions[] = $new_version;
                    }
                }
            }
        }

        $duplicate_versions['duplicated_old_versions'] = $duplicated_old_versions;
        $duplicate_versions['duplicated_new_versions'] = $duplicated_new_versions;

        return $duplicate_versions;
    }


    /**
     * @param ilObjFile $a_file
     *
     * @return array
     */
    private function getVersionNumbersInFilesystem(ilObjFile $a_file)
    {
        $file_directory = $a_file->getDirectory();
        $sub_directories = glob($file_directory . "/*", GLOB_ONLYDIR);

        $version_numbers = [];
        foreach ($sub_directories as $sub_directory) {
            $version_numbers[] = (string) (int) str_replace($file_directory . "/", "", $sub_directory);
        }

        return $version_numbers;
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
}