<?php

// convert laptime from ms to MMM:ss.mmm
function laptime2str ($laptime_ms) {
    $milliseconds = $laptime_ms % 1000;
    $laptime_ms /= 1000;
    $seconds = $laptime_ms % 60;
    $minutes = $laptime_ms / 60;

    return sprintf("%3d:%02d:%03d", $minutes, $seconds, $milliseconds);
}

?>
