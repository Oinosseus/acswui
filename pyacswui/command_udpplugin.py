import os
from configparser import ConfigParser
from .command import Command
from .database import Database
from .udp_plugin_server import UdpPluginServer
from .verbosity import Verbosity

import datetime
import signal
import sys
import time


# catch external program termination
__TERMINATION_REQUESTED__ = False
def handler_sigterm(signum, frame):
    global __TERMINATION_REQUESTED__
    __TERMINATION_REQUESTED__ = True
    t = datetime.datetime.now()
    print(t.strftime("%H:%M:%S") + "  SIGTERM handler")
signal.signal(signal.SIGTERM, handler_sigterm)



class CommandUdpPlugin(Command):

    def __init__(self, argparser):
        Command.__init__(self, argparser, "udpplugin", "run the ACswui UDP plugin")
        self.add_argument('-v', action='count', default=0, help="each 'v' increases the verbosity level")


    def process(self):
        global __TERMINATION_REQUESTED__

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
        path_data_acserver = os.path.join(self.getGeneralArg("path-data"), "acserver", "slot" + slot_string)
        path_realtime_json = os.path.join(self.getGeneralArg("path-htdata"), "realtime", slot_string + ".json")
        path_entry_list = os.path.join(path_data_acserver, "cfg", "entry_list.ini")
        path_server_cfg = os.path.join(path_data_acserver, "cfg", "server_cfg.ini")

        # read server config
        self._verbosity.print("Read config files")
        server_cfg = ConfigParser()
        server_cfg.read(path_server_cfg)

        # setup UDP Plugin
        self._verbosity.print("Setup UDP plugin server")
        udpp = UdpPluginServer(self.getIniSection('PLUGIN')['slot'],
                               self.getIniSection('PLUGIN')['preset'],
                               self.getIniSection('PLUGIN')['udp_acserver'],
                               self.getIniSection('PLUGIN')['udp_plugin'],
                               self.getIniSection('PLUGIN')['udp_rp_events_tx'],
                               self.getIniSection('PLUGIN')['udp_rp_events_rx'],
                               self.getIniSection('PLUGIN')['rp_admin_password'],
                               db,
                               path_entry_list,
                               path_data_acserver,
                               path_realtime_json,
                               self.getIniSection('PLUGIN')['preserved_kick'].lower() in ["true", "1", "yes"],
                               self.getIniSection('BopCarBallast'),
                               self.getIniSection('BopCarRestrictor'),
                               self.getIniSection('BopUserBallast'),
                               self.getIniSection('BopUserRestrictor'),
                               self.getIniSection('BopTeamcarBallast'),
                               self.getIniSection('BopTeamcarRestrictor'),
                               self.getIniSection('PLUGIN')['referenced_session_schedule_id'],
                               self.getIniSection('PLUGIN')['auto-dnf-level'],
                               verbosity=self._verbosity)
        udpp.process() # run once just to ensure that it does not crash immediately

        # run server
        self._verbosity.print("Processing ...")
        while True:
            udpp.process()
            sys.stdout.flush()

            # quit at termination
            if __TERMINATION_REQUESTED__ and not udpp.ActiveSession:
                break

        self._verbosity.print("Finished")
