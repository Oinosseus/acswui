<?php

namespace Svg;

//! SVG XY-Chart
class XYChart {

    private $YAxes = array();
    private $XAxis = NULL;

//     private $XTick = NULL;
//     private $YTick = NULL;


    public function __construct() {
    }


    public function addYAxis(YAxis $ax) {
        $this->YAxes[] = $ax;
    }


    //! @return The XML (HTML) string of the SVG chart
    public function drawHtml(string $label, string $html_div_class, float $scale_x, float $scale_y) {

        if ($this->XAxis === NULL) {
            \Core\Log::error("Missing X-Axis, (need to call setXAxis())!");
            return "";
        }




        // --------------------------------------------------------------------
        //                              X-Axis
        // --------------------------------------------------------------------

        [$xax_min_x, $xax_max_x] = $this->XAxis->xrange($this->YAxes);

        $xml_xaxis = "";
        $xml_xaxis .= $this->XAxis->drawXmlAxes($this->YAxes, $scale_x, $scale_y);
//
//         # x-axis
        $xax_xtick = $this->XAxis->xTick();
//         $xaxis_min = NULL;
//         $xaxis_max = NULL;
//         foreach ($this->YAxes as $ax) {
//             [$min, $max] = $ax->xrange($xax_xtick);
//             if ($xaxis_min === NULL || $min < $xaxis_min) $xaxis_min = $min;
//             if ($xaxis_max === NULL || $max < $xaxis_max) $xaxis_max = $max;
//         }
//
//         # enlarge axis ends by 10%
//         $xaxis_span = $xaxis_max - $xaxis_min;
//         $xaxis_min -= $xaxis_span * 0.1;
//         $xaxis_max += $xaxis_span * 0.1;
//
//         // scale and round
//         $xaxis_min = round($scale_x * $xaxis_min);
//         $xaxis_max = round($scale_x * $xaxis_max);
//
//         // draw
//         $xml_xaxis .= "<polyline id=\"XAxis\" points=\"$xaxis_min,0 $xaxis_max,0\" style=\"marker-end:url(#AxisArrow)\" />";


        // --------------------------------------------------------------------
        //                              Y-Axis
        // --------------------------------------------------------------------

        $xml_yaxes = "";

        // find largest y-scale
        $yax_max_y = 0;
        $yax_min_y = 0;
        foreach (array_reverse($this->YAxes) as $ax) {
            [$min, $max] = $ax->yrange();
            if ($max > $yax_max_y) $yax_max_y = $max;
            if ($min < $yax_min_y) $yax_min_y = $min;
        }

        foreach (array_reverse($this->YAxes) as $ax) {
            [$min, $max] = $ax->yrange();

            $xml_yaxes .= $ax->drawXmlAxes($xax_xtick, $scale_x, $scale_y * $yax_max_y / $max);
        }

/*
        if ($this->YAxisLeft) {
            $this->YAxisLeft->setYScale($scale_y);
            $this->YAxisLeft->setXScale($scale_x);
            $pos = 0;
            $xml_yaxes .= $this->YAxisLeft->drawXmlAxes();
            $yaxis_min = $this->YAxisLeft->yrange()[0];
            $yaxis_max = $this->YAxisLeft->yrange()[1];
        }
        if ($this->YAxisRight) {

            // scale to left axis
            if ($this->YAxisLeft) {
                $right_max = $this->YAxisRight->yrange()[1];
                $left_max = $this->YAxisLeft->yrange()[1];
                $scale = $scale_y * $left_max / $right_max;
                $this->YAxisRight->setYScale($scale);
            } else {
                $this->YAxisRight->setScale($scale_y);
                $yaxis_min = $this->YAxisRight->yrange()[0];
                $yaxis_max = $this->YAxisRight->yrange()[1];
            }
            $this->YAxisRight->setXScale($scale_x);
            $pos = 1e3;
            $xml_yaxes .= $this->YAxisRight->drawXmlAxes();
        }*/



        // --------------------------------------------------------------------
        //                           Plots
        // --------------------------------------------------------------------

        $xml_plots = "";
        foreach (array_reverse($this->YAxes) as $ax) {
//             $xml_plots .= "<g id=\"" . $ax->id() . "\">";
            [$min, $max] = $ax->yrange();
            $xml_plots .= $ax->drawXmlPlots($scale_x, $scale_y * $yax_max_y / $max);
//             $xml_plots .= "</g>";
        }


        // --------------------------------------------------------------------
        //                                SVG
        // --------------------------------------------------------------------

        # head
        $viewbox = sprintf("%d %d %d %d",
                           ($xax_min_x - 500) * $scale_x,
                           -1 * ($yax_max_y + 50) * $scale_y,
                           ($xax_max_x - $xax_min_x + 1000) * $scale_x,
                           ($yax_max_y - $yax_min_y + 130) * $scale_y);
//         $viewbox = "-100 -300 1000 400";
        $xml = "<svg class=\"SVGXYChart\" viewBox=\"$viewbox\">";

//         $xml .= "<defs>";
//         $width = 2 * $scale_x;
//         $height = 1 * $scale_y;
//         $xml .= "<marker id=\"AxisArrow\" markerWidth=\"$width\" markerHeight=\"$height\" refx=\"0\" refy=\"2\" orient=\"auto\">";
//         $xml .= "<polyline points=\"0,0 6,2 0,4\"/>";
//         $xml .= "</marker>";
//         $xml .= "</defs>";

        # axes
        $xml .= "<g class=\"Axes\">";
        $xml .= $xml_xaxis;
        $xml .= $xml_yaxes;
        $xml .= "</g>";

        # plots
        $xml .= "<g class=\"Plots\">";
        $xml .= $xml_plots;
        $xml .= "</g>";

        $xml .= "</svg>";

        $html = "<div class=\"SvgChart $html_div_class\">";
        $html .= "<label>$label</label>";
        $html .= $xml;
        $html .= "</div>";

        return $html;
    }


    public function setXAxis(XAxis $ax) {
        if ($this->XAxis !== NULL) {
            \Core\Log::warning("Overwriting new X-Axis");
        }
        $this->XAxis = $ax;
    }


    /**
     * The tick is used for grid and min/max axis calculation
     */
    public function setTick(int $x_tick, int $y_tick) {
//         $this->XTick = $x_tick;
//         $this->YTick = $y_tick;
        foreach ([$this->YAxisLeft, $this->YAxisRight] as $ax) {
            $ax->setTick($x_tick, $y_tick);
        }
    }


}
