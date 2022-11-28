<?php

declare(strict_types=1);
namespace Compound;

/**
 * This Class offers a summary for multiple SessionPenalty objects
 */
class SessionPenaltiesSum {

    private $Penalties = array();
    private $Session = NULL;

    private $PenDnf = FALSE;
    private $PenDsq = FALSE;
    private $PenLaps = 0;
    private $PenPts = 0;
    private $PenSf = 0;
    private $PenTime = 0;


    /**
     * This will request \DbEntry\SessionPenalty::listPenalties()
     *
     * When $entry is a User object, then the user is ignored for TeamCars (only penalties for single driver)
     *
     * @param $session The requested session
     * @param $entry If set, only penalties for a certain driver are listed
     */
    public function __construct(\DbEntry\Session $session,
                                \Compound\SessionEntry $entry=NULL) {
        $this->Penalties = \DbEntry\SessionPenalty::listPenalties($session, $entry);
        $this->Session = $session;

        // sum penalties
        foreach ($this->Penalties as $spen) {
            $this->PenDnf = $this->PenDnf || $spen->penDnf();
            $this->PenDsq = $this->PenDsq || $spen->penDsq();
            $this->PenLaps += $spen->penLaps();
            $this->PenPts += $spen->penPts();
            $this->PenSf += $spen->penSf();
            $this->PenTime += $spen->penTime();
        }
    }


    //! @return An Html string that sums all penalties
    public function getHtml() : string {
        $html = "";

        $penalties = array();
        $css_good = "PenaltyGood";
        $css_bad = "PenaltyBad";
        if ($this->penSf() != 0) {
            $css = ($this->penSf() < 0) ? $css_bad : $css_good;
            $penalties[] = "<span class=\"$css\">" . sprintf("%+dSF", $this->penSf()) . "</span>";
        }
        if ($this->penTime() != 0) {
            $css = ($this->penTime() > 0) ? $css_bad : $css_good;
            $penalties[] = "<span class=\"$css\">" . sprintf("%+ds", $this->penTime()) . "</span>";
        }
        if ($this->penPts() != 0) {
            $css = ($this->penPts() < 0) ? $css_bad : $css_good;
            $penalties[] = "<span class=\"$css\">" . sprintf("%+dpts", $this->penPts()) . "</span>";
        }
        if ($this->penLaps() != 0) {
            $css = ($this->penLaps() < 0) ? $css_bad : $css_good;
            $penalties[] = "<span class=\"$css\">" . sprintf("%+dL", $this->penLaps()) . "</span>";
        }
        if ($this->penDnf() != 0) $penalties[] = "<span class=\"$css_bad\">DNF</span>";
        if ($this->penDsq() != 0) $penalties[] = "<span class=\"$css_bad\">DSQ</span>";

        $html .= implode(", ", $penalties);
        return "<div class=\"CompoundSessionPenaltiesSum\">{$html}</div>";
    }


    //! @return The assigned penalty is a DNF
    public function penDnf() : bool {
        return $this->PenDnf;
    }


    //! @return The assigned penalty is a DSQ
    public function penDsq() : bool {
        return $this->PenDsq;
    }


    //! @return The assigned penalty for laps
    public function penLaps() : int {
        return $this->PenLaps;
    }


    //! @return The assigned penalty for points (points for championships)
    public function penPts() : int {
        return $this->PenPts;
    }


    //! @return The assigned penalty for safety points
    public function penSf() : int {
        return $this->PenSf;
    }


    //! @return The assigned penalty for total time
    public function penTime() : int {
        return $this->PenTime;
    }


    //! @return The according session where this penalty was imposed
    public function session() : Session {
        return $this->Session;
    }


}
