import argparse
import getpass
import json
import os
import pymysql
import re
import shutil
import subprocess
from .command import Command, ArgumentException
from .database import Database
from .helper_functions import version
from .installer_cars import InstallerCars
from .installer_database import InstallerDatabase
from .installer_tracks import InstallerTracks
from .verbosity import Verbosity


class CommandInstall(Command):

    def __init__(self, argparser):
        Command.__init__(self, argparser, "install", "Server-Installer -  install http from server package")
        self.add_argument('--base-data', action="store_true", help="install basic http data (default groups, etc.)")
        self.add_argument('-v', action='count', default=0, help="each 'v' increases the verbosity level")
        self.__root_password = "";



    def process(self):
        self._verbosity = Verbosity(self.getArg("v"), self.__class__.__name__)

        # setup database
        self.__db = Database(host=self.getGeneralArg("db-host"),
                             port=self.getGeneralArg("db-port"),
                             database=self.getGeneralArg("db-database"),
                             user=self.getGeneralArg("db-user"),
                             password=self.getGeneralArg("db-password")
                             )

        # root password
        print("Set a password for the HTTP root user (leave empty to disable root login)")
        pwd1 = getpass.getpass("assign HTTP root password:")
        if len(pwd1) > 0:
            pwd2 = getpass.getpass("confirm HTTP root password:")
            pwd2 = pwd2.strip()  # only strip second to detect crazy admins that use whitespaces at the beginning or end of their passwords
            if pwd1 != pwd2:
                print("ERROR: Passwords to not match")
                exit(1);
            self.__root_password = pwd2
        else:
            print("HTTP root login disabled")

        # install work
        self._verbosity.print("copy data")
        self.__work_copy_files()

        # country flags
        self._verbosity.print("ddownload flags from flagpedia")
        self.__work_flagpedia()

        # database
        self._verbosity.print("install datatabase tables")
        installer = InstallerDatabase(self.__db, self._verbosity)
        installer.process()

        # document begin of installation
        self.__installer_info_id = self.__db.insertRow("installer", {"version": version(), "info": "database installed"})
        self.__work_cconfig()

        # cars
        self._verbosity.print("scanning available cars")
        installer = InstallerCars(self.__db,
                                  self.getGeneralArg("path-srvpkg"),
                                  self.getGeneralArg("path-htdata"),
                                  self._verbosity)
        installer.process()

        # tracks
        self._verbosity.print("scanning available tracks")
        installer = InstallerTracks(self.__db,
                             self.getGeneralArg("path-srvpkg"),
                             self.getGeneralArg("path-htdata"),
                             self._verbosity)
        installer.process()

        installer = None  # I can't remember why I put this is here. Maybe to ensure destructor is called?

        # ?? stuff
        self.__work_database_data()

        # translations
        self._verbosity.print("installing translations")
        self.__work_translations()

        # base data
        if self.getArg("base-data") is True:
            self._verbosity.print("installing base data")
            self.__work_install_basics()

        # chmod
        self._verbosity.print("chgrp for http server user-group")
        self.__set_chmod()

        # document begin of installation
        self.__db.updateRow("installer",
                            self.__installer_info_id,
                            {"info": "installation finished of version " + version(True)})



    def dict2php(self, d):

        list_php_elements= []

        for key in d.keys():
            value = d[key]

            if type(value) == type({}):
                value = self.dict2php(value)
            elif type(value) == type([]):
                raise NotImplementedError("please implement list2php()")
            else:
                value = "\"" + str(value) + "\""

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

        # acserver
        path_data = os.path.abspath(self.getGeneralArg("path-data"))
        path_data_acserver = os.path.join(path_data, "acserver")
        for slot_nr in range(int(self.getGeneralArg('server-slot-amount'))):
            slot_nr += 1
            Verbosity(Verbosity(verb)).print("slot %i" % slot_nr)

            # slot
            path_data_acserver_slot = os.path.join(path_data_acserver, "slot%i" % slot_nr)
            if not os.path.isdir(path_data_acserver_slot):
                self.mkdirs(path_data_acserver_slot)

            # cfg
            path_data_acserver_slot_cfg = os.path.join(path_data_acserver_slot, "cfg")
            if not os.path.isdir(path_data_acserver_slot_cfg):
                self.mkdirs(path_data_acserver_slot_cfg)

            # cm_content
            path_data_acserver_slot_cfg_cm_content = os.path.join(path_data_acserver_slot, "cfg", "cm_content")
            if not os.path.isdir(path_data_acserver_slot_cfg_cm_content):
                self.mkdirs(path_data_acserver_slot_cfg_cm_content)

            # prepare cfg files (to save ownership)
            for filename in ["entry_list.ini", "server_cfg.ini", "server_cfg.ini.tmp", "welcome.txt", os.path.join("cm_content", "content.json")]:
                path_file = os.path.join(path_data_acserver_slot_cfg, filename)
                with open(path_file, "w") as f:
                    f.write("\n")

            # results
            path_data_acserver_slot_results = os.path.join(path_data_acserver_slot, "results")
            if not os.path.isdir(path_data_acserver_slot_results):
                self.mkdirs(path_data_acserver_slot_results)

            # copy system directory
            path_srvpkg_acserver_system = os.path.join(self.getGeneralArg("path-srvpkg"), "acserver", "system")
            path_data_acserver_slot_system = os.path.join(path_data_acserver_slot, "system")
            self.copytree(path_srvpkg_acserver_system, path_data_acserver_slot_system)

            # copy content directory
            path_srvpkg_acserver_content = os.path.join(self.getGeneralArg("path-srvpkg"), "acserver", "content")
            path_data_acserver_slot_content = os.path.join(path_data_acserver_slot, "content")
            self.copytree(path_srvpkg_acserver_content, path_data_acserver_slot_content)

            # acserver binaries
            path_srvpkg_acserver = os.path.join(self.getGeneralArg("path-srvpkg"), "acserver")
            path_srvpkg_acserver_bin = os.path.join(path_srvpkg_acserver, "acServer")
            #path_data_acserver_slot_binary = os.path.join(path_data_acserver_slot, "acServer%i" % slot_nr)
            path_data_acserver_slot_binary = os.path.join(path_data_acserver_slot, "acServer")
            shutil.copy(path_srvpkg_acserver_bin, path_data_acserver_slot_binary)

        # acswui_udp_plugin
        path_data_acswui_udpp = os.path.join(path_data, "acswui_udp_plugin")
        if not os.path.isdir(path_data_acswui_udpp):
            Verbosity(Verbosity(verb)).print("mkdirs " + path_data_acswui_udpp)
            self.mkdirs(path_data_acswui_udpp)

        # log dirs
        for logdir in ['logs_srvrun', 'logs_cron', 'logs_http']:
            path_data_log = os.path.join(path_data, logdir)
            if not os.path.isdir(path_data_log):
                Verbosity(Verbosity(verb)).print("mkdirs " + path_data_log)
                self.mkdirs(path_data_log)

        # htcache
        path_htcache = os.path.join(path_data, "htcache")
        if not os.path.isdir(path_htcache):
            Verbosity(Verbosity(verb)).print("mkdirs " + path_htcache)
            self.mkdirs(path_htcache)
        shutil.copy(os.path.join(os.path.abspath(os.path.dirname(__file__)), "..", "docs", "slot_ports_optimized.svg"), os.path.join(path_htcache, "slot_ports_optimized.svg"))

        # htcache/cronjobs
        path_htcache_cronjobs = os.path.join(path_data, "htcache", "cronjobs")
        if not os.path.isdir(path_htcache_cronjobs):
            Verbosity(Verbosity(verb)).print("mkdirs " + path_htcache_cronjobs)
            self.mkdirs(path_htcache_cronjobs)

        # htcache/real_weather_cache
        path_htcache_rwc = os.path.join(path_data, "htcache", "real_weather_cache")
        if not os.path.isdir(path_htcache_rwc):
            Verbosity(Verbosity(verb)).print("mkdirs " + path_htcache_rwc)
            self.mkdirs(path_htcache_rwc)

        # htcache/owned_skins
        path_htcache_owned_skins = os.path.join(path_data, "htcache", "owned_skins")
        if not os.path.isdir(path_htcache_owned_skins):
            Verbosity(Verbosity(verb)).print("mkdirs " + path_htcache_owned_skins)
            self.mkdirs(path_htcache_owned_skins)

        # htcache/owned_skin_registration_temp
        path_htcache_owned_skin_registration_temp = os.path.join(path_data, "htcache", "owned_skin_registration_temp")
        if not os.path.isdir(path_htcache_owned_skin_registration_temp):
            Verbosity(Verbosity(verb)).print("mkdirs " + path_htcache_owned_skin_registration_temp)
            self.mkdirs(path_htcache_owned_skin_registration_temp)

        # common config directory
        path_data_acswui_config = os.path.join(path_data, "acswui_config")
        self.mkdirs(path_data_acswui_config)

        # real penalty
        path_srvpkg_rp = os.path.join(self.getGeneralArg("path-srvpkg"), "RealPenalty_ServerPlugin")
        if not os.path.isdir(path_srvpkg_rp):
            Verbosity(verb).print("skip real penalty")
        else:
            Verbosity(verb).print("install penalty")
            for slot_nr in range(int(self.getGeneralArg('server-slot-amount'))):
                path_rp_dst = os.path.join(path_data, "real_penalty", str(slot_nr + 1))
                for subfolder in ["files", "tracks"]:
                    self.copytree(os.path.join(path_srvpkg_rp, subfolder), os.path.join(path_rp_dst, subfolder))
                for subfile in ["ac_penalty", "licensing_token", "public.pem"]:
                    shutil.copy(os.path.join(path_srvpkg_rp, subfile), os.path.join(path_rp_dst, subfile))
                for createfile in ["settings.ini", "penalty_settings.ini", "ac_settings.ini"]:
                    open(os.path.join(path_rp_dst, createfile), "w").close()



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

        # flagpedia
        path_flagpedia = os.path.join(path_htdata, "flagpedia")
        if not os.path.isdir(path_flagpedia):
            Verbosity(Verbosity(verb)).print("mkdirs " + path_flagpedia)
            self.mkdirs(path_flagpedia)

        # owned_carskin_packages
        path_owned_carskin_packages = os.path.join(path_htdata, "owned_carskin_packages")
        if not os.path.isdir(path_owned_carskin_packages):
            Verbosity(Verbosity(verb)).print("mkdirs " + path_owned_carskin_packages)
            self.mkdirs(path_owned_carskin_packages)

        verb = None


    def __work_cconfig(self):
        self._verbosity.print("create Config.php")

        # encrypt root password
        http_root_password = subprocess.check_output(['php', '-r', 'echo(password_hash("%s", PASSWORD_BCRYPT));' % self.__root_password])
        http_root_password = http_root_password.decode("utf-8")

        # paths
        abspath_acswui = os.path.abspath(os.curdir)
        abspath_data = os.path.abspath(self.getGeneralArg('path-data'))
        abspath_htdocs = os.path.abspath(self.getGeneralArg('path-htdocs'))
        abspath_htdata = os.path.abspath(self.getGeneralArg('path-htdata'))
        abspath_acswui_py = os.path.abspath(os.path.join(abspath_acswui, "acswui.py"))

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

        # country codes
        country_codes_json_string = subprocess.check_output(["curl", "--silent", "https://flagcdn.com/en/codes.json"]).decode("utf-8")
        country_codes_json = json.loads(country_codes_json_string)

        # driver ranking groups
        drvrnkgrps = int(self.getGeneralArg('driver-ranking-groups'))
        if drvrnkgrps < 1 or drvrnkgrps > 100:
            raise ValueError("The INI file key 'driver-ranking-groups' must be in the range [1, 100]!")



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
            f.write("    const DefaultTemplate = \"acswui\";\n")
            f.write("    const LogWarning = %s;\n" % log_warning)
            f.write("    const LogDebug = %s;\n" % log_debug)
            f.write("    const RootPassword = '%s';\n" % http_root_password)
            f.write("    const GuestGroup = '%s';\n" % self.getGeneralArg('user-group-guest'))
            f.write("    const DriverGroup = '%s';\n" % self.getGeneralArg('user-group-driver'))
            f.write("    const Locales = [%s];\n" % ", ".join(locales))
            f.write("    const Countries = %s;\n" % self.dict2php(country_codes_json))
            f.write("\n")
            f.write("    // database constants\n")
            f.write("    const DbHost = \"%s\";\n" % self.getGeneralArg('db-host'))
            f.write("    const DbDatabase = \"%s\";\n" % self.getGeneralArg('db-database'))
            f.write("    const DbPort = \"%s\";\n" % self.getGeneralArg('db-port'))
            f.write("    const DbUser = \"%s\";\n" % self.getGeneralArg('db-user'))
            f.write("    const DbPasswd = \"%s\";\n" % self.getGeneralArg('db-password'))
            f.write("\n")
            f.write("    // server_cfg\n")
            f.write("    const ServerSlotAmount = %d;\n" % int(self.getGeneralArg('server-slot-amount')))
            f.write("\n")
            f.write("    // misc\n")
            f.write("    const DriverRankingGroups = %s;\n" % drvrnkgrps)
            f.write("    const ACswuiVersion = \"%s\";\n" % version())
            f.write("    const ACswuiVersionLong = \"%s\";\n" % version(True))
            f.write("}\n")



    def __work_install_basics(self):
        verb = Verbosity(self._verbosity)
        verb.print("Install base data")

        # add guest group
        try:
            guest_group = self.getGeneralArg('user-group-guest')
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



    def __work_flagpedia(self):
        verb = Verbosity(self._verbosity)
        verb.print("downloads flags from flagpedia")

        path_flagpedia = os.path.join(os.path.abspath(self.getGeneralArg("path-htdata")), "flagpedia")
        country_codes_json_string = subprocess.check_output(["curl", "--silent", "https://flagcdn.com/en/codes.json"]).decode("utf-8")
        country_codes_json = json.loads(country_codes_json_string)

        for country_key in country_codes_json.keys():
            Verbosity(verb).print(country_key + ".svg " + country_codes_json[country_key])
            svg_content = subprocess.check_output(["curl", "--silent", "https://flagcdn.com/%s.svg" % country_key]).decode("utf-8")
            with open(os.path.join(path_flagpedia, country_key + ".svg"), "w") as svg_file:
                svg_file.write(svg_content)



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
        paths.append(os.path.join(abspath_data, "logs_srvrun"))
        paths.append(os.path.join(abspath_data, "htcache"))
        paths.append(os.path.join(abspath_data, "acserver"))
        for slot_nr in range(int(self.getGeneralArg('server-slot-amount'))):
            slot_nr += 1
            paths.append(os.path.join(abspath_data, "acserver", "slot%i" % slot_nr, "cfg"))
            paths.append(os.path.join(abspath_data, "acserver", "slot%i" % slot_nr, "results"))
        paths.append(os.path.join(abspath_data, "acswui_config"))
        paths.append(os.path.join(abspath_data, "real_penalty"))
        paths.append(os.path.join(abspath_data, "acswui_udp_plugin"))
        paths.append(os.path.join(abspath_htdata, "realtime"))
        paths.append(os.path.join(abspath_htdata, "content"))
        paths.append(os.path.join(abspath_htdata, "htmlimg", "car_skins"))
        paths.append(os.path.join(abspath_htdata, "owned_carskin_packages"))
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

        # make preview images writeable for user
        cmd = ["chmod", "-R", "u+w", abspath_htdata]
        Verbosity(verb).print(" ".join(cmd))
        subprocess.run(cmd)

        # make real penalty executable
        path_srvpkg_rp = os.path.join(self.getGeneralArg("path-srvpkg"), "RealPenalty_ServerPlugin")
        if os.path.isdir(path_srvpkg_rp):
            for slot_nr in range(int(self.getGeneralArg('server-slot-amount'))):
                path_rp = os.path.join(abspath_data, "real_penalty", str(slot_nr + 1), "ac_penalty")
                cmd = ["chmod", "ug+x", path_rp]
                Verbosity(verb).print(" ".join(cmd))
                subprocess.run(cmd)



    def __work_database_data(self):
        verb = Verbosity(self._verbosity)
        verb.print("Write Database Data")

        # default groups
        groups = []
        groups.append(self.getGeneralArg('user-group-driver'))
        groups.append(self.getGeneralArg('user-group-guest'))
        for g in groups:
            if len(self.__db.fetch("Groups", ['Id'], {'Name':g})) == 0:
                self.__db.insertRow("Groups", {"Name":g})

        # ---------------------------------------------------------------------
        #                             Permissions
        # ---------------------------------------------------------------------

        # list available permissions
        permissions = []
        permissions.append("ServerContent_CarClasses_View")
        permissions.append("ServerContent_CarClasses_Edit")
        permissions.append("ServerContent_Cars_View")
        permissions.append("ServerContent_Cars_Edit")
        permissions.append("ServerContent_Teams_View")
        permissions.append("ServerContent_Teams_Found")
        permissions.append("ServerContent_Tracks_View")
        permissions.append("ServerContent_Tracks_Edit")
        permissions.append("ServerContent_View")
        permissions.append("User_DriverRanking_View")
        permissions.append("User_View")
        permissions.append("User_Settings")
        permissions.append("User_Groups_View")
        permissions.append("User_Groups_Edit")
        permissions.append("User_Management_View")
        permissions.append("User_Management_Edit")
        permissions.append("User_Polls_View")
        permissions.append("User_Polls_Vote")
        permissions.append("User_Polls_Edit")
        permissions.append("Settings_View")
        permissions.append("Settings_ACswui_View")
        permissions.append("Settings_ACswui_Edit")
        permissions.append("Settings_DefaultSchedule_View")
        permissions.append("Settings_DefaultSchedule_Edit")
        permissions.append("Settings_Presets_View")
        permissions.append("Settings_Presets_Edit")
        permissions.append("Settings_Slots_View")
        permissions.append("Settings_Slots_Edit")
        permissions.append("Settings_Weather_View")
        permissions.append("Settings_Weather_Edit")
        permissions.append("Sessions_View")
        for i in range(1, int(self.getGeneralArg('server-slot-amount')) + 1):
            permissions.append("Sessions_Control_Slot%i" % i)
        permissions.append("Sessions_Loops_View")
        permissions.append("Sessions_Loops_Edit")
        permissions.append("Sessions_Schedule_View")
        permissions.append("Sessions_Schedule_Edit")
        permissions.append("Json")
        permissions.append("Cronjobs_View")
        permissions.append("Cronjobs_Force")
        permissions.append("Skins_Create")
        permissions.append("Notify_Maladministration")

        # delete obsolete permissions
        for column in self.__db.columns("Groups"):
            if column not in ["Id", "Name"] + permissions:
                self.__db.deleteColumn("Groups", column)

        # create missing columns
        for p in permissions:
            self.__db.appendColumnTinyInt("Groups", p)
