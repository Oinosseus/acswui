<?php

namespace Core;

class ServerSlot {

    private static $SlotObjectCache = array();  // key=slot-id, value=ServerSlot-object

    private $Id = NULL;
    private $ParameterCollection = NULL;

    /**
     * Construct a new object
     * @param $id Database table id
     */
    private function __construct() {
    }


    //! @return An array of ServerPreset objects that are children of this preset
    public function children() {
        if ($this->ChildSlots === NULL) {
            $this->ChildSlots = array();

            $res = \Core\Database::fetch("ServerSlots", ['Id'], ['Parent'=>$this->id()], 'Name');
            foreach ($res as $row) {
                $this->ChildSlots[] = ServerSlot::fromId($row['Id']);
            }
        }
        return $this->ChildSlots;
    }


    //! @return A ServerSlot object, retreived by Slot-ID ($id=0 will return a base preset)
    public static function fromId(int $slot_id) {
        $ss = NULL;

        if ($slot_id > \Core\Config::ServerSlotAmount) {
            \Core\Log::error("Deny requesting slot-id '$slot_id' at maximum slot amount of '" . \Core\Config::ServerSlotAmount . "'!");
            return NULL;
        } else if ($slot_id < 0) {
            \Core\Log::error("Deny requesting negative slot-id '$slot_id'!");
            return NULL;
        } else if (array_key_exists($slot_id, ServerSlot::$SlotObjectCache)) {
            $ss = ServerSlot::$SlotObjectCache[$slot_id];
        } else {
            $ss = new ServerSlot();
            $ss->Id = $slot_id;
            ServerSlot::$SlotObjectCache[$ss->id()] = $ss;
        }

        return $ss;
    }


    //! @return The ID of the slot (number)
    public function id() {
        return $this->Id;
    }


    //! @return The name of the preset
    public function name() {
        if ($this->id() === 0) return _("Base Settings");
        else return "Slot " . $this->id();
    }


