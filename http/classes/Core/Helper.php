<?php

namespace Core;


class Helper {


    /**
     * Deletes a directory with all its content (recursively)
     * @param $dir_path The directory to be deleted
     */
    public static function rmdirs(string $dir_path) {
        if (!is_dir($dir_path)) {
            \Core\Log::error("Path is not a directory: '$dir_path'");
            return;
        }

        // delete direcotry contents
        foreach (scandir($dir_path) as $f) {

            if ($f == "." || $f == "..") continue;

            $path = "{$dir_path}/$f";
            if (is_dir($path)) {
                Helper::rmdirs($path);
                rmdir($path);
            }
            else {
                unlink($path);
            }
        }

        // delete directory
        rmdir($dir_path);
    }


    /**
     * Deletes all contents within a directory,
     * but not the directory itself.
     * @param $dir_path A path to an existing  directory that shall be wiped.
     * @param $wipe_hidden_files if TRUE, also hidden files and directories are deleted (default=FALSE)
     */
    public static function cleandir(string $dir_path, bool $wipe_hidden_files=FALSE) {
        foreach (scandir($dir_path) as $f) {

            if ($f == "." || $f == "..") continue;
            if ($wipe_hidden_files==FALSE && substr($f, 0, 1) == ".") continue;

            $path = "{$dir_path}/$f";
            if (is_dir($path)) {
                Helper::cleandir($path, $wipe_hidden_files);
                rmdir($path);
            }
            else {
                unlink($path);
            }
        }
    }
}
