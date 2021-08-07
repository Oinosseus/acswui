<?php

namespace Svg;


class YAxis {
    private $IsLeft = NULL;
    private $XmllId = NULL;
    private $Plots = array();
    private $YMax = NULL;
    private $YMin = 0;
    private $XMax = NULL;
    private $XMin = 0;
    private $YScale = 1.0;
    private $XScale = 1.0;


    public function __construct(bool $is_left, string $xml_id) {
        $this->IsLeft = $is_left;
        $this->XmlId = $xml_id;
    }


    public function addPlot(DataPlot $plot) {
        $this->Plots[] = $plot;

        // remember absolute min/max
        [$min, $max] = $plot->yrange();
        if ($this->YMin === NULL || $min < $this->YMin) $this->YMin = $min;
        if ($this->YMax === NULL || $max > $this->YMax) $this->YMax = $max;
        [$min, $max] = $plot->xrange();
        if ($this->XMin === NULL || $min < $this->XMin) $this->XMin = $min;
        if ($this->XMax === NULL || $max > $this->XMax) $this->XMax = $max;
    }


    public function isLeft() {
        return $this->IsLeft;
    }


    public function xrange() {
        return [$this->XMin, $this->XMax];
    }


    public function yrange() {
        return [$this->YMin, $this->YMax];
    }


    public function drawXmlAxes() {
        // scale and round
        $ymin = 0 + ($this->yrange()[1] - $this->yrange()[0]) * 0.1 * $this->YScale;
        $ymax = round(-1 * $this->YScale * $this->YMax);

        if ($this->IsLeft) {
            $xpos = 0;
        } else {
            $xpos = $this->xrange()[1] * $this->XScale;
        }

        $id = $this->XmlId;
        return "<polyline id=\"$id\" points=\"$xpos,$ymin $xpos,$ymax\" style=\"marker-end:url(#AxisArrow)\" />";
    }


    public function drawXmlPlots() {
        $xml = "";
        foreach ($this->Plots as $p) {
            $xml .= $p->drawXml($this->XScale, $this->YScale);
        }
        return $xml;
    }


    public function setXScale(float $scale) {
        $this->XScale = $scale;
    }


    public function setYScale(float $scale) {
        $this->YScale = $scale;
    }
}
