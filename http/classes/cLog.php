<?php

class cLog {

    private $FilePathError   = "";
    private $FileHandleError = 0;

    private $FilePathWarning = "";
    private $FileHandleWarning = 0;
    private $FileBufferWarning = "";

    private $FilePathNotice = "";
    private $FileHandleNotice = 0;
    private $FileBufferNotice = "";

    function __construct() {
        global $acswuiConfig;
        $this->FilePathError   = $acswuiConfig->LogPath . "/" . date("Y-m-d") . ".error.log";
        $this->FilePathWarning = $acswuiConfig->LogPath . "/" . date("Y-m-d") . ".warning.log";
        $this->FilePathNotice  = $acswuiConfig->LogPath . "/" . date("Y-m-d") . ".notice.log";
    }

    // Buffered logfiles are written in the destructor.
    // Open files are closed in destructor.
    function __destruct() {
        // close error file
        if ($this->FileHandleError != 0) {
            fflush($this->FileHandleError);
            fclose($this->FileHandleError);
        }

        // write warning file
        if ($this->FileHandleWarning != 0) {
            if ($this->FileBufferWarning >= 0) {
                flock($this->FileHandleWarning, LOCK_EX);
                fwrite($this->FileHandleWarning, $this->FileBufferWarning);
            }
            fclose($this->FileHandleWarning);
        }

        // write notice file
        if ($this->FileHandleNotice != 0) {
            if ($this->FileBufferNotice >= 0) {
                flock($this->FileHandleNotice, LOCK_EX);
                fwrite($this->FileHandleNotice, $this->FileBufferNotice);
            }
            fclose($this->FileHandleNotice);
        }

    }

    // Error messages are written immediately without buffering.
    // Logfile is locked -> multiple instances must wait for releasing.
    public function logError ($message) {

        // open logfile for writing
        if ($this->FileHandleError == 0) {
            $this->FileHandleError = fopen($this->FilePathError, 'a');
            flock($this->FileHandleError, LOCK_EX);
            fwrite($this->FileHandleError, "\n\n\n");
            fwrite($this->FileHandleError, " New Run " . date("H:i:s") . "\n");
            fwrite($this->FileHandleError, "==================\n");
        }

        // log entry
        if ($this->FileHandleError != 0) {
            fwrite($this->FileHandleError, "\n$message\n");
            $bktrc=debug_backtrace();
            for ($i=0; $i<count($bktrc);$i++) {

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
                fwrite($this->FileHandleError, "  [Backtrace $i]");
                fwrite($this->FileHandleError, " " . $bktrc[$i]['file'] . " : " . $bktrc[$i]['line'] . " : " . $class . $funct . "\n");
            }
            fflush($this->FileHandleError);
        }
    }

    // Warning messages are bufferend. The logfile is written when class destructor is called.
    public function logWarning ($message) {

        // write header to warning file buffer
        if ($this->FileHandleWarning == 0) {
            $this->FileHandleWarning = fopen($this->FilePathWarning, 'a');
            $this->FileBufferWarning .= "\n\n\n";
            $this->FileBufferWarning .= " New Run " . date("H:i:s") . "\n";
            $this->FileBufferWarning .= "==================\n";
        }

        // put message to warning file buffer
        $this->FileBufferWarning .= "\n$message\n";
        $bktrc = debug_backtrace();
        for ($i=0; $i<count($bktrc);$i++) {

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
            $this->FileBufferWarning .= "[Backtrace $i]";
            $this->FileBufferWarning .= " " . $bktrc[$i]['file'] . " : " . $bktrc[$i]['line'] . " : " . $class . $funct . "\n";
        }

    }

    // Notice messages are bufferend. The logfile is written when class destructor is called.
    public function logNotice ($message) {

        // write header to notice file buffer
        if ($this->FileHandleNotice == 0) {
            $this->FileHandleNotice = fopen($this->FilePathNotice, 'a');
            $this->FileBufferNotice .= "\n\n\n";
            $this->FileBufferNotice .= " New Run " . date("H:i:s") . "\n";
            $this->FileBufferNotice .= "==================\n";
        }

        // put message to warning file buffer
        $this->FileBufferNotice .= "\n$message\n";
        $bktrc = debug_backtrace();
        for ($i=0; $i<count($bktrc);$i++) {

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
            $this->FileBufferNotice .= "[Backtrace $i]";
            $this->FileBufferNotice .= " " . $bktrc[$i]['file'] . " : " . $bktrc[$i]['line'] . " : " . $class . $funct . "\n";
        }

    }

}


?>