    //! @return The Collection object, that stores all parameters
    public function parameterCollection() {
        if ($this->ParameterCollection === NULL) {

            // create parameter collection
            if ($this->id() !== 0) {
                $base_collection = ServerSlot::fromId(0)->parameterCollection();
                $this->ParameterCollection = new \Parameter\Collection($base_collection, NULL);

            } else {
                $root_collection = new \Parameter\Collection(NULL, NULL, "ServerSlot", _("Server Slot"), _("Collection of server slot settings"));
                $p = new \Parameter\ParamString(NULL, $root_collection, "Name", _("Name"), _("An arbitrary name for the server slot (recommended to be unique)"), "", "");


                ////////////
                // acServer

                $pc = new \Parameter\Collection(NULL, $root_collection, "AcServer", _("acServer"), _("Settings for the actual acServer"));

                // ports
                $coll = new \Parameter\Collection(NULL, $pc, "PortsInet", _("Internet Ports"), _("Internet protocol port numbers for the AC server"));
                $p = new \Parameter\ParamInt(NULL, $coll, "UDP", "UDP", _("UDP port number: open this port on your server's firewall"), "", 9101);
                $p->setMin(1024);
                $p->setMax(65535);
                $p = new \Parameter\ParamInt(NULL, $coll, "TCP", "TCP", _("TCP port number: open this port on your server's firewall"), "", 9101);
                $p->setMin(1024);
                $p->setMax(65535);
                $p = new \Parameter\ParamInt(NULL, $coll, "HTTP", "HTTP", _("Lobby port number: open these ports (both UDP and TCP) on your server's firewall"), "", 9100);
                $p->setMin(1024);
                $p->setMax(65535);

                // plugin ports
                $coll = new \Parameter\Collection(NULL, $pc, "PortsPlugin", _("Plugin Ports"), _("UDP plugin port settings"));

                $p = new \Parameter\ParamInt(NULL, $coll, "UDP_R", "UDP_R", _("Remote UDP port for external plugins"), "", 9102);
                $p->setMin(1024);
                $p->setMax(65535);

                // performance
                $coll = new \Parameter\Collection(NULL, $pc, "Performance", _("Performance"), _("Settings that affect the transfer performance / quality"));
                $p = new \Parameter\ParamInt(NULL, $coll, "ClntIntvl", _("Client Interval"), _("Refresh rate of packet sending by the server. 10Hz = ~100ms. Higher number = higher MP quality = higher bandwidth resources needed. Really high values can create connection issues"), "Hz", 15);
                $p->setMin(1);
                $p->setMax(100);
                $p = new \Parameter\ParamInt(NULL, $coll, "Threads", _("Number of Threads"), _("Number of threads to run on"), "", 2);
                $p->setMin(1);
                $p->setMax(64);
                $p = new \Parameter\ParamInt(NULL, $coll, "MaxClients", _("Max Clients"), _("Max number of clients"), "", 25);
                $p->setMin(1);
                $p->setMax(999);


                ////////////////
                // Real Penalty

                $pc = new \Parameter\Collection(NULL, $root_collection, "RP", _("Real Penalty Plugin"), _("Settings for the Real Penalty plugin"));

                // genral
                $coll = new \Parameter\Collection(NULL, $pc, "General", _("General Settings"), _("General settings for Real Penalty"));

                $p = new \Parameter\ParamBool(NULL, $coll, "Enable", _("Enable RP"), _("Wheather to use Real Penalty for this slot or not"), "", FALSE);

                $p = new \Parameter\ParamString(NULL, $coll, "ProductKey", _("Product Key"), _("Personal key, received after the Patreon subscription: https://www.patreon.com/DavideBolognesi"), "", "");

                $p = new \Parameter\ParamString(NULL, $coll, "AdminPwd", _("Admin Passwort"), _("Password only for app 'Real Penalty - admin'"), "", "");

                // plugin ports
                $coll = new \Parameter\Collection(NULL, $pc, "PortsPlugin", _("Plugin Ports"), _("UDP plugin port settings"));

                $p = new \Parameter\ParamInt(NULL, $coll, "UDP_L", "UDP_L", _("Local UDP port to communicate with the acServer"), "", 9103);
                $p->setMin(1024);
                $p->setMax(65535);

                $p = new \Parameter\ParamInt(NULL, $coll, "UDP_R", "UDP_R", _("Remote UDP port for additional plugins"), "", 9104);
                $p->setMin(1024);
                $p->setMax(65535);

                // internet ports
                $coll = new \Parameter\Collection(NULL, $pc, "PortsInet", _("Internet Ports"), _("Port settings open to the internet"));

                $p = new \Parameter\ParamInt(NULL, $coll, "UDP", "UDP", _("UDP to communicate with RP client app"), "", 9105);
                $p->setMin(1024);
                $p->setMax(65535);


                /////////////////
                // ACswui Plugin

                $pc = new \Parameter\Collection(NULL, $root_collection, "ACswui", _("ACswui Plugin"), _("Settings for the ACswui plugin"));

                // plugin ports
                $coll = new \Parameter\Collection(NULL, $pc, "PortsPlugin", _("Plugin Ports"), _("UDP plugin port settings"));

                $p = new \Parameter\ParamInt(NULL, $coll, "UDP_L", "UDP_L", _("Local UDP port to communicate with the acServer"), "", 9106);
                $p->setMin(1024);
                $p->setMax(65535);



                // set all deriveable and visible
                function __adjust_derived_collection($collection) {
                    $collection->derivedAccessability(2);
                    foreach ($collection->children() as $child) {
                        __adjust_derived_collection($child);
                    }
                }
                __adjust_derived_collection($root_collection);

                // derive base collection from (invisible) root collection
                $this->ParameterCollection = new \Parameter\Collection($root_collection, NULL);
            }

            // load data from disk
            $file_path = \Core\Config::AbsPathData . "/server_slots/" . $this->id() . ".json";
            if (file_exists($file_path)) {
                $ret = file_get_contents($file_path);
                if ($ret === FALSE) {
                    \Core\Log::error("Cannot read from file '$file_path'!");
                } else {
                    $data_array = json_decode($ret, TRUE);
                    if ($data_array == NULL) {
                        \Core\Log::warning("Decoding NULL from json file '$file_path'.");
                    } else {
                        $this->parameterCollection()->dataArrayImport($data_array);
                    }
                }
            }
        }

        return $this->ParameterCollection;
    }



    //! Store settings to database
    public function save() {

        // prepare data
        $data_array = $this->parameterCollection()->dataArrayExport();
        $data_json = json_encode($data_array);

        // write to file
        $file_path = \Core\Config::AbsPathData . "/server_slots/" . $this->id() . ".json";
        $f = fopen($file_path, 'w');
        if ($f === FALSE) {
            \Core\Log::error("Cannot write to file '$file_path'!");
            return;
        }
        fwrite($f, $data_json);
        fclose($f);
    }
}
