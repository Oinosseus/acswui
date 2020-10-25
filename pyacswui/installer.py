import argparse
import subprocess
import shutil
import os
from .db_wrapper import DbWrapper

class Installer(object):

    def __init__(self, config, verbosity = 0, install_base_data = False):

        if type(config) != type({}):
            raise TypeError("Parameter 'config' must be dict!")

        # check http directory
        if 'path_http' not in config or not os.path.isdir(config['path_http']):
            raise NotImplementedError("Http directory '%s' invalid!" % config['path_http'])

        self.__config = {}
        self.__config.update(config)
        self.__verbosity = int(verbosity)
        self.__db = DbWrapper(config)
        self.__db.Verbosity = self.__verbosity - 1

        if install_base_data == True:
            self.__install_base_data = True
        else:
            self.__install_base_data = False



    def work(self):
        """
            1. create or update database tables
            2. create cConfig.php
            3. scan cars into database
            4. scan tracks into database
            5. install basic data
        """

        self.__work_db_tables()
        self.__work_cconfig()
        self.__work_scan_cars()
        self.__work_scan_tracks()
        self.__work_install_basics()


    def __parse_json(self, json_file, key_name, default_value):
        ret = default_value
        key_name = '"' + key_name + '":'
        if os.path.isfile(json_file):
            with open(json_file, "r", encoding='utf-8', errors='ignore') as f:
                for line in f.readlines():
                    if key_name in line.lower():
                        ret = line.split(key_name, 1)[1]
                        ret = ret.strip()
                        if ret[:1] == '"':
                            ret = ret[1:].strip()
                        if ret[-1:] == ',':
                            ret = ret[:-1].strip()
                        if ret[-1:] == '"':
                            ret = ret[:-1]
        return ret

    def __work_db_tables(self):

        # ------------
        #  red tables

        # check table installer
        if self.__verbosity > 0:
            print("check database table `installer`")
        self.__db.appendTable("installer")
        self.__db.appendColumnCurrentTimestamp("installer", "timestamp")
        self.__db.appendColumnString("installer", "version", 10)
        self.__db.appendColumnText("installer", "info")

        # insert installer info
        self.__db.insertRow("installer", {"version": "0.1a", "info": ""})

        # check table users
        if self.__verbosity > 0:
            print("check database table `Users`")
        self.__db.appendTable("Users")
        self.__db.appendColumnString("Users", "Login", 50)
        self.__db.appendColumnString("Users", "Password", 100)
        self.__db.appendColumnString("Users", "Steam64GUID", 50)

        # check table Groups
        if self.__verbosity > 0:
            print("check database table `Groups`")
        self.__db.appendTable("Groups")
        self.__db.appendColumnString("Groups", "Name", 50)

        # check table UserGroupMap
        if self.__verbosity > 0:
            print("check database table `UserGroupMap`")
        self.__db.appendTable("UserGroupMap")
        self.__db.appendColumnInt("UserGroupMap", "User")
        self.__db.appendColumnInt("UserGroupMap", "Group")


        # -------------
        #  grey tables

        # check table Cars
        if self.__verbosity > 0:
            print("check database table `Cars`")
        self.__db.appendTable("Cars")
        self.__db.appendColumnString("Cars", "Car", 80)
        self.__db.appendColumnString("Cars", "Name", 80)
        self.__db.appendColumnInt("Cars", "Parent")
        self.__db.appendColumnString("Cars", "Brand", 80)

        # check table CarSkins
        if self.__verbosity > 0:
            print("check database table `CarSkins`")
        self.__db.appendTable("CarSkins")
        self.__db.appendColumnInt("CarSkins", "Car")
        self.__db.appendColumnString("CarSkins", "Skin", 50)

        # check table Tracks
        if self.__verbosity > 0:
            print("check database table `Tracks`")
        self.__db.appendTable("Tracks")
        self.__db.appendColumnString("Tracks", "Track", 80)
        self.__db.appendColumnString("Tracks", "Config", 80)
        self.__db.appendColumnString("Tracks", "Name", 80)
        self.__db.appendColumnFloat("Tracks", "Length")
        self.__db.appendColumnInt("Tracks", "Pitboxes")


        # ----------------
        #  Server Logging

        if self.__verbosity > 0:
            print("check database table `Sessions`")
        self.__db.appendTable("Sessions")
        self.__db.appendColumnInt("Sessions", "ProtocolVersion")
        self.__db.appendColumnInt("Sessions", "SessionIndex")
        self.__db.appendColumnInt("Sessions", "CurrentSessionIndex")
        self.__db.appendColumnInt("Sessions", "SessionCount")
        self.__db.appendColumnString("Sessions", 'ServerName', 50)
        self.__db.appendColumnInt("Sessions", "Track")
        self.__db.appendColumnString("Sessions", 'Name', 50)
        self.__db.appendColumnInt("Sessions", "Type")
        self.__db.appendColumnInt("Sessions", "Time")
        self.__db.appendColumnInt("Sessions", "Laps")
        self.__db.appendColumnInt("Sessions", "WaitTime")
        self.__db.appendColumnInt("Sessions", "TempAmb")
        self.__db.appendColumnInt("Sessions", "TempRoad")
        self.__db.appendColumnString("Sessions", 'WheatherGraphics', 50)
        self.__db.appendColumnUInt("Sessions", "Elapsed")
        self.__db.appendColumnCurrentTimestamp("Sessions", "Timestamp")

        if self.__verbosity > 0:
            print("check database table `Laps`")
        self.__db.appendTable("Laps")
        self.__db.appendColumnInt("Laps", "Session")
        self.__db.appendColumnInt("Laps", "CarSkin")
        self.__db.appendColumnInt("Laps", "User")
        self.__db.appendColumnUInt("Laps", "Laptime")
        self.__db.appendColumnInt("Laps", "Cuts")
        self.__db.appendColumnFloat("Laps", "Grip")
        self.__db.appendColumnCurrentTimestamp("Laps", "Timestamp")

        if self.__verbosity > 0:
            print("check database table `CollisionEnv`")
        self.__db.appendTable("CollisionEnv")
        self.__db.appendColumnInt("CollisionEnv", "Session")
        self.__db.appendColumnInt("CollisionEnv", "CarSkin")
        self.__db.appendColumnInt("CollisionEnv", "User")
        self.__db.appendColumnFloat("CollisionEnv", "Speed")
        self.__db.appendColumnCurrentTimestamp("CollisionEnv", "Timestamp")

        if self.__verbosity > 0:
            print("check database table `CollisionCar`")
        self.__db.appendTable("CollisionCar")
        self.__db.appendColumnInt("CollisionCar", "Session")
        self.__db.appendColumnInt("CollisionCar", "CarSkin")
        self.__db.appendColumnInt("CollisionCar", "User")
        self.__db.appendColumnFloat("CollisionCar", "Speed")
        self.__db.appendColumnInt("CollisionCar", "OtherUser")
        self.__db.appendColumnInt("CollisionCar", "OtherCarSkin")
        self.__db.appendColumnCurrentTimestamp("CollisionCar", "Timestamp")



        # -------------
        #  blue tables

        if self.__verbosity > 0:
            print("check database table `ServerPresets`")
        self.__db.appendTable("ServerPresets")
        self.__db.appendColumnString("ServerPresets", 'Name', 60)
        self.__db.appendColumnString("ServerPresets", 'srv_NAME', 60)
        self.__db.appendColumnString("ServerPresets", 'srv_CARS', 255)
        self.__db.appendColumnString("ServerPresets", 'srv_TRACK', 50)
        self.__db.appendColumnString("ServerPresets", 'srv_CONFIG_TRACK', 50)
        self.__db.appendColumnInt("ServerPresets",    'srv_SUN_ANGLE')
        self.__db.appendColumnInt("ServerPresets",    'srv_MAX_CLIENTS')
        self.__db.appendColumnInt("ServerPresets",    'srv_RACE_OVER_TIME')
        self.__db.appendColumnInt("ServerPresets",    'srv_ALLOWED_TYRES_OUT')
        self.__db.appendColumnInt("ServerPresets",    'srv_UDP_PORT')
        self.__db.appendColumnInt("ServerPresets",    'srv_TCP_PORT')
        self.__db.appendColumnInt("ServerPresets",    'srv_HTTP_PORT')
        self.__db.appendColumnString("ServerPresets", 'srv_PASSWORD', 50)
        self.__db.appendColumnInt("ServerPresets",    'srv_LOOP_MODE')
        self.__db.appendColumnInt("ServerPresets",    'srv_REGISTER_TO_LOBBY')
        self.__db.appendColumnInt("ServerPresets",    'srv_PICKUP_MODE_ENABLED')
        self.__db.appendColumnInt("ServerPresets",    'srv_SLEEP_TIME')
        self.__db.appendColumnInt("ServerPresets",    'srv_VOTING_QUORUM')
        self.__db.appendColumnInt("ServerPresets",    'srv_VOTE_DURATION')
        self.__db.appendColumnInt("ServerPresets",    'srv_BLACKLIST_MODE')
        self.__db.appendColumnInt("ServerPresets",    'srv_TC_ALLOWED')
        self.__db.appendColumnInt("ServerPresets",    'srv_ABS_ALLOWED')
        self.__db.appendColumnInt("ServerPresets",    'srv_STABILITY_ALLOWED')
        self.__db.appendColumnInt("ServerPresets",    'srv_AUTOCLUTCH_ALLOWED')
        self.__db.appendColumnInt("ServerPresets",    'srv_DAMAGE_MULTIPLIER')
        self.__db.appendColumnInt("ServerPresets",    'srv_FUEL_RATE')
        self.__db.appendColumnInt("ServerPresets",    'srv_TYRE_WEAR_RATE')
        self.__db.appendColumnInt("ServerPresets",    'srv_CLIENT_SEND_INTERVAL_HZ')
        self.__db.appendColumnInt("ServerPresets",    'srv_TYRE_BLANKETS_ALLOWED')
        self.__db.appendColumnString("ServerPresets", 'srv_ADMIN_PASSWORD', 50)
        self.__db.appendColumnInt("ServerPresets",    'srv_QUALIFY_MAX_WAIT_PERC')
        self.__db.appendColumnText("ServerPresets",   'srv_WELCOME_MESSAGE')
        self.__db.appendColumnInt("ServerPresets",    'srv_FORCE_VIRTUAL_MIRROR')
        self.__db.appendColumnString("ServerPresets", 'srv_LEGAL_TYRES', 30)
        self.__db.appendColumnInt("ServerPresets",    'srv_MAX_BALLAST_KG')
        self.__db.appendColumnInt("ServerPresets",    'srv_UDP_PLUGIN_LOCAL_PORT')
        self.__db.appendColumnString("ServerPresets", 'srv_UDP_PLUGIN_ADDRESS', 150)
        self.__db.appendColumnString("ServerPresets", 'srv_AUTH_PLUGIN_ADDRESS', 150)

        self.__db.appendColumnInt("ServerPresets",    'dyt_SESSION_START')
        self.__db.appendColumnInt("ServerPresets",    'dyt_RANDOMNESS')
        self.__db.appendColumnInt("ServerPresets",    'dyt_LAP_GAIN')
        self.__db.appendColumnInt("ServerPresets",    'dyt_SESSION_TRANSFER')

        self.__db.appendColumnString("ServerPresets", 'bok_NAME', 50)
        self.__db.appendColumnInt("ServerPresets",    'bok_TIME')

        self.__db.appendColumnString("ServerPresets", 'prt_NAME', 50)
        self.__db.appendColumnInt("ServerPresets",    'prt_TIME')
        self.__db.appendColumnInt("ServerPresets",    'prt_IS_OPEN')

        self.__db.appendColumnString("ServerPresets", 'qly_NAME', 50)
        self.__db.appendColumnInt("ServerPresets",    'qly_TIME')
        self.__db.appendColumnInt("ServerPresets",    'qly_IS_OPEN')

        self.__db.appendColumnString("ServerPresets", 'rce_NAME', 50)
        self.__db.appendColumnInt("ServerPresets",    'rce_LAPS')
        self.__db.appendColumnInt("ServerPresets",    'rce_WAIT_TIME')
        self.__db.appendColumnInt("ServerPresets",    'rce_IS_OPEN')

        self.__db.appendColumnString("ServerPresets", 'wth_GRAPHICS', 50)
        self.__db.appendColumnInt("ServerPresets",    'wth_BASE_TEMPERATURE_AMBIENT')
        self.__db.appendColumnInt("ServerPresets",    'wth_VARIATION_AMBIENT')
        self.__db.appendColumnInt("ServerPresets",    'wth_BASE_TEMPERATURE_ROAD')
        self.__db.appendColumnInt("ServerPresets",    'wth_VARIATION_ROAD')


        # --------------
        #  Car Classes

        if self.__verbosity > 0:
            print("check database table `CarClasses`")
        self.__db.appendTable("CarClasses")
        self.__db.appendColumnString("CarClasses", 'Name', 50)

        if self.__verbosity > 0:
            print("check database table `CarClassesMap`")
        self.__db.appendTable("CarClassesMap")
        self.__db.appendColumnInt("CarClassesMap", 'CarClass')
        self.__db.appendColumnInt("CarClassesMap", 'Car')
        self.__db.appendColumnInt("CarClassesMap", 'Ballast')


        # --------------
        #  Race Series

        if self.__verbosity > 0:
            print("check database table `RaceSeries`")
        self.__db.appendTable("RaceSeries")
        self.__db.appendColumnString("RaceSeries", 'Name', 50)

        if self.__verbosity > 0:
            print("check database table `RaceSeriesMap`")
        self.__db.appendTable("RaceSeriesMap")
        self.__db.appendColumnInt("RaceSeriesMap", 'RaceSeries')
        self.__db.appendColumnInt("RaceSeriesMap", 'CarClass')




    def __work_cconfig(self):

        # user info
        if self.__verbosity > 0:
            print("create cConfig.php")

        # encrypt root password
        http_root_password = subprocess.check_output(['php', '-r', 'echo(password_hash("%s", PASSWORD_BCRYPT));' % self.__config['http_root_passwd']])
        http_root_password = http_root_password.decode("utf-8")

        # path to

        # find server_cfg settings [SERVER]
        srv_cfg_server    = ""
        for key in ['NAME', 'CARS', 'TRACK', 'CONFIG_TRACK', 'SUN_ANGLE', 'MAX_CLIENTS', 'RACE_OVER_TIME', 'ALLOWED_TYRES_OUT', 'UDP_PORT', 'TCP_PORT', 'HTTP_PORT', 'PASSWORD', 'LOOP_MODE', 'REGISTER_TO_LOBBY', 'PICKUP_MODE_ENABLED', 'SLEEP_TIME', 'VOTING_QUORUM', 'VOTE_DURATION', 'BLACKLIST_MODE', 'TC_ALLOWED', 'ABS_ALLOWED', 'STABILITY_ALLOWED', 'AUTOCLUTCH_ALLOWED', 'DAMAGE_MULTIPLIER', 'FUEL_RATE', 'TYRE_WEAR_RATE', 'CLIENT_SEND_INTERVAL_HZ', 'TYRE_BLANKETS_ALLOWED', 'ADMIN_PASSWORD', 'QUALIFY_MAX_WAIT_PERC', 'WELCOME_MESSAGE', 'FORCE_VIRTUAL_MIRROR', 'LEGAL_TYRES', 'MAX_BALLAST_KG', 'UDP_PLUGIN_LOCAL_PORT', 'UDP_PLUGIN_ADDRESS', 'AUTH_PLUGIN_ADDRESS']:
            config_key = "http_srv_" + key
            if config_key in self.__config and len(self.__config[config_key]) > 0:
                if len(srv_cfg_server) > 0:
                    srv_cfg_server += ", "
                srv_cfg_server += "'" + key + "' => '" + self.__config[config_key] + "'"

        # find server_cfg settings [DYNAMIC_TRACK]
        srv_cfg_dyntrck    = ""
        for key in ['SESSION_START', 'RANDOMNESS', 'LAP_GAIN', 'SESSION_TRANSFER']:
            config_key = "http_dyt_" + key
            if config_key in self.__config and len(self.__config[config_key]) > 0:
                if len(srv_cfg_dyntrck) > 0:
                    srv_cfg_dyntrck += ", "
                srv_cfg_dyntrck += "'" + key + "' => '" + self.__config[config_key] + "'"

        # find server_cfg settings [BOOKING]
        srv_cfg_book    = ""
        for key in ['NAME', 'TIME']:
            config_key = "http_bok_" + key
            if config_key in self.__config and len(self.__config[config_key]) > 0:
                if len(srv_cfg_book) > 0:
                    srv_cfg_book += ", "
                srv_cfg_book += "'" + key + "' => '" + self.__config[config_key] + "'"

        # find server_cfg settings [PRACTICE]
        srv_cfg_prcts    = ""
        for key in ['NAME', 'TIME', 'IS_OPEN']:
            config_key = "http_prt_" + key
            if config_key in self.__config and len(self.__config[config_key]) > 0:
                if len(srv_cfg_prcts) > 0:
                    srv_cfg_prcts += ", "
                srv_cfg_prcts += "'" + key + "' => '" + self.__config[config_key] + "'"

        # find server_cfg settings [QUALIFY]
        srv_cfg_qly    = ""
        for key in ['NAME', 'TIME', 'IS_OPEN']:
            config_key = "http_qly_" + key
            if config_key in self.__config and len(self.__config[config_key]) > 0:
                if len(srv_cfg_qly) > 0:
                    srv_cfg_qly += ", "
                srv_cfg_qly += "'" + key + "' => '" + self.__config[config_key] + "'"

        # find server_cfg settings [RACE]
        srv_cfg_rce    = ""
        for key in ['NAME', 'LAPS', 'WAIT_TIME', 'IS_OPEN']:
            config_key = "http_rce_" + key
            if config_key in self.__config and len(self.__config[config_key]) > 0:
                if len(srv_cfg_rce) > 0:
                    srv_cfg_rce += ", "
                srv_cfg_rce += "'" + key + "' => '" + self.__config[config_key] + "'"

        # find server_cfg settings [WEATHER]
        srv_cfg_wth    = ""
        for key in ['GRAPHICS', 'BASE_TEMPERATURE_AMBIENT', 'VARIATION_AMBIENT', 'BASE_TEMPERATURE_ROAD', 'VARIATION_ROAD']:
            config_key = "http_wth_" + key
            if config_key in self.__config and len(self.__config[config_key]) > 0:
                if len(srv_cfg_wth) > 0:
                    srv_cfg_wth += ", "
                srv_cfg_wth += "'" + key + "' => '" + self.__config[config_key] + "'"


        with open(self.__config['path_http'] + "/classes/cConfig.php", "w") as f:
            f.write("<?php\n")
            f.write("  class cConfig {\n")
            f.write("\n")
            f.write("    // basic constants\n")
            f.write("    private $DefaultTemplate = \"%s\";\n" % self.__config['http_dflt_tmplt'])
            f.write("    private $LogPath = '%s';\n" % self.__config['http_log_path'])
            f.write("    private $LogDebug = \"false\";\n")
            f.write("    private $RootPassword = '%s';\n" % http_root_password)
            f.write("    private $GuestGroup = '%s';\n" % self.__config['http_guest_group'])
            f.write("\n")
            f.write("    // database constants\n")
            f.write("    private $DbType = \"%s\";\n" % self.__config['db_type'])
            f.write("    private $DbHost = \"%s\";\n" % self.__config['db_host'])
            f.write("    private $DbDatabase = \"%s\";\n" % self.__config['db_database'])
            f.write("    private $DbPort = \"%s\";\n" % self.__config['db_port'])
            f.write("    private $DbUser = \"%s\";\n" % self.__config['db_user'])
            f.write("    private $DbPasswd = \"%s\";\n" % self.__config['db_passwd'])
            f.write("\n")
            f.write("    // server_cfg\n")
            f.write("    private $SrvCfg_Server = [" + srv_cfg_server + "];\n")
            f.write("    private $SrvCfg_DynTrack = [" + srv_cfg_dyntrck + "];\n")
            f.write("    private $SrvCfg_Booking = [" + srv_cfg_book + "];\n")
            f.write("    private $SrvCfg_Practice = [" + srv_cfg_prcts + "];\n")
            f.write("    private $SrvCfg_Qualify = [" + srv_cfg_qly + "];\n")
            f.write("    private $SrvCfg_Race = [" + srv_cfg_rce + "];\n")
            f.write("    private $SrvCfg_Weather = [" + srv_cfg_wth + "];\n")
            f.write("\n")
            f.write("    // this allows read-only access to private properties\n")
            f.write("    public function __get($name) {\n")
            f.write("      return $this->$name;\n")
            f.write("    }\n")
            f.write("  }\n")
            f.write("?>\n")



    def __work_scan_cars(self):

        for car in os.listdir(self.__config['path_http'] + "/acs_content/cars"):
            car_path   = self.__config['path_http'] + "/acs_content/cars/" + car
            car_name   = self.__parse_json(car_path + "/ui/ui_car.json", "name", car)
            car_parent = self.__parse_json(car_path + "/ui/ui_car.json", "parent", "")
            car_brand  = self.__parse_json(car_path + "/ui/ui_car.json", "brand", "")

            # get skins
            car_skins = []
            if os.path.isdir(self.__config['path_http'] + "/acs_content/cars/" + car + "/skins"):
                for skin in os.listdir(self.__config['path_http'] + "/acs_content/cars/" + car + "/skins"):
                    car_skins.append(skin)

            if self.__verbosity > 0:
                print("Install car '" + car + "'")

            # get IDs of existing cars (should be exactly one car)
            existing_car_ids = self.__db.findIds("Cars", {"Car": car})

            # insert car if not existent
            if len(existing_car_ids) == 0:
                self.__db.insertRow("Cars", {"Car": car, "Name": car_name, "Parent": 0, "Brand": car_brand})
                existing_car_ids = self.__db.findIds("Cars", {"Car": car})

            # update all existing cars
            for eci in existing_car_ids:
                self.__db.updateRow("Cars", eci, {"Car": car, "Name": car_name, "Parent": 0, "Brand": car_brand})

                # insert not existing skins
                for skin in car_skins:
                    existing_car_skins = self.__db.findIds("CarSkins", {"Car": eci, "Skin": skin})
                    if len(existing_car_skins) == 0:
                        self.__db.insertRow("CarSkins", {"Car": eci, "Skin": skin})



    def __work_scan_tracks(self):

        def interpret_length(length):
            ret = ""

            length = str(length.strip())

            # catch decimal number out of string
            sep_found    = False
            for char in length:
                if char in "0123456789":
                    ret += char
                elif len(ret) > 0 and char in ".,":
                    if not sep_found:
                        sep_found = True
                        ret += char
                    else:
                        break
                else:
                    break
            ret = ret.strip()

            # stop if to decimal value could be found
            if len(ret) == 0:
                return 0.0

            # catch unit if length
            unit = length.split(ret, 1)[1].strip()

            # convert length to float
            ret = float(ret.replace(",", "."))

            # interpret unit
            if unit[:2].lower() == "km":
                ret *= 1000.0

            #print("LENGTH before=", length, "after=", ret, "unit=", unit)
            return ret

        def interpret_pitboxes(pitbxs):
            ret = 0
            for char in pitbxs:
                if char in "0123456789":
                    ret *= 10
                    ret += int(char)
                else:
                    break
            return ret



        for track in os.listdir(self.__config['path_http'] + "/acs_content/tracks"):
            track_path   = self.__config['path_http'] + "/acs_content/tracks/" + track

            if self.__verbosity > 0:
                print("Install track '" + track + "'")

            # update track
            if os.path.isfile(track_path + "/ui/ui_track.json"):
                track_name   = self.__parse_json(track_path + "/ui/ui_track.json", "name", track)
                track_length = self.__parse_json(track_path + "/ui/ui_track.json", "length", "0")
                track_length = interpret_length(track_length)
                track_pitbxs = interpret_pitboxes(self.__parse_json(track_path + "/ui/ui_track.json", "pitboxes", "0"))

                existing_track_ids = self.__db.findIds("Tracks", {"Track": track})
                if len(existing_track_ids) == 0:
                    self.__db.insertRow("Tracks", {"Track": track, "Config": "", "Name": track_name, "Length": track_length, "Pitboxes": track_pitbxs})
                else:
                    self.__db.updateRow("Tracks", existing_track_ids[0], {"Track": track, "Config": "", "Name": track_name, "Length": track_length, "Pitboxes": track_pitbxs})

            # update track configs
            if os.path.isdir(track_path + "/ui"):
                for track_config in os.listdir(track_path + "/ui"):
                    if os.path.isdir(track_path + "/ui/" + track_config):
                        if os.path.isfile(track_path + "/ui/" + track_config + "/ui_track.json"):
                            track_name   = self.__parse_json(track_path + "/ui/" + track_config + "/ui_track.json", "name", track)
                            track_length = self.__parse_json(track_path + "/ui/" + track_config + "/ui_track.json", "length", "0")
                            track_length = interpret_length(track_length)
                            track_pitbxs = interpret_pitboxes(self.__parse_json(track_path + "/ui/" + track_config + "/ui_track.json", "pitboxes", "0"))

                            existing_track_ids = self.__db.findIds("Tracks", {"Track": track, "Config": track_config})
                            if len(existing_track_ids) == 0:
                                self.__db.insertRow("Tracks", {"Track": track, "Config": track_config, "Name": track_name, "Length": track_length, "Pitboxes": track_pitbxs})
                            else:
                                self.__db.updateRow("Tracks", existing_track_ids[0], {"Track": track, "Config": track_config, "Name": track_name, "Length": track_length, "Pitboxes": track_pitbxs})



    def __work_install_basics(self):

        if self.__verbosity > 0:
            print("Install Base Data")

        # add guest group
        if 'http_guest_group' in self.__config and len(self.__config['http_guest_group']) > 0:
            if len(self.__db.findIds("Groups", {"Name": self.__config['http_guest_group']})) == 0:
                self.__db.insertRow("Groups", {"Name": self.__config['http_guest_group']})

        # optional data
        if self.__install_base_data:

            # default groups
            if len(self.__db.findIds("Groups", {"Name": "Driver"})) == 0:
                self.__db.insertRow("Groups", {"Name": "Driver"})
            if len(self.__db.findIds("Groups", {"Name": "Race Orga"})) == 0:
                self.__db.insertRow("Groups", {"Name": "Race Orga"})
            if len(self.__db.findIds("Groups", {"Name": "Server Admin"})) == 0:
                self.__db.insertRow("Groups", {"Name": "Server Admin"})

            # default server preset 'easy'
            srv_preset_easy = {}
            srv_preset_easy.update({'Name': "Easy Drivin'"})
            srv_preset_easy.update({'srv_SUN_ANGLE': '-8'})
            srv_preset_easy.update({'srv_TC_ALLOWED': '2'})
            srv_preset_easy.update({'srv_ABS_ALLOWED': '2'})
            srv_preset_easy.update({'srv_STABILITY_ALLOWED': '1'})
            srv_preset_easy.update({'srv_AUTOCLUTCH_ALLOWED': '1'})
            srv_preset_easy.update({'srv_DAMAGE_MULTIPLIER': '0'})
            srv_preset_easy.update({'srv_FUEL_RATE': '0'})
            srv_preset_easy.update({'srv_TYRE_WEAR_RATE': '0'})
            srv_preset_easy.update({'srv_TYRE_BLANKETS_ALLOWED': '1'})
            srv_preset_easy.update({'srv_WELCOME_MESSAGE': 'Easy driving without damage.'})
            srv_preset_easy.update({'srv_FORCE_VIRTUAL_MIRROR': '0'})
            srv_preset_easy.update({'srv_LEGAL_TYRES': 'V;E;HR;ST'})
            srv_preset_easy.update({'srv_MAX_BALLAST_KG': '50'})
            srv_preset_easy.update({'dyt_SESSION_START': '100'})
            srv_preset_easy.update({'dyt_RANDOMNESS': '0'})
            srv_preset_easy.update({'dyt_LAP_GAIN': '1'})
            srv_preset_easy.update({'dyt_SESSION_TRANSFER': '100'})
            srv_preset_easy.update({'prt_NAME': 'Practice'})
            srv_preset_easy.update({'prt_TIME': '10'})
            srv_preset_easy.update({'prt_IS_OPEN': '1'})
            srv_preset_easy.update({'qly_NAME': 'Qualify'})
            srv_preset_easy.update({'qly_TIME': '15'})
            srv_preset_easy.update({'qly_IS_OPEN': '1'})
            srv_preset_easy.update({'rce_NAME': 'Race'})
            srv_preset_easy.update({'rce_LAPS': '5'})
            srv_preset_easy.update({'rce_WAIT_TIME': '30'})
            srv_preset_easy.update({'rce_IS_OPEN': '1'})
            srv_preset_easy.update({'wth_GRAPHICS': '3_clear'})
            srv_preset_easy.update({'wth_BASE_TEMPERATURE_AMBIENT': '22'})
            srv_preset_easy.update({'wth_VARIATION_AMBIENT': '2'})
            srv_preset_easy.update({'wth_BASE_TEMPERATURE_ROAD': '9'})
            srv_preset_easy.update({'wth_VARIATION_ROAD': '2'})
            if len(self.__db.findIds("ServerPresets", {"Name": "Easy Drivin'"})) == 0:
                self.__db.insertRow("ServerPresets", srv_preset_easy)

            # default server preset 'pro'
            srv_preset_easy = {}
            srv_preset_easy.update({'Name': "Professional"})
            srv_preset_easy.update({'srv_SUN_ANGLE': '-18'})
            srv_preset_easy.update({'srv_TC_ALLOWED': '1'})
            srv_preset_easy.update({'srv_ABS_ALLOWED': '1'})
            srv_preset_easy.update({'srv_STABILITY_ALLOWED': '0'})
            srv_preset_easy.update({'srv_AUTOCLUTCH_ALLOWED': '0'})
            srv_preset_easy.update({'srv_DAMAGE_MULTIPLIER': '50'})
            srv_preset_easy.update({'srv_FUEL_RATE': '100'})
            srv_preset_easy.update({'srv_TYRE_WEAR_RATE': '100'})
            srv_preset_easy.update({'srv_TYRE_BLANKETS_ALLOWED': '0'})
            srv_preset_easy.update({'srv_WELCOME_MESSAGE': 'Professional driving simulation.'})
            srv_preset_easy.update({'srv_FORCE_VIRTUAL_MIRROR': '0'})
            srv_preset_easy.update({'srv_LEGAL_TYRES': 'V;E;HR;ST'})
            srv_preset_easy.update({'srv_MAX_BALLAST_KG': '50'})
            srv_preset_easy.update({'dyt_SESSION_START': '80'})
            srv_preset_easy.update({'dyt_RANDOMNESS': '5'})
            srv_preset_easy.update({'dyt_LAP_GAIN': '2'})
            srv_preset_easy.update({'dyt_SESSION_TRANSFER': '90'})
            srv_preset_easy.update({'prt_NAME': 'Practice'})
            srv_preset_easy.update({'prt_TIME': '60'})
            srv_preset_easy.update({'prt_IS_OPEN': '1'})
            srv_preset_easy.update({'qly_NAME': 'Qualify'})
            srv_preset_easy.update({'qly_TIME': '20'})
            srv_preset_easy.update({'qly_IS_OPEN': '1'})
            srv_preset_easy.update({'rce_NAME': 'Race'})
            srv_preset_easy.update({'rce_LAPS': '15'})
            srv_preset_easy.update({'rce_WAIT_TIME': '60'})
            srv_preset_easy.update({'rce_IS_OPEN': '1'})
            srv_preset_easy.update({'wth_GRAPHICS': '3_clear'})
            srv_preset_easy.update({'wth_BASE_TEMPERATURE_AMBIENT': '18'})
            srv_preset_easy.update({'wth_VARIATION_AMBIENT': '5'})
            srv_preset_easy.update({'wth_BASE_TEMPERATURE_ROAD': '9'})
            srv_preset_easy.update({'wth_VARIATION_ROAD': '5'})
            if len(self.__db.findIds("ServerPresets", {"Name": "Professional"})) == 0:
                self.__db.insertRow("ServerPresets", srv_preset_easy)

            # default server preset 'hotlap'
            srv_preset_easy = {}
            srv_preset_easy.update({'Name': "Hotlapping"})
            srv_preset_easy.update({'srv_SUN_ANGLE': '-8'})
            srv_preset_easy.update({'srv_TC_ALLOWED': '1'})
            srv_preset_easy.update({'srv_ABS_ALLOWED': '1'})
            srv_preset_easy.update({'srv_STABILITY_ALLOWED': '0'})
            srv_preset_easy.update({'srv_AUTOCLUTCH_ALLOWED': '0'})
            srv_preset_easy.update({'srv_DAMAGE_MULTIPLIER': '20'})
            srv_preset_easy.update({'srv_FUEL_RATE': '50'})
            srv_preset_easy.update({'srv_TYRE_WEAR_RATE': '30'})
            srv_preset_easy.update({'srv_TYRE_BLANKETS_ALLOWED': '1'})
            srv_preset_easy.update({'srv_WELCOME_MESSAGE': 'Perfect server for training hotlaps.'})
            srv_preset_easy.update({'srv_FORCE_VIRTUAL_MIRROR': '0'})
            srv_preset_easy.update({'srv_LEGAL_TYRES': 'V;E;HR;ST'})
            srv_preset_easy.update({'srv_MAX_BALLAST_KG': '50'})
            srv_preset_easy.update({'dyt_SESSION_START': '100'})
            srv_preset_easy.update({'dyt_RANDOMNESS': '0'})
            srv_preset_easy.update({'dyt_LAP_GAIN': '2'})
            srv_preset_easy.update({'dyt_SESSION_TRANSFER': '100'})
            srv_preset_easy.update({'prt_NAME': 'Practice'})
            srv_preset_easy.update({'prt_TIME': '60'})
            srv_preset_easy.update({'prt_IS_OPEN': '1'})
            srv_preset_easy.update({'qly_NAME': ''})
            srv_preset_easy.update({'rce_NAME': ''})
            srv_preset_easy.update({'wth_GRAPHICS': '3_clear'})
            srv_preset_easy.update({'wth_BASE_TEMPERATURE_AMBIENT': '25'})
            srv_preset_easy.update({'wth_VARIATION_AMBIENT': '1'})
            srv_preset_easy.update({'wth_BASE_TEMPERATURE_ROAD': '9'})
            srv_preset_easy.update({'wth_VARIATION_ROAD': '1'})
            if len(self.__db.findIds("ServerPresets", {"Name": "Hotlapping"})) == 0:
                self.__db.insertRow("ServerPresets", srv_preset_easy)
