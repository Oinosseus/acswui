import argparse
import subprocess
import shutil
import os
import json
import re
import pymysql
from .command import Command, ArgumentException
from .database import Database
from .verbosity import Verbosity
from .installer_tracks import InstallerTracks
from .installer_cars import InstallerCars
from .installer_database import InstallerDatabase

class CommandInstall(Command):

    def __init__(self, argparser):
        Command.__init__(self, argparser, "install", "Server-Installer -  install http from server package")

        self.add_argument('--root-password', help="Password for root user")
        self.add_argument('--guest-group', default="Visitor", help="Group name of visitors that are not logged in")
        self.add_argument('--default-template', default="acswui", help="Default template for http")
        self.add_argument('--base-data', action="store_true", help="install basic http data (default groups, etc.)")
        self.add_argument('-v', action='count', default=0, help="each 'v' increases the verbosity level")



    def process(self):
        self._verbosity = Verbosity(self.getArg("v"), self.__class__.__name__)
        self._verbosity.print("begin process")

        # read server_cfg json
        self._verbosity.print("read server_cfg_json")
        with open(os.path.join(self.getGeneralArg("path-srvpkg"), "server_cfg.json"), "r") as f:
            json_string = f.read()
        self.__server_cfg_json = json.loads(json_string)

        # setup database
        self._verbosity.print("setup datatasbe")
        self.__db = Database(host=self.getGeneralArg("db-host"),
                             port=self.getGeneralArg("db-port"),
                             database=self.getGeneralArg("db-database"),
                             user=self.getGeneralArg("db-user"),
                             password=self.getGeneralArg("db-password")
                             )

        # install work
        self._verbosity.print("initialize data")
        self.__work_copy_files()

        installer = InstallerDatabase(self.__db,
                                self.__server_cfg_json,
                                self._verbosity)
        installer.process()

        # temporarily needed to fix track location table
        # this can be deleted later
        for row in self.__db.fetch("Tracks", "Track", {}, sort_by_cloumn="Track"):
            track = row['Track']
            fields = {"Track":track}
            locations = self.__db.findIds("TrackLocations", fields)
            if len(locations) == 0:
                fields.update({"Deprected": 1})
                self.__db.insertRow("TrackLocations", fields)
        for row in self.__db.fetch("Tracks", ['Id', 'Track'], {}, sort_by_cloumn="Track"):
            track_track = row['Track']
            track_id = row['Id']
            locations = self.__db.findIds("TrackLocations", {"Track": track_track})
            if len(locations) > 1:
                raise NotImplementedError("track " + track)
            elif len(locations) == 1:
                location_id = locations[0]
                self.__db.updateRow("Tracks", track_id, {'Location': location_id})

        # temporarily needed to fix Users.Name column
        # this can be deleted later
        for row in self.__db.fetch("Users", ["Id", "Login"], {}, sort_by_cloumn="Id"):
            self.__db.updateRow("Users", row['Id'], {'Name': row['Login']})


        self._verbosity.print("start scanning AC content")
        self.__work_cconfig()

        installer = InstallerCars(self.__db,
                                  self.getGeneralArg("path-srvpkg"),
                                  self.getGeneralArg("path-htdata"),
                                  self._verbosity)
        installer.process()

        installer = InstallerTracks(self.__db,
                             self.getGeneralArg("path-srvpkg"),
                             self.getGeneralArg("path-htdata"),
                             self._verbosity)
        installer.process()

        installer = None

        self._verbosity.print("post processing")
        self.__work_translations()
        if self.getArg("base-data") is True:
            self.__work_install_basics()
        self.__set_chmod()



    def dict2php(self, d):

        list_php_elements= []

        for key in d.keys():
            value = d[key]

            if type(value) == type({}):
                value = self.dict2php(value)
            elif type(value) == type([]):
                raise NotImplementedError("please implement list2php()")
            else:
                value = str(value)

            list_php_elements.append("\"" + str(key) + "\"=>" + value)

        return "array(" + (",".join(list_php_elements)) + ")"



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



    def __work_copy_files(self):
        verb = Verbosity(self._verbosity)
        verb.print("copy files")


        #########
        # htdocs

        # create dir
        path_htdocs = os.path.abspath(self.getGeneralArg("path-htdocs"))
        if not os.path.isdir(path_htdocs):
            Verbosity(Verbosity(verb)).print("mkdirs " + path_htdocs)
            self.mkdirs(path_htdocs)

        # copy dir
        Verbosity(verb).print("copy ./http/ to " + path_htdocs)
        path_htdocs_src = os.path.join(os.path.abspath(os.path.dirname(__file__)), "..", "http")
        self.copytree(path_htdocs_src, path_htdocs)

        # SteamOpenID
        path_php_steam_openid =  os.path.join(os.path.abspath(os.path.dirname(__file__)), "..", "submodules", "php-steam-openid", "src")
        path_htdocs_clases = os.path.join(path_htdocs, "classes")
        Verbosity(verb).print("copy php-steam-openid")
        self.copytree(path_php_steam_openid, path_htdocs_clases)



        #######
        # data

        Verbosity(verb).print("copy data")

        # cfg
        path_data = os.path.abspath(self.getGeneralArg("path-data"))
        path_data_acserver = os.path.join(path_data, "acserver")
        path_data_acserver_cfg = os.path.join(path_data_acserver, "cfg")
        if not os.path.isdir(path_data_acserver_cfg):
            Verbosity(Verbosity(verb)).print("mkdirs " + path_data_acserver_cfg)
            self.mkdirs(path_data_acserver_cfg)

        # prepare cfg files (to save ownership)
        slot_nr = 0
        while True:
            slot_dict = self.getIniSection("SERVER_SLOT_" + str(slot_nr))
            if slot_dict is None:
                break

            for filename in ["entry_list_%i.ini", "server_cfg_%i.ini", "welcome_%i.txt"]:
                path_file = os.path.join(path_data_acserver_cfg, filename % slot_nr)
                with open(path_file, "w") as f:
                    f.write("\n")

            slot_nr += 1

        # results
        path_data_acserver_results = os.path.join(path_data_acserver, "results")
        if not os.path.isdir(path_data_acserver_results):
            Verbosity(Verbosity(verb)).print("mkdirs " + path_data_acserver_results)
            self.mkdirs(path_data_acserver_results)

        # copy system directory
        path_srvpkg_acserver_system = os.path.join(self.getGeneralArg("path-srvpkg"), "acserver", "system")
        path_data_acserver_system = os.path.join(path_data_acserver, "system")
        self.copytree(path_srvpkg_acserver_system, path_data_acserver_system)

        # copy content directory
        path_srvpkg_acserver_content = os.path.join(self.getGeneralArg("path-srvpkg"), "acserver", "content")
        path_data_acserver_content = os.path.join(path_data_acserver, "content")
        self.copytree(path_srvpkg_acserver_content, path_data_acserver_content)

        # log dirs
        for logdir in ['logs_acserver', 'logs_cron', 'logs_http']:
            path_data_log = os.path.join(path_data, logdir)
            if not os.path.isdir(path_data_log):
                Verbosity(Verbosity(verb)).print("mkdirs " + path_data_log)
                self.mkdirs(path_data_log)

        # htcache
        path_htcache = os.path.join(path_data, "htcache")
        if not os.path.isdir(path_htcache):
            Verbosity(Verbosity(verb)).print("mkdirs " + path_htcache)
            self.mkdirs(path_htcache)

        # acserver binaries
        path_srvpkg_acserver = os.path.join(self.getGeneralArg("path-srvpkg"), "acserver")
        slot_nr = 0
        while True:
            slot_dict = self.getIniSection("SERVER_SLOT_" + str(slot_nr))
            if slot_dict is None:
                break
            path_srvpkg_acserver_bin = os.path.join(path_srvpkg_acserver, "acServer")
            path_data_acserver_binslot = os.path.join(path_data_acserver, "acServer%i" % slot_nr)
            shutil.copy(path_srvpkg_acserver_bin, path_data_acserver_binslot)
            slot_nr += 1

        # server_cfg.json
        path_srvpkg_servercfg = os.path.join(self.getGeneralArg("path-srvpkg"), "server_cfg.json")
        path_data_servercfg = os.path.join(path_data, "server_cfg.json")
        shutil.copy(path_srvpkg_servercfg, path_data_servercfg)


        #############
        # acswui.ini

        path_acswui_ini = os.path.join(path_data, "acswui.ini")
        Verbosity(verb).print("create " + path_acswui_ini)

        with open(path_acswui_ini, "w") as f:
            f.write("[GENERAL]\n")

            # database
            keys = []
            keys += ['db-host', 'db-database', 'db-port', 'db-user', 'db-password']
            for key in keys:
                value = self.getIniSection("GENERAL")[key]
                f.write(key + " = " + value + "\n")

            # paths
            keys = []
            keys += ['path-data', 'path-htdata']
            for key in keys:
                value = self.getIniSection("GENERAL")[key]
                value = os.path.abspath(value)
                f.write(key + " = " + value + "\n")


        #########
        # htdata

        # create dir
        path_htdata = os.path.abspath(self.getGeneralArg("path-htdata"))
        if not os.path.isdir(path_htdata):
            Verbosity(Verbosity(verb)).print("mkdirs " + path_htdata)
            self.mkdirs(path_htdata)

        # copy data
        path_srvpkg_htdata = os.path.join(self.getGeneralArg("path-srvpkg"), "htdata")
        Verbosity(verb).print("copy " + path_srvpkg_htdata + " to " + path_htdata)
        self.copytree(path_srvpkg_htdata, path_htdata)

        # realtime
        path_realtime = os.path.join(path_htdata, "realtime")
        if not os.path.isdir(path_realtime):
            Verbosity(Verbosity(verb)).print("mkdirs " + path_realtime)
            self.mkdirs(path_realtime)

        verb = None


    def __work_cconfig(self):
        self._verbosity.print("create Config.php")

        # encrypt root password
        http_root_password = subprocess.check_output(['php', '-r', 'echo(password_hash("%s", PASSWORD_BCRYPT));' % self.getArg('root-password')])
        http_root_password = http_root_password.decode("utf-8")

        # paths
        abspath_acswui = os.path.abspath(os.curdir)
        abspath_data = os.path.abspath(self.getGeneralArg('path-data'))
        abspath_htdocs = os.path.abspath(self.getGeneralArg('path-htdocs'))
        abspath_htdata = os.path.abspath(self.getGeneralArg('path-htdata'))
        abspath_acswui_py = os.path.abspath(os.path.join(abspath_acswui, "acswui.py"))

        # server slots
        server_slot_list = []
        slot_nr = 0
        while True:
            slot_dict = self.getIniSection("SERVER_SLOT_" + str(slot_nr))
            if slot_dict is None:
                break
            slot_php_array = [];

            for section in self.__server_cfg_json:
                for fieldset in self.__server_cfg_json[section]:
                    for tag in self.__server_cfg_json[section][fieldset]:
                        tag_dict = self.__server_cfg_json[section][fieldset][tag]
                        db_col_name = tag_dict['DB_COLUMN_NAME']

                        # check for fixed values
                        if db_col_name in slot_dict:
                            slot_php_array.append("'%s'=>\"%s\"" % (db_col_name, slot_dict[db_col_name]))

            slot_php_array = "[" + (", ".join(slot_php_array)) + "]";
            server_slot_list.append(slot_php_array)
            slot_nr += 1
        server_slots = ",\n                                 ".join(server_slot_list)

        # fixed server settings
        fixed_server_settings = []
        for section in self.__server_cfg_json:
            for fieldset in self.__server_cfg_json[section]:
                for tag in self.__server_cfg_json[section][fieldset]:
                    tag_dict = self.__server_cfg_json[section][fieldset][tag]
                    db_col_name = tag_dict['DB_COLUMN_NAME']

                    # check for fixed values
                    try:
                        val = self.getIniSection("FIXED_SERVER_SETTINGS")[db_col_name]
                    except KeyError as e:
                        val = None

                    if val is not None:
                        fixed_server_settings.append("\"%s\"=>\"%s\"" % (db_col_name, val))
        fixed_server_settings = ",".join(fixed_server_settings)

        # driver ranking
        driver_ranking = {"XP":{}, "SX":{}, "SF":{}, "DEF":{}}
        driver_ranking_from_ini = self.getIniSection("DRIVER_RANKING")
        for drfi_key in driver_ranking_from_ini.keys():
            value = driver_ranking_from_ini[drfi_key]
            group, key = drfi_key.upper().split("_")
            driver_ranking[group][key] = value

        # scan locales
        locales = []
        path_locales = os.path.join(self.getGeneralArg('path-htdocs'), "locale")
        for locale in sorted(os.listdir(path_locales)):
            locales.append("'%s'" % locale)

        # logging
        log_warning = "FALSE"
        if self.getGeneralArg('log-warning').lower() == "true":
            log_warning = "TRUE"
        log_debug = "FALSE"
        if self.getGeneralArg('log-debug').lower() == "true":
            log_debug = "TRUE"



        with open(os.path.join(abspath_htdocs, "classes" , "Core", "Config.php"), "w") as f:
            f.write("<?php\n")
            f.write("\n")
            f.write("namespace Core;\n")
            f.write("\n")
            f.write("class Config {\n")
            f.write("\n")
            f.write("    // paths\n")
            f.write("    const AbsPathData = \"%s\";\n" % abspath_data)
            f.write("    const RelPathData = \"%s\";\n" % os.path.relpath(abspath_data, abspath_htdocs))
            f.write("    const AbsPathHtdata = \"%s\";\n" % abspath_htdata)
            f.write("    const RelPathHtdata = \"%s\";\n" % os.path.relpath(abspath_htdata, abspath_htdocs))
            f.write("    const AbsPathAcswui = \"%s\";\n" % abspath_acswui)
            f.write("\n")
            f.write("    // basic constants\n")
            f.write("    const DefaultTemplate = \"%s\";\n" % self.getArg('default-template'))
            f.write("    const LogWarning = %s;\n" % log_warning)
            f.write("    const LogDebug = %s;\n" % log_debug)
            f.write("    const RootPassword = '%s';\n" % http_root_password)
            f.write("    const GuestGroup = '%s';\n" % self.getArg('guest-group'))
            f.write("    const Locales = [%s];\n" % ", ".join(locales))
            f.write("\n")
            f.write("    // database constants\n")
            f.write("    const DbHost = \"%s\";\n" % self.getGeneralArg('db-host'))
            f.write("    const DbDatabase = \"%s\";\n" % self.getGeneralArg('db-database'))
            f.write("    const DbPort = \"%s\";\n" % self.getGeneralArg('db-port'))
            f.write("    const DbUser = \"%s\";\n" % self.getGeneralArg('db-user'))
            f.write("    const DbPasswd = \"%s\";\n" % self.getGeneralArg('db-password'))
            f.write("\n")
            f.write("    // server_cfg\n")
            f.write("    const FixedServerConfig = array(%s);\n" % fixed_server_settings)
            f.write("    const ServerSlots = array(%s);\n" % server_slots)
            f.write("\n")
            f.write("    // discord webhooks\n")
            f.write("    const DWhManSrvStrtUrl = \"%s\";\n" % self.getIniSection("DISCORD_WEBHOOKS")['MANUAL_SERVER_START_URL'])
            f.write("    const DWhManSrvStrtGMntn = \"%s\";\n" % self.getIniSection("DISCORD_WEBHOOKS")['MANUAL_SERVER_START_MENTION_GROUPID'])
            f.write("    const DWhSchSrvStrtUrl = \"%s\";\n" % self.getIniSection("DISCORD_WEBHOOKS")['SCHEDULE_SERVER_START_URL'])
            f.write("    const DWhSchSrvStrtGMntn = \"%s\";\n" % self.getIniSection("DISCORD_WEBHOOKS")['SCHEDULE_SERVER_START_MENTION_GROUPID'])
            f.write("\n")
            f.write("    // misc\n")
            f.write("    const DriverRanking = %s;\n" % self.dict2php(driver_ranking))
            #f.write("    const DriverRankingCummulateScanDays = %s;\n" % self.getIniSection("DRIVER_RANKING")['CummulateScanDays'])
            f.write("}\n")



    def __work_install_basics(self):
        verb = Verbosity(self._verbosity)
        verb.print("Install base data")

        # add guest group
        try:
            guest_group = self.getArg("guest-group")
        except ArgumentException as e:
            guest_group = ""

        if len(guest_group) > 0:
            if len(self.__db.findIds("Groups", {"Name": guest_group})) == 0:
                Verbosity(verb).print("Create guest group '%s'" % guest_group)
                self.__db.insertRow("Groups", {"Name": guest_group})

        # default groups
        if len(self.__db.findIds("Groups", {"Name": "Driver"})) == 0:
            Verbosity(verb).print("Create group 'Driver")
            self.__db.insertRow("Groups", {"Name": "Driver"})
        if len(self.__db.findIds("Groups", {"Name": "Car Expert"})) == 0:
            Verbosity(verb).print("Create group 'Car Expert")
            self.__db.insertRow("Groups", {"Name": "Car Expert"})

        # default server preset 'Practice'
        with open(os.path.join(os.path.abspath(os.path.dirname(__file__)), "basic_data_default_presets.json"), "r") as f:
            json_string = f.read()
        json_obj = json.loads(json_string)
        for preset in json_obj:
            if len(self.__db.findIds("ServerPresets", {"Name": preset['Name']})) == 0:
                Verbosity(verb).print("Create server preset '%s" % preset['Name'])
                self.__db.insertRow("ServerPresets", preset)

        # default car classes
        with open(os.path.join(os.path.abspath(os.path.dirname(__file__)), "basic_data_car_classes.json"), "r") as f:
            json_string = f.read()
        json_obj = json.loads(json_string)
        for cclass in json_obj:
            if len(self.__db.findIds("CarClasses", {"Name": cclass['Name']})) == 0:
                Verbosity(verb).print("Create car class '%s" % cclass['Name'])
                cc_id = self.__db.insertRow("CarClasses", {'Name': cclass['Name']})

                for car_name in cclass['CarNames']:

                    res = self.__db.fetch("Cars", ['Id'], {'Car': car_name})
                    if len(res) == 1:
                        car_id = res[0]['Id']
                        self.__db.insertRow("CarClassesMap", {'Car': car_id, 'CarClass': cc_id, 'Ballast':0})



    def __work_translations(self):
        verb = Verbosity(self._verbosity)
        verb.print("compile translations")

        # scanning for languages
        path_locales = os.path.join(self.getGeneralArg('path-htdocs'), "locale")
        for locale in sorted(os.listdir(path_locales)):
            Verbosity(verb).print(locale)

            # scan all .po files
            path_lc_messages = os.path.join(path_locales, locale, "LC_MESSAGES")
            for po_file in os.listdir(path_lc_messages):
                if po_file[-3:] != ".po":
                    continue

                Verbosity(Verbosity(verb)).print(po_file)
                po_path = os.path.join(path_lc_messages, po_file)
                mo_path = os.path.join(path_lc_messages, po_file[:-3] + ".mo")
                cmd = ["msgfmt", "-o", mo_path, po_path]
                subprocess.run(cmd)



    def __set_chmod(self):
        verb = Verbosity(self._verbosity)
        verb.print("Setting webserver access rights")

        # paths
        abspath_acswui = os.path.abspath(os.curdir)
        abspath_data = os.path.abspath(self.getGeneralArg('path-data'))
        abspath_htdocs = os.path.abspath(self.getGeneralArg('path-htdocs'))
        abspath_htdata = os.path.abspath(self.getGeneralArg('path-htdata'))
        abspath_acswui_py = os.path.abspath(os.path.join(abspath_acswui, "acswui.py"))

        # directory paths
        for path in [abspath_data, abspath_htdocs, abspath_htdata]:
            cmd = ["chgrp", "-R", self.getGeneralArg('http-guid'), path]
            Verbosity(verb).print(" ".join(cmd))
            subprocess.run(cmd)

        # directories with write access
        paths = []
        paths.append(os.path.join(abspath_data, "logs_http"))
        paths.append(os.path.join(abspath_data, "logs_cron"))
        paths.append(os.path.join(abspath_data, "logs_acserver"))
        paths.append(os.path.join(abspath_data, "htcache"))
        paths.append(os.path.join(abspath_data, "acserver"))
        paths.append(os.path.join(abspath_data, "acserver", "cfg"))
        paths.append(os.path.join(abspath_data, "acserver", "results"))
        paths.append(os.path.join(abspath_htdata, "realtime"))
        for path in paths:
            cmd = ["chmod", "-R", "g+w", path]
            Verbosity(verb).print(" ".join(cmd))
            subprocess.run(cmd)

        # acswuy python scripts
        cmd = ["chgrp", self.getGeneralArg("http-guid"), os.path.join(abspath_acswui, "acswui.py")]
        Verbosity(verb).print(" ".join(cmd))
        subprocess.run(cmd)
        for script in os.listdir(os.path.join(abspath_acswui, "pyacswui")):
            if script[-3:] == ".py":
                cmd = ["chgrp", self.getGeneralArg("http-guid"), os.path.join(abspath_acswui, "pyacswui", script)]
                Verbosity(verb).print(" ".join(cmd))
                subprocess.run(cmd)
