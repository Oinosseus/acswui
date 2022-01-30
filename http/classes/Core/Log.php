<?php

namespace Core;

class Log {

    private static $FilePathError   = "";
    private static $FileHandleError = 0;

    private static $FilePathWarning = "";
    private static $FileHandleWarning = 0;
    private static $FileBufferWarning = "";

    public static function initialize(string $logpath) {
        Log::$FilePathError   = $logpath . "/" . date("Y-m-d") . ".error.log";
        Log::$FilePathWarning = $logpath . "/" . date("Y-m-d") . ".warning.log";
    }


    //! @return String with backtrace information
    private static function getBacktrace(string $endline) {

//         $endline = (Config::LogDebug) ? "<br>" : "\n";

        $ret = "";
        $bktrc = debug_backtrace();
        for ($i=1; $i<count($bktrc);$i++) {

            // get class
            if (isset($bktrc[$i]['class']))
                $class = $bktrc[$i]['class'] . (($bktrc[$i]['class'] == "::") ? "::":"->");
            else
                $class = "";

            // get function call
            if (isset($bktrc[$i]['function']))
                $funct = $bktrc[$i]['function'] . "(" . implode(", ", $bktrc[$i]['args']) . ")";
            else
                $funct = "";

            // backtrace information
            $ret .= "[Backtrace $i]";
            $file = (array_key_exists('file', $bktrc[$i])) ? $bktrc[$i]['file'] : "?";
            $line = (array_key_exists('line', $bktrc[$i])) ? $bktrc[$i]['line'] : "?";
            $ret .= " $file : $line : " . $class . $funct . $endline;
        }

        return $ret;
    }


    // Buffered logfiles are written in the destructor.
    // Open files are closed in destructor.
    public static function shutdown() {
        // close error file
        if (Log::$FileHandleError != 0) {
            fflush(Log::$FileHandleError);
            fclose(Log::$FileHandleError);
        }

        // write warning file
        if (Log::$FileHandleWarning != 0) {
            if (Log::$FileBufferWarning >= 0) {
                flock(Log::$FileHandleWarning, LOCK_EX);
                fwrite(Log::$FileHandleWarning, Log::$FileBufferWarning);
            }
            fclose(Log::$FileHandleWarning);
        }
    }


    //! Error messages are written immediately without buffering.
    //! Logfile is locked -> multiple instances must wait for releasing.
    public static function error ($message) {

        // open logfile for writing
        if (Config::LogDebug !== TRUE && Log::$FileHandleError == 0) {
            Log::$FileHandleError = fopen(Log::$FilePathError, 'a');
            flock(Log::$FileHandleError, LOCK_EX);
            fwrite(Log::$FileHandleError, "\n\n\n");
            fwrite(Log::$FileHandleError, " New Run " . date("H:i:s") . "\n");
            fwrite(Log::$FileHandleError, "==================\n");
        }

        // log entry
        if (Config::LogDebug && array_key_exists("HtmlContent", $_GET)) {
            $html = "$message<br>";
            $html .= Log::getBacktrace("<br>");
            echo "<div class=\"DebugLog Error\">$html</div>";

        } else if (Log::$FileHandleError != 0) {
            fwrite(Log::$FileHandleError, "\n$message\n");
            fwrite(Log::$FileHandleError, Log::getBacktrace("\n"));
            fflush(Log::$FileHandleError);
        }
    }

    //! Warning messages are bufferend. The logfile is written when class destructor is called.
    public static function warning ($message) {

        if (Config::LogWarning !== TRUE)
            return;

        // put message to warning file buffer
        if (Config::LogDebug && array_key_exists("HtmlContent", $_GET)) {
            $html = "$message<br>";
            $html .= Log::getBacktrace("<br>");
            echo "<div class=\"DebugLog Warning\">$html</div>";

        } else {
            // write header to warning file buffer
            if (Log::$FileHandleWarning == 0) {
                Log::$FileHandleWarning = fopen(Log::$FilePathWarning, 'a');
                Log::$FileBufferWarning .= "\n\n\n";
                Log::$FileBufferWarning .= " New Run " . date("H:i:s") . "\n";
                Log::$FileBufferWarning .= "==================\n";
            }

            Log::$FileBufferWarning .= "\n$message\n";
            fwrite(Log::$FileBufferWarning, Log::getBacktrace("\n"));
        }
    }

    //! Debug messages are output directly
    public static function debug($message) {

        if (Config::LogDebug !== TRUE || !array_key_exists("HtmlContent", $_GET))
            return;

        // put message to warning file buffer
        $html = "$message<br>";
        $html .= Log::getBacktrace("<br>");
        echo "<div class=\"DebugLog Debug\">$html</div>";
    }

}
