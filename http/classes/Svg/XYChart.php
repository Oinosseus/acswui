<?php

namespace Svg;

//! SVG XY-Chart
class XYChart {

    private $CssClass = NULL;

    private $YAxisLeft = NULL;
    private $YAxisRight = NULL;

    public function __construct(string $css_class="") {
        $this->CssClass = $css_class;
    }


    public function addYAxis(YAxis $ax) {

        if ($ax->isLeft()) {
            if ($this->YAxisLeft !== NULL) {
                \Core\Log::error("Cannot add additional Y-Axis!");
                return;
            }
            $this->YAxisLeft = $ax;

        } else {
            if ($this->YAxisRight !== NULL) {
                \Core\Log::error("Cannot add additional Y-Axis!");
                return;
            }
            $this->YAxisRight = $ax;
        }
    }


    //! @return The XML (HTML) string of the SVG chart
    public function drawHtml(string $label, string $html_id, int $scale_x, int $scale_y) {



        // --------------------------------------------------------------------
        //                              X-Axis
        // --------------------------------------------------------------------

        $xml_xaxis = "";

        # x-axis
        $xaxis_min = NULL;
        $xaxis_max = NULL;
        foreach ([$this->YAxisLeft, $this->YAxisRight] as $ax) {
            if ($ax) {
                [$min, $max] = $ax->xrange();
                if ($xaxis_min === NULL || $min < $xaxis_min) $xaxis_min = $min;
                if ($xaxis_max === NULL || $max < $xaxis_max) $xaxis_max = $max;
            }
        }

        # enlarge axis ends by 10%
        $xaxis_span = $xaxis_max - $xaxis_min;
        $xaxis_min -= $xaxis_span * 0.1;
        $xaxis_max += $xaxis_span * 0.1;

        // scale and round
        $xaxis_min = round($scale_x * $xaxis_min);
        $xaxis_max = round($scale_x * $xaxis_max);

        // draw
        $xml_xaxis .= "<polyline id=\"XAxis\" points=\"$xaxis_min,0 $xaxis_max,0\" style=\"marker-end:url(#AxisArrow)\" />";


        // --------------------------------------------------------------------
        //                              Y-Axis
        // --------------------------------------------------------------------

        $xml_yaxes = "";

        $yaxis_min = 0;
        $yaxis_max = 0;

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
        }



        // --------------------------------------------------------------------
        //                           Plots
        // --------------------------------------------------------------------

        $xml_plots = "";
        if ($this->YAxisLeft) {
            $xml_plots .= "<g id=\"AxisLeftPlots\">";
            $xml_plots .= $this->YAxisLeft->drawXmlPlots($scale_x, $scale_y);
            $xml_plots .= "</g>";
        }
        if ($this->YAxisRight) {
            $xml_plots .= "<g id=\"AxisRightPlots\">";
            $xml_plots .= $this->YAxisRight->drawXmlPlots($scale_x, $scale_y);
            $xml_plots .= "</g>";
        }


        // --------------------------------------------------------------------
        //                                SVG
        // --------------------------------------------------------------------

        # head
        $class = $this->CssClass;
        $viewbox = sprintf("%d %d %d %d",
                           $xaxis_min - 30 * $scale_x,
                           -1 * ($yaxis_max + 30) * $scale_y,
                           ($xaxis_max - $xaxis_min) + 200 * $scale_x,
                           ($yaxis_max - $yaxis_min + 80) * $scale_y);
        $xml = "<svg class=\"SVGXYChart $class\" viewBox=\"$viewbox\">";

        $xml .= "<defs>";
        $width = 2 * $scale_x;
        $height = 1 * $scale_y;
        $xml .= "<marker id=\"AxisArrow\" markerWidth=\"$width\" markerHeight=\"$height\" refx=\"0\" refy=\"2\" orient=\"auto\">";
        $xml .= "<polyline points=\"0,0 6,2 0,4\"/>";
        $xml .= "</marker>";
        $xml .= "</defs>";

        # axes
        $xml .= "<g id=\"Axis\">";
        $xml .= $xml_xaxis;
        $xml .= $xml_yaxes;
        $xml .= "</g>";

        # plots
        $xml .= "<g id=\"Plots\">";
        $xml .= $xml_plots;
        $xml .= "</g>";

        $xml .= "</svg>";

        $html = "<div id=\"$html_id\" class=\"SvgChart\">";
        $html .= "<label>$label</label>";
        $html .= $xml;
        $html .= "</div>";
        return $html;
    }
}
