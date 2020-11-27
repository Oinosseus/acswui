<?php

/**
 * Cached wrapper to databse Sessions table element
 */
class Session {
    private $Id = NULL;
    private $ProtocolVersion = NULL;
    private $SessionIndex = NULL;
    private $CurrentSessionIndex = NULL;
    private $SessionCount = NULL;
    private $ServerName = NULL;
    private $Track = NULL;
    private $Name = NULL;
    private $Type = NULL;
    private $Time = NULL;
    private $Laps = NULL;
    private $WaitTime = NULL;
    private $TempAmb = NULL;
    private $TempRoad = NULL;
    private $WheatherGraphics = NULL;
    private $Elapsed = NULL;
    private $Timestamp = NULL;

    private $DrivenLaps = NULL;
    private $FirstDrivenLap = NULL;
    private $Drivers = NULL;

    /**
     * @param $id Database table id
     */
    public function __construct($id) {
        $this->Id = $id;
    }

    public function __toString() {
        return "Session(Id=" . $this->Id . ")";
    }

    //! @return The database table id
    public function id() {
        return $this->Id;
    }

    //! @todo Write a description
    public function protocolVersion() {
        if ($this->ProtocolVersion === NULL) $this->updateFromDb();
        return $this->ProtocolVersion;
    }

    //! @todo Write a description
    public function sessionIndex() {
        if ($this->SessionIndex === NULL) $this->updateFromDb();
        return $this->SessionIndex;
    }

    //! @todo Write a description
    public function currentSessionIndex() {
        if ($this->CurrentSessionIndex === NULL) $this->updateFromDb();
        return $this->CurrentSessionIndex;
    }

    //! @todo Write a description
    public function sessionCount() {
        if ($this->SessionCount === NULL) $this->updateFromDb();
        return $this->SessionCount;
    }

    //! @todo Write a description
    public function serverName() {
        if ($this->ServerName=== NULL) $this->updateFromDb();
        return $this->ServerName;
    }

    //! @return A Track object
    public function track() {
        if ($this->Track === NULL) $this->updateFromDb();
        return $this->Track;
    }

    //! @todo Write a description
    public function name() {
        if ($this->Name === NULL) $this->updateFromDb();
        return $this->Name;
    }

    //! @todo Write a description
    public function type() {
        if ($this->Type === NULL) $this->updateFromDb();
        return $this->Type;
    }

    //! @todo Write a description
    public function time() {
        if ($this->Time === NULL) $this->updateFromDb();
        return $this->Time;
    }

    //! @todo Write a description
    public function laps() {
        if ($this->Laps === NULL) $this->updateFromDb();
        return $this->Laps;
    }

    //! @todo Write a description
    public function waitTime() {
        if ($this->WaitTime === NULL) $this->updateFromDb();
        return $this->WaitTime;
    }

    //! @todo Write a description
    public function tempAmb() {
        if ($this->TempAmb === NULL) $this->updateFromDb();
        return $this->TempAmb;
    }

    //! @todo Write a description
    public function tempRoad() {
        if ($this->TempRoad === NULL) $this->updateFromDb();
        return $this->TempRoad;
    }

    //! @todo Write a description
    public function wheatherGraphics() {
        if ($this->WheatherGraphics === NULL) $this->updateFromDb();
        return $this->WheatherGraphics;
    }

    //! @todo Write a description
    public function elapsed() {
        if ($this->Elapsed === NULL) $this->updateFromDb();
        return $this->Elapsed;
    }

    //! @return A DateTime object trepresening the session start
    public function timestamp() {
        if ($this->Timestamp === NULL) $this->updateFromDb();
        return $this->Timestamp;
    }

    //! @return A list of Lap objects, driven in this session (descending by lap ID)
    public function drivenLaps() {
        if ($this->DrivenLaps === NULL) $this->updateDrivenLaps();
         return $this->DrivenLaps;
    }

    /**
     * This is intended to be used to determine session relative lap numbers:
     * $lap_number = $lap->id() - $session->firstDrivenLap()->id() + 1;
     * @return The Lap object of the first driven lap in this session
     */
    public function firstDrivenLap() {
        if ($this->FirstDrivenLap === NULL) $this->updateDrivenLaps();
         return $this->FirstDrivenLap;
    }

    //! @return A list of User objects that drove laps in this session
    public function drivers() {
        if ($this->Drivers === NULL) $this->updateDrivenLaps();
         return $this->Drivers;
    }

    private function updateDrivenLaps() {
        global $acswuiDatabase;
        $this->DrivenLaps = array();
        $this->Drivers = array();
        $lap = NULL;
        foreach ($acswuiDatabase->fetch_2d_array("Laps", ['Id'], ['Session'=>$this->Id], 'Id', FALSE) as $lap) {
            $lap = new Lap($lap['Id']);
            $this->DrivenLaps[] = $lap;

            $driver_already_listed = FALSE;
            foreach ($this->Drivers as $d) {
                if ($d->id() == $lap->user()->id()) {
                    $driver_already_listed = TRUE;
                    break;
                }
            }
            if ($driver_already_listed === FALSE) $this->Drivers[] = $lap->user();

        }
        $this->FirstDrivenLap = $lap;
    }


    private function updateFromDb() {
        global $acswuiDatabase;

        // request from db
        $columns = array();
        $columns[] = 'ProtocolVersion';
        $columns[] = 'SessionIndex';
        $columns[] = 'CurrentSessionIndex';
        $columns[] = 'SessionCount';
        $columns[] = 'ServerName';
        $columns[] = 'Track';
        $columns[] = 'Name';
        $columns[] = 'Type';
        $columns[] = 'Time';
        $columns[] = 'Laps';
        $columns[] = 'WaitTime';
        $columns[] = 'TempAmb';
        $columns[] = 'TempRoad';
        $columns[] = 'WheatherGraphics';
        $columns[] = 'Elapsed';
        $columns[] = 'Timestamp';

        $res = $acswuiDatabase->fetch_2d_array("Sessions", $columns, ['Id'=>$this->Id]);
        if (count($res) !== 1) {
            $acswuiLog->logError("Cannot find Sessions.Id=" . $this->Id);
            return;
        }

        $this->ProtocolVersion = $res[0]['ProtocolVersion'];
        $this->SessionIndex = $res[0]['SessionIndex'];
        $this->CurrentSessionIndex = $res[0]['CurrentSessionIndex'];
        $this->SessionCount = $res[0]['SessionCount'];
        $this->ServerName = $res[0]['ServerName'];
        $this->Track = new Track($res[0]['Track']);
        $this->Name = $res[0]['Name'];
        $this->Type = $res[0]['Type'];
        $this->Time = $res[0]['Time'];
        $this->Laps = $res[0]['Laps'];
        $this->WaitTime = $res[0]['WaitTime'];
        $this->TempAmb = $res[0]['TempAmb'];
        $this->TempRoad = $res[0]['TempRoad'];
        $this->WheatherGraphics = $res[0]['WheatherGraphics'];
        $this->Elapsed = $res[0]['Elapsed'];
        $this->Timestamp = new DateTime($res[0]['Timestamp']);
    }
}

?>
