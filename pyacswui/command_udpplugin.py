import os
from configparser import ConfigParser
from .command import Command
from .database import Database
from .udp_plugin_server import UdpPluginServer
from .verbosity import Verbosity
import sys


class CommandUdpPlugin(Command):

    def __init__(self, argparser):
        Command.__init__(self, argparser, "udpplugin", "run the ACswui UDP plugin")
        self.add_argument('-v', action='count', default=0, help="each 'v' increases the verbosity level")


    def process(self):
        self._verbosity = Verbosity(self.getArg("v"), self.__class__.__name__)

        # setup database
        self._verbosity.print("Setup Database")
        db = Database(host=self.getGeneralArg("db-host"),
                      port=self.getGeneralArg("db-port"),
                      database=self.getGeneralArg("db-database"),
                      user=self.getGeneralArg("db-user"),
                      password=self.getGeneralArg("db-password")
                      )

        # paths
        slot_string = self.getIniSection('PLUGIN')['slot']
        path_data_acserver = os.path.join(self.getGeneralArg("path-data"), "acserver")
        path_realtime_json = os.path.join(self.getGeneralArg("path-htdata"), "realtime", slot_string + ".json")
        path_entry_list = os.path.join(path_data_acserver, "cfg", "entry_list_" + slot_string + ".ini")
        path_server_cfg = os.path.join(path_data_acserver, "cfg", "server_cfg_" + slot_string + ".ini")

        # read server config
        self._verbosity.print("Read config files")
        server_cfg = ConfigParser()
        server_cfg.read(path_server_cfg)

        # setup UDP Plugin
        self._verbosity.print("Setup UDP plugin server")
        udpp = UdpPluginServer(self.getIniSection('PLUGIN')['slot'],
                               self.getIniSection('PLUGIN')['preset'],
                               self.getIniSection('PLUGIN')['carclass'],
                               self.getIniSection('PLUGIN')['udp_acserver'],
                               self.getIniSection('PLUGIN')['udp_plugin'],
                               db,
                               path_entry_list,
                               path_data_acserver,
                               path_realtime_json,
                               verbosity=self._verbosity)
        udpp.process() # run once just to ensure that it does not crash immediately

        # run server
        self._verbosity.print("Processing ...")
        while True:
            udpp.process()
            sys.stdout.flush()