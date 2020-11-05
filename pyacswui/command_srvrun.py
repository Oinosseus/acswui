import os
import signal
import time
import os.path
from subprocess import Popen, DEVNULL
from configparser import ConfigParser
from .command import Command, ArgumentException
from .database import Database
from .udp_plugin_server import UdpPluginServer
from .verbosity import Verbosity

class CommandSrvrun(Command):

    def __init__(self, argparser):
        Command.__init__(self, argparser, "srvrun", "run the ac server")

        # logging
        #self.add_argument('-l', '--log', help="if set, the acServer ouput will be logged to")

        # database
        self.add_argument('--db-host', help="Database host (not needed when global config is given)")
        self.add_argument('--db-port', help="Database port (not needed when global config is given)")
        self.add_argument('--db-database', help="Database name (not needed when global config is given)")
        self.add_argument('--db-user', help="Database username (not needed when global config is given)")
        self.add_argument('--db-password', help="Database password (not needed when global config is given)")

        # server config
        self.add_argument('--path-server-cfg', help="Path to server_cfg.ini file")
        self.add_argument('--path-entry-list', help="Path to entry_list.ini file")
        self.add_argument('--path-acs', help="Path to AC server directory")
        self.add_argument('--name-acs', default="acServer", help="Name of AC server executable")
        self.add_argument('--acs-log', help="Path to write AC server output to (optional)")


    def process(self):

        # setup database
        self.Verbosity.print("Setup Database")
        db = Database(host=self.getArg("db_host"),
                      port=self.getArg("db_port"),
                      database=self.getArg("db_database"),
                      user=self.getArg("db_user"),
                      password=self.getArg("db_password"),
                      verbosity=Verbosity(self.Verbosity)
                      )

        # read server config
        self.Verbosity.print("Read config files")
        server_cfg = ConfigParser()
        server_cfg.read(self.getArg("path_server_cfg"))
        entry_list = ConfigParser()
        entry_list.read(self.getArg("path_entry_list"))

        # setup UDP Plugin
        self.Verbosity.print("Setup UDP plugin server")
        udpp_cfg = server_cfg['SERVER']['UDP_PLUGIN_ADDRESS'].split(":")
        udpp_addr = udpp_cfg[0]
        udpp_port = udpp_cfg[1]
        udpp = UdpPluginServer(udpp_addr, udpp_port, db, verbosity=self.Verbosity)
        udpp.process() # run once just to check that it works

        # start ac server as separate process
        self.Verbosity.print("Start AC server")
        acs_cmd = []
        acs_cmd.append(os.path.join(self.getArg("path_acs"), self.getArg("name_acs")))
        acs_cmd.append(">")
        try:
            stdout = open(self.getArg("acs_log"), "w")
        except ArgumentException as e:
            stdout = DEVNULL
        acs_cmd.append("&")
        acs_proc = Popen(acs_cmd, cwd=self.getArg("path_acs"), stdout=stdout, stderr=stdout)

        # run server
        self.Verbosity.print("Processing ...")
        while True:

            # process server
            try:
                udpp.process()
            except BaseException as e:
                acs_proc.terminate()
                acs_proc.wait(timeout=5.0)
                raise e

            # quit parsing when acServer is stopped
            ret = acs_proc.poll()
            if ret is not None:
                self.Verbosity.print("AC server has finished with returncode", ret)
                break
