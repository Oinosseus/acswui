<?php

namespace Svg;

//! Storage class for data of a plot
class DataPlot {

    private $DataPairs = NULL;
    private $Label = NULL;
    private $ExtraClass = NULL;

    private $XMin = NULL;
    private $XMax = NULL;
    private $YMin = NULL;
    private $YMax = NULL;


    /**
     * @param $data_pair An array of data point elements. Each datapoint element itself is an array of two float values. [[x, y]]
     * @param $label An arbitrary label for the plot
     * @param $extra_class Class name that shall be assigned (additionally to 'DataPlot')
     */
    public function __construct(array $data_pairs, string $label, string $extra_class) {

        $this->DataPairs = $data_pairs;
        $this->Label = $label;
        $this->ExtraClass = $extra_class;

        // determine min/max values
        foreach ($this->DataPairs as $dp) {
            $x = (float) $dp[0];
            $y = (float) $dp[1];

            if ($this->XMin === NULL || $x < $this->XMin) $this->XMin = $x;
            if ($this->XMax === NULL || $x > $this->XMax) $this->XMax = $x;
            if ($this->YMin === NULL || $y < $this->YMin) $this->YMin = $y;
            if ($this->YMax === NULL || $y > $this->YMax) $this->YMax = $y;
        }

    }


    public function __toString() {
        return "DataPlot[" . $this->Label . "]";
    }


    //! @return An array with two elements: [x-min, x-max]
    public function xrange() {
        return [$this->XMin, $this->XMax];
    }


    //! @return An array with two elements: [y-min, y-max]
    public function yrange() {
        return [$this->YMin, $this->YMax];
    }


    public function drawXml(float $scale_x, float $scale_y) {
        $id = $this->ExtraClass;
        $xml = "<g class=\"DataPlot $id\">";

        # line
        $points_line = "";
        foreach ($this->DataPairs as $dp) {
            $points_line .= sprintf("%s,%s ", round($dp[0] * $scale_x), round(-1 * $dp[1] * $scale_y));
        }

        # background
        $points_background = sprintf("%d,0 ",
                                     $this->DataPairs[0][0] * $scale_x);
        $points_background .= $points_line;
        $points_background .= sprintf("%d,0 ",
                                     $this->DataPairs[count($this->DataPairs)-1][0] * $scale_x);

        $xml .= "<polyline class=\"PlotBackground\" points=\"$points_background\"/>";
        $xml .= "<polyline class=\"PlotLine\" points=\"$points_line\"/>";

        $xml .= "</g>";

        return $xml;
    }
}
