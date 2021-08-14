<?php

namespace Svg;


class XAxis {
    private $XTick = NULL;
    private $ExtraClass = "";
    private $Label = "";


    public function __construct(string $extra_class, string $label) {
        $this->ExtraClass = $extra_class;
        $this->Label = $label;
    }


    /**
     * @param $y_axes An array of YAxis objects
     */
    public function drawXmlAxes(array $y_axes, float $scale_x,float $scale_y) {

        if ($this->XTick === NULL) {
            \Core\Log::error("Invalid XTick, use setXTick()!");
            return "";
        }

        // get axis min/max
        [$ax_x_min, $ax_x_max] = $this->xrange($y_axes);
        $ax_y_min = 0;
        $ax_y_max = 0;
        foreach ($y_axes as $yax) {
            [$min, $max] = $yax->yrange();
            if ($min < $ax_y_min) $ax_y_min = $min;
            if ($max > $ax_y_max) $ax_y_max = $max;
        }

        // start group
        $xml = "<g class=\"XAxis ". $this->ExtraClass . "\">";

//         // show grid
//         if ($this->YTick) {
//
//             // draw grid
//             $x_grid_min_scaled = $ax_x_min * $scale_x;
//             $x_grid_max_scaled = $ax_x_max * $scale_x;

            // draw grid
            $xml .= "<g class=\"Grid\">";
            $x_tick_start = $this->XTick * floor($ax_x_min / $this->XTick);
            for ($x = $x_tick_start; $x < $ax_x_max; $x += $this->XTick) {
                if ($x == 0) continue;
                $x_scaled = $x * $scale_x;
                $y0_scaled = -1 * $ax_y_min * $scale_y;
                $y1_scaled = -1 * $ax_y_max * $scale_y;
                $xml .= "<polyline points=\"$x_scaled,$y0_scaled $x_scaled,$y1_scaled\"/>";
            }
            $xml .= "</g>";

            // draw tick marks
            $xml .= "<g class=\"Tickmarks\">";
            $x_tick_start = $this->XTick * floor($ax_x_min / $this->XTick);
            for ($x = $x_tick_start; $x < $ax_x_max; $x += $this->XTick) {
//                 if ($x == 0) continue;
                $x_scaled = $x * $scale_x;
                $y_scaled = 50 * $scale_x;
                $xml .= "<polyline points=\"$x_scaled,0 $x_scaled,$y_scaled\"/>";
            }
            $xml .= "</g>";

            // draw tick text
            $xml .= "<g class=\"Ticktext\">";
            for ($x = $x_tick_start; $x < $ax_x_max; $x += $this->XTick) {
//                 if ($x == 0) continue;
                $x_scaled = $x * $scale_x;
                $xml .= "<text y=\"0\" x=\"$x_scaled\">$x</text>";
            }
            $xml .= "</g>";
//         }

        // axis label
        $x_scaled = 0.5 * ($ax_x_max - $ax_x_min) * $scale_x;
        $xml .= "<text class=\"XAxisLabel\" x=\"$x_scaled\" y=\"0\" text-anchor=\"middle\">";
        $xml .= $this->Label;
        $xml .= "</text>";

        // draw axis
        $x0_scaled = $ax_x_min * $scale_x;
        $x1_scaled =  $ax_x_max * $scale_x;
        $xml .= "<polyline class=\"Axis\" points=\"$x0_scaled,0 $x1_scaled,0\" style=\"marker-end:url(#AxisArrow)\" />";

        $xml .= "</g>";

        return $xml;
    }


    /**
     * The tick is used for grid and min/max axis calculation
     */
    public function setXTick(int $x_tick) {
        $this->XTick = $x_tick;
    }


    public function xrange(array $y_axes) {

        if ($this->XTick === NULL) {
            \Core\Log::error("Invalid XTick, use setXTick()!");
            return [0, 0];
        }

        $ax_x_min = NULL;
        $ax_x_max = NULL;
        foreach ($y_axes as $yax) {
            [$min, $max] = $yax->xrange($this->XTick);
            if ($ax_x_min === NULL || $min < $ax_x_min) $ax_x_min = $min;
            if ($ax_x_max === NULL || $max > $ax_x_max) $ax_x_max = $max;
        }

        // scale x according to tick
        $ax_x_min = $this->XTick * floor($ax_x_min / $this->XTick) - 100;
        $ax_x_max = $this->XTick *  ceil($ax_x_max / $this->XTick) + 100;

        return [$ax_x_min, $ax_x_max];
    }


    //! @return The x-tick (can bee NULL)
    public function xTick() {
        return $this->XTick;
    }

}
