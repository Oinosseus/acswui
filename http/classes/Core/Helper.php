<?php

namespace Core;


class Helper {

    private static $SERVER_IP = NULL;
    private static $FILE_CACHE_IP = NULL;


    //! @return The server public IP (does not work with forwaring)
    public static function ip() : string {

        $ip_cache_file = \Core\Config::AbsPathData . "/acswui_config/server_ip.txt";

        // update cache
        if (Self::$SERVER_IP === NULL ) {

            // try to get from apache
            Self::$SERVER_IP = (string) $_SERVER['SERVER_ADDR'];

            // update file cache
            if (Self::$SERVER_IP != "" && Self::$FILE_CACHE_IP != Self::$SERVER_IP) {
                file_put_contents($ip_cache_file, Self::$SERVER_IP, LOCK_EX);
                Self::$FILE_CACHE_IP = Self::$SERVER_IP;
            }

            // read from file cache
            if (Self::$SERVER_IP == "") {
                Self::$FILE_CACHE_IP = file_get_contents($ip_cache_file);
                if (Self::$FILE_CACHE_IP === FALSE) Self::$FILE_CACHE_IP = "";
                Self::$SERVER_IP = Self::$FILE_CACHE_IP;
            }
        }

        return Self::$SERVER_IP;
    }


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


    /**
     * Finds the most N-maximum values in a list of numbers (int|float)
     * and returns the sum of them.
     *
     * If N=0, then 0 is returned
     * If N is less than count($list), then the sum of all is returned.
     *
     * @param $n The number of elements to sum
     * @param $list An array of numbers
     * @return The sum of the N-maximum elements
     */
    public static function maxNSum(int $n, array $list) : int|float {
        $sum = 0;
        sort($list,  SORT_NUMERIC);
        $list = array_reverse($list);
        for ($i=0; $i<count($list) && $i<$n; ++$i)
            $sum += $list[$i];
        return $sum;
    }

    /**
     * Finds the least N-minimum values in a list of numbers (int|float)
     * and returns the sum of them.
     *
     * If N=0, then 0 is returned
     * If N is less than count($list), then the sum of all is returned.
     *
     * @param $n The number of elements to sum
     * @param $list An array of numbers
     * @return The sum of the N-minimum elements
     */
    public static function minNSum(int $n, array $list) : int|float {
        $sum = 0;
        sort($list,  SORT_NUMERIC);
        for ($i=0; $i<count($list) && $i<$n; ++$i)
            $sum += $list[$i];
        return $sum;
    }
}
