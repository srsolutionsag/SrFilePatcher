<?php

/* Copyright (c) 1998-2009 ILIAS open source, Extended GPL, see https://github.com/ILIAS-eLearning/ILIAS/tree/trunk/docs/LICENSE */

require_once __DIR__ . "/../vendor/autoload.php";

use srag\Plugins\SrFilePatcher\Utils\SrFilePatcherTrait;
use srag\DIC\SrFilePatcher\DICTrait;

/**
 * Class ilSrFilePatcher
 *
 * @author  studer + raimann ag - Team Core 1 <support-core1@studer-raimann.ch>
 */
class ilSrFilePatcher
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


    public function __construct()
    {
        $this->log = self::dic()->logger()->root();
        $this->ctrl = self::dic()->ctrl();
        $this->lng = self::dic()->language();
        $this->pl = ilSrFilePatcherPlugin::getInstance();
    }


    public function patchSingleFile(int $a_file_ref_id)
    {

        $file = new ilObjFile($a_file_ref_id);
        if ($file == null) {
            ilUtil::sendFailure(sprintf(self::dic()->language()->txt("error_no_file_for_ref_id"), $a_file_ref_id), true);
        }

        // check which parts of the file versioning - if any - are broken
        $error_report = $this->getErrorReportOfFileVersioning($file);
    }


    public function patchAllFiles()
    {

    }


    public function getErrorReportOfFileVersioning(ilObjFile $a_file)
    {
        $versions = $this->getVersions($a_file);
        return $error_report = [
            'num_duplicate_version_numbers'   => count($this->getDuplicateVersions($versions)['duplicated_old_versions']),
            'has_wrong_max_version'           => $this->hasWrongMaxVersion($a_file),
            'has_wrong_version'               => $this->hasWrongCurrentVersion($a_file),
            'num_missing_version_folders'     => count($this->getVersionNumbersMissingInFileSystem($a_file)),
            'num_redundant_version_folders'   => count($this->getRedundantVersionNumbersInFileSystem($a_file)),
            'num_lost_old_version_files'      => count($this->getOldVersionNumbersMissingInFileSystem($a_file)),
            'num_lost_new_version_files'      => count($this->getLostNewVersions($a_file)),
            'num_misplaced_new_version_files' => count($this->getMisplacedNewVersions($a_file)),
        ];
    }


    /**
     * @param ilObjFile $a_file
     *
     * @return bool
     */
    private function hasDuplicateVersionNumbers(ilObjFile $a_file)
    {
        $duplicate_versions = $this->getDuplicateVersions($this->getVersions($a_file));

        if (!empty($duplicate_versions)) {
            return true;
        } else {
            return false;
        }
    }


    private function hasWrongMaxVersion(ilObjFile $a_file)
    {
        $correct_max_version = $this->getCorrectMaxVersion($this->getVersions($a_file));
        $file_max_version = $a_file->getMaxVersion();

        if ($file_max_version !== $correct_max_version) {
            return true;
        } else {
            return false;
        }
    }


    private function hasWrongCurrentVersion(ilObjFile $a_file)
    {
        $correct_current_version = $this->getCorrectCurrentVersion($this->getVersions($a_file));
        $file_current_version = $a_file->getVersion();

        if ($file_current_version !== $correct_current_version) {
            return true;
        } else {
            return false;
        }
    }


    private function hasMissingVersionFolders(ilObjFile $a_file)
    {
        $version_numbers_missing_in_filesystem = $this->getVersionNumbersMissingInFileSystem($a_file);

        if (!empty($version_numbers_missing_in_filesystem)) {
            return true;
        } else {
            return false;
        }
    }


    private function hasRedundantVersionFolders(ilObjFile $a_file)
    {
        $redundant_version_numbers_in_filesystem = $this->getRedundantVersionNumbersInFileSystem($a_file);

        if (!empty($redundant_version_numbers_in_filesystem)) {
            return true;
        } else {
            return false;
        }
    }


    private function hasMisplacedNewVersionFiles(ilObjFile $a_file)
    {
        $misplaced_version_files = $this->getMisplacedNewVersions($a_file);

        if (!empty($misplaced_version_files)) {
            return true;
        } else {
            return false;
        }
    }


    private function hasLostOldVersionFiles(ilObjFile $a_file)
    {
        $lost_old_version_files = $this->getOldVersionNumbersMissingInFileSystem($a_file);

        if (!empty($lost_old_version_files)) {
            return true;
        } else {
            return false;
        }
    }


    private function hasLostNewVersionFiles(ilObjFile $a_file)
    {
        $lost_new_version_files = $this->getLostNewVersions($a_file);

        if (!empty($lost_new_version_files)) {
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
     * @param array $a_versions
     *
     * @return array
     */
    private function getVersionNumbers(array $a_versions)
    {
        $version_numbers = [];

        foreach ($a_versions as $version) {
            $version_numbers[] = $version['version'];
        }

        return $version_numbers;
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


    private function getCorrectMaxVersion(array $a_versions)
    {
        $old_versions = $this->getOldVersions($a_versions);
        $new_versions = $this->getNewVersions($a_versions);

        $highest_old_version = $this->getHighestVersion($old_versions);
        $highest_new_version = $this->getHighestVersion($new_versions);

        return $correct_max_version = $highest_old_version + $highest_new_version - 1;
    }


    private function getCorrectCurrentVersion(array $a_versions)
    {
        $correct_version_numbers = $this->getCorrectVersionNumbers($a_versions);
        return $correct_current_version = max($correct_version_numbers);
    }


    /**
     * @param array $a_versions
     *
     * @return array
     */
    private function getCorrectVersionNumbers(array $a_versions)
    {
        $correct_version_numbers = [];

        // add already correct old version numbers to the array
        $old_versions = $this->getOldVersions($a_versions);
        foreach ($old_versions as $old_version) {
            $correct_version_numbers[] = $old_version['version'];
        }

        // determine what the correct version numbers would be for the broken new versions and add them to the array
        $new_versions = $this->getNewVersions($a_versions);
        $highest_old_version = $this->getHighestVersion($old_versions);
        foreach ($new_versions as $new_version) {
            $correct_new_version = $new_version['version'] + $highest_old_version - 1;
            $correct_version_numbers[] = $correct_new_version;
        }

        return $correct_version_numbers;
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
            $version_numbers[] = (string)(int) str_replace($file_directory . "/", "", $sub_directory);
        }

        return $version_numbers;
    }


    /**
     * @param ilObjFile $a_file
     *
     * @return array
     */
    private function getOldVersionNumbersMissingInFileSystem(ilObjFile $a_file)
    {
        $old_versions = $this->getOldVersions($this->getVersions($a_file));
        $old_version_numbers = $this->getVersionNumbers($old_versions);
        $version_numbers_in_filesystem = $this->getVersionNumbersInFilesystem($a_file);

        $old_versions_missing_in_filesystem = [];
        foreach ($old_version_numbers as $old_version_number) {
            if (!in_array($old_version_number, $version_numbers_in_filesystem)) {
                $old_versions_missing_in_filesystem[] = $old_version_number;
            }
        }

        return $old_versions_missing_in_filesystem;
    }


    private function getVersionNumbersMissingInFileSystem(ilObjFile $a_file)
    {
        $correct_version_numbers = $this->getCorrectVersionNumbers($this->getVersions($a_file));
        $version_numbers_in_filesystem = $this->getVersionNumbersInFilesystem($a_file);

        $versions_missing_in_filesystem = [];
        foreach ($correct_version_numbers as $correct_version_number) {
            if (!in_array($correct_version_number, $version_numbers_in_filesystem)) {
                $versions_missing_in_filesystem[] = $correct_version_number;
            }
        }

        return $versions_missing_in_filesystem;
    }


    private function getRedundantVersionNumbersInFileSystem(ilObjFile $a_file)
    {
        $correct_version_numbers = $this->getCorrectVersionNumbers($this->getVersions($a_file));
        $version_numbers_in_filesystem = $this->getVersionNumbersInFilesystem($a_file);

        $redundant_versions_in_filesystem = [];
        foreach ($version_numbers_in_filesystem as $version_number_in_filesystem) {
            if (!in_array($version_number_in_filesystem, $correct_version_numbers)) {
                $redundant_versions_in_filesystem[] = $version_number_in_filesystem;
            }
        }

        return $redundant_versions_in_filesystem;
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