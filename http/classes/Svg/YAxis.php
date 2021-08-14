<?php

namespace Svg;


class YAxis {
    private $IsLeft = NULL;
    private $ExtraClass = NULL;
    private $Label = "";
    private $Plots = array();
    private $DataYMax = 0;
    private $DataYMin = 0;
    private $DataXMax = NULL;
    private $DataXMin = NULL;
    private $YTick = NULL;


    public function __construct(string $xml_id, string $extra_class) {
        $this->ExtraClass = $xml_id;
        $this->IsLeft = TRUE;
        $this->Label = $extra_class;
    }


    public function addPlot(DataPlot $plot) {
        $this->Plots[] = $plot;

        // remember absolute min/max y-range
        [$min, $max] = $plot->yrange();
        if ($this->DataYMin === NULL || $min < $this->DataYMin) $this->DataYMin = $min;
        if ($this->DataYMax === NULL || $max > $this->DataYMax) $this->DataYMax = $max;
//         \Core\Log::debug(sprintf("%s, Y-Range, %d/%d, %d/%d", $this->ExtraClass, $min, $this->DataYMin, $max, $this->DataYMax));

        // remember absolute min/max x-range
        [$min, $max] = $plot->xrange();
//         \Core\Log::debug(sprintf("%s, X-Range, %d, %d", $this->ExtraClass, $min, $max));
        if ($this->DataXMin === NULL || $min < $this->DataXMin) $this->DataXMin = $min;
        if ($this->DataXMax === NULL || $max > $this->DataXMax) $this->DataXMax = $max;
    }


    public function id() {
        return $this->ExtraClass;
    }


    public function isLeft() {
        return $this->IsLeft;
    }


    //! @return The range of the axis values [min, max]
    public function xrange(int $x_tick) {
        $min = $x_tick * floor($this->DataXMin / $x_tick);
        $max = $x_tick * ceil($this->DataXMax / $x_tick);
        return [$min, $max];
    }


    //! @return The range of the axis values [min, max]
    public function yrange() {
        $min = $this->DataYMin;
        $max = $this->DataYMax;

        if ($this->YTick) {
            $min = $this->YTick * floor($this->DataYMin / $this->YTick);
            $max = $this->YTick * ceil($this->DataYMax / $this->YTick);
        }

        return [$min, $max];
    }


    public function drawXmlAxes(int $x_tick, float $scale_x,float $scale_y) {

        // get axis min/max
        [$ax_y_min, $ax_y_max] = $this->yrange();
        [$ax_x_min, $ax_x_max] = $this->xrange($x_tick);
        $ax_x_scaled = (($this->IsLeft) ? $ax_x_min : $ax_x_max) * $scale_x;

        // start group
        $css_class = ($this->IsLeft) ? "YAxisLeft" : "YAxisRight";
        $xml = "<g class=\"$css_class " . $this->ExtraClass . "\">";

        // show grid
        if ($this->YTick) {

            // draw grid
            $x_grid_min_scaled = $ax_x_min * $scale_x;
            $x_grid_max_scaled = $ax_x_max * $scale_x;

            // draw grid
            $xml .= "<g class=\"Grid\">";
            for ($y = ($ax_y_min + $this->YTick); $y < $ax_y_max; $y += $this->YTick) {
                if ($y == 0) continue;
                $y_scaled = -1 * $y * $scale_y;
                $xml .= "<polyline points=\"$x_grid_min_scaled,$y_scaled $x_grid_max_scaled,$y_scaled\"/>";
            }
            $xml .= "</g>";

            // draw tick marks
            $xml .= "<g class=\"Tickmarks\">";
            for ($y = ($ax_y_min + $this->YTick); $y < $ax_y_max; $y += $this->YTick) {
                if ($y == 0) continue;
                $y_scaled = -1 * $y * $scale_y;
                $x_scaled = $ax_x_scaled + (($this->IsLeft) ? -50 : 50) * $scale_x;
                $xml .= "<polyline points=\"$x_scaled,$y_scaled $ax_x_scaled,$y_scaled\"/>";
            }
            $xml .= "</g>";

            // draw tick text
            $xml .= "<g class=\"Ticktext\">";
            for ($y = ($ax_y_min + $this->YTick); $y < $ax_y_max; $y += $this->YTick) {
                if ($y == 0) continue;
                $y_scaled = -1 * $y * $scale_y;
                $x_scaled = $ax_x_scaled;// - 5;
                $text_anchor = ($this->IsLeft) ? "end" : "start";
                $xml .= "<text y=\"$y_scaled\" x=\"$x_scaled\">$y</text>";
            }
            $xml .= "</g>";
        }

        // axis label
        $y_scaled = -1 * $ax_y_max * $scale_y;
        $x_scaled = $ax_x_scaled;
        $xml .= "<text class=\"YAxisLabel\" x=\"$x_scaled\" y=\"$y_scaled\" text-anchor=\"middle\">";
        $xml .= $this->Label;
        $xml .= "</text>";

        // draw axis
        $y0_scaled = -1 * $ax_y_min * $scale_y;
        $y1_scaled = -1 * $ax_y_max * $scale_y;
        $xml .= "<polyline class=\"Axis\" points=\"$ax_x_scaled,$y0_scaled $ax_x_scaled,$y1_scaled\" style=\"marker-end:url(#AxisArrow)\" />";

        $xml .= "</g>";

        return $xml;
    }


    public function drawXmlPlots(float $scale_x, float $scale_y) {
        $xml = "";
        foreach ($this->Plots as $p) {
            $xml .= $p->drawXml($scale_x, $scale_y);
        }
        return $xml;
    }


    public function setSideLeft() {
        $this->IsLeft = TRUE;
    }

    public function setSideRight() {
        $this->IsLeft = FALSE;
    }


    /**
     * The tick is used for grid and min/max axis calculation
     */
    public function setYTick(int $y_tick) {
        $this->YTick = $y_tick;
    }
}
