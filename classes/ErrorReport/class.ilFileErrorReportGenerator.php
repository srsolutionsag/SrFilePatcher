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
    public function __construct(ilSrFilePatcherGUI $a_parent)
    {
        $this->log = self::dic()->logger()->root();
        $this->ctrl = self::dic()->ctrl();
        $this->lng = self::dic()->language();
        $this->parent = $a_parent;
        $this->pl = ilSrFilePatcherPlugin::getInstance();
    }


    /**
     * @param ilObjFile $a_file
     *
     * @return array
     */
    public function getReport(ilObjFile $a_file)
    {
        $versions = $this->getExtendedVersions($a_file);

        $incorrectly_numbered_versions = $this->getIncorrectlyNumberedVersions($a_file);
        $misplaced_versions = $this->getMisplacedVersions($a_file);
        $versions_without_folder = $this->getVersionsWithoutFolder($a_file);
        $lost_old_versions = $this->getLostOldVersions($a_file);
        $lost_new_versions = $this->getLostNewVersions($a_file);
        $lost_versions = array_merge($lost_old_versions, $lost_new_versions);

        $report = [];
        // store file ref_id in report to preserve the information regarding which file the report concerns
        $report['file_ref_id'] = $a_file->getRefId();
        // add data concerning the individual file versions to the report
        foreach ($versions as $version) {
            $hist_entry_id = $version['hist_entry_id'];

            $report[$hist_entry_id]['hist_entry_id'] = $hist_entry_id;
            $report[$hist_entry_id]['version'] = $version['version'];
            $report[$hist_entry_id]['correct_version'] = $version['correct_version'];
            $report[$hist_entry_id]['date'] = $version['date'];
            $report[$hist_entry_id]['filename'] = $version['filename'];
            $report[$hist_entry_id]['user_id'] = $version['user_id'];

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
                $report[$hist_entry_id]['current_path'] = "-";
                $report[$hist_entry_id]['correct_path'] = "-";
            } else {
                $report[$hist_entry_id]['file_exists'] = true;
                $report[$hist_entry_id]['patch_possible'] = true;
                $report[$hist_entry_id]['current_path'] = $version['current_path'];
                $report[$hist_entry_id]['correct_path'] = $version['correct_path'];
            }
        }
        // add db-data to record
        $report['db_current_version'] = $a_file->getVersion();
        $report['db_correct_version'] = $this->getCorrectCurrentVersion($a_file);
        $report['db_current_max_version'] = $a_file->getMaxVersion();
        $report['db_correct_max_version'] = $this->getCorrectMaxVersion($a_file);

        return $report;
    }


    /**
     * @param ilObjFile $a_file
     *
     * @return bool
     */
    public function hasWrongMaxVersion(ilObjFile $a_file)
    {
        $correct_max_version = $this->getCorrectMaxVersion($a_file);
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
        $correct_current_version = $this->getCorrectCurrentVersion($a_file);
        $file_current_version = $a_file->getVersion();

        if ($file_current_version !== $correct_current_version) {
            return true;
        } else {
            return false;
        }
    }


    private function getExtendedVersions(ilObjFile $a_file)
    {
        $versions = $this->parent->getVersionsFromHistoryOfFile($a_file);
        $extended_versions = [];

        $old_versions = $this->getOldVersions($versions);
        $new_versions = $this->getNewVersions($versions);

        foreach ($old_versions as $old_version) {
            $extended_version = $old_version;
            $extended_version['correct_version'] = $old_version['version'];
            $extended_version['current_path'] = $this->getCurrentPathForVersion($extended_version, $a_file);
            $extended_version['correct_path'] = $this->getCorrectPathForVersion($extended_version, $a_file);
            $extended_versions[] = $extended_version;
        }

        $highest_old_version = $this->getHighestVersion($old_versions);
        foreach ($new_versions as $new_version) {
            $extended_version = $new_version;
            $extended_version['correct_version'] = $new_version['version'] + $highest_old_version - 1;;
            $extended_version['current_path'] = $this->getCurrentPathForVersion($extended_version, $a_file);
            $extended_version['correct_path'] = $this->getCorrectPathForVersion($extended_version, $a_file);
            $extended_versions[] = $extended_version;
        }

        return $extended_versions;
    }


    /**
     * @param ilObjFile $a_file
     *
     * @return array
     */
    private function getIncorrectlyNumberedVersions(ilObjFile $a_file)
    {
        $versions = $this->getExtendedVersions($a_file);

        $incorrectly_numbered_versions = [];
        foreach ($versions as $version) {
            if ($version['version'] !== $version['correct_version']) {
                $incorrectly_numbered_versions[] = $version;
            }
        }

        return $incorrectly_numbered_versions;
    }


    /**
     * @param ilObjFile $a_file
     *
     * @return array
     */
    private function getMisplacedVersions(ilObjFile $a_file)
    {
        $versions = $this->getExtendedVersions($a_file);

        $misplaced_versions = []; // new versions that are inside a folder that doesn't match their (correct) version number
        foreach ($versions as $version) {
            if ($version['current_path'] !== $version['correct_path']) {
                $misplaced_versions[] = $version;
            }
        }

        return $misplaced_versions;
    }


    /**
     * @param ilObjFile $a_file
     *
     * @return array
     */
    private function getVersionsWithoutFolder(ilObjFile $a_file)
    {
        $versions = $this->getExtendedVersions($a_file);
        $version_numbers_in_filesystem = $this->getVersionNumbersInFilesystem($a_file);

        $versions_without_folder = [];
        foreach ($versions as $version) {
            if (!in_array($version['correct_version'], $version_numbers_in_filesystem)) {
                $versions_without_folder[] = $version;
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
        $old_versions = $this->getOldVersions($this->getExtendedVersions($a_file));
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

        $duplicate_versions = $this->getDuplicateVersions($this->getExtendedVersions($a_file));
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
     * @param ilObjFile $a_file
     *
     * @return int
     */
    private function getCorrectMaxVersion(ilObjFile $a_file)
    {
        $versions = $this->getExtendedVersions($a_file);
        $old_versions = $this->getOldVersions($versions);
        $new_versions = $this->getNewVersions($versions);

        $highest_old_version = $this->getHighestVersion($old_versions);
        $highest_new_version = $this->getHighestVersion($new_versions);

        return $correct_max_version = $highest_old_version + $highest_new_version - 1;
    }


    /**
     * @param ilObjFile $a_file
     *
     * @return int
     */
    private function getCorrectCurrentVersion(ilObjFile $a_file)
    {
        $versions = $this->getExtendedVersions($a_file);

        $correct_current_version = 0;
        foreach ($versions as $version) {
            if ($version['correct_version'] > $correct_current_version) {
                $correct_current_version = $version['correct_version'];
            }
        }

        return $correct_current_version;
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


    private function getCurrentPathForVersion(array $a_version, ilObjFile $a_file)
    {
        $file_directory = $a_file->getDirectory();
        $file_name = $a_version['filename'];
        $sub_directories = glob($file_directory . "/*", GLOB_ONLYDIR);

        $current_path = "-";
        foreach ($sub_directories as $sub_directory) {
            $directory_version = (string) (int) str_replace($file_directory . "/", "", $sub_directory);
            if ($a_version['version'] === $directory_version) {
                $sub_directory = str_replace("//", "/", $sub_directory);
                $current_path = $sub_directory . "/" . $file_name;
            }
        }

        return $current_path;
    }


    private function getCorrectPathForVersion(array $a_version, ilObjFile $a_file)
    {
        $file_directory = $a_file->getDirectory();
        $file_name = $a_version['filename'];
        $correct_version = $a_version['correct_version'];

        $version_directory = "000";
        if (strlen($correct_version) === 1) {
            $version_directory = "00" . $correct_version;
        } elseif (strlen($correct_version) === 2) {
            $version_directory = "0" . $correct_version;
        } elseif (strlen($correct_version) === 3) {
            $version_directory = $correct_version;
        }

        $correct_path = "-";
        if ($version_directory !== "000") {
            $correct_path = $file_directory . $version_directory . "/" . $file_name;
        }

        return $correct_path;
    }
}