import os
import signal
import time
import os.path
import sys
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
        self.add_argument('--path-acs-target', help="Path to AC server directory")
        self.add_argument('--path-realtime-json', help="Optional file that gets updated with realtime information")
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
                      verbosity=Verbosity(Verbosity(self.Verbosity))
                      )

        # read server config
        self.Verbosity.print("Read config files")
        server_cfg = ConfigParser()
        server_cfg.read(self.getArg("path_server_cfg"))

        # setup UDP Plugin
        self.Verbosity.print("Setup UDP plugin server")
        udpp_port_server = server_cfg['SERVER']['UDP_PLUGIN_LOCAL_PORT']
        udpp_cfg = server_cfg['SERVER']['UDP_PLUGIN_ADDRESS'].split(":")
        udpp_addr = udpp_cfg[0]
        udpp_port_plugin = udpp_cfg[1]
        try:
            realtime_json_path = self.getArg("path-realtime-json")
        except ArgumentException:
            realtime_json_path = None
        udpp = UdpPluginServer(udpp_port_server, udpp_port_plugin, db,
                               self.getArg("path-entry-list"),
                               self.getArg("path-acs-target"),
                               realtime_json_path,
                               verbosity=self.Verbosity)
        udpp.process() # run once just to ensure that it does not crash immediately

        # start ac server as separate process
        self.Verbosity.print("Start AC server")
        acs_cmd = []
        acs_cmd.append(os.path.join(self.getArg("path_acs_target"), self.getArg("name_acs")))
        acs_cmd.append("-c")
        acs_cmd.append(self.getArg("path-server-cfg"))
        acs_cmd.append("-e")
        acs_cmd.append(self.getArg("path-entry-list"))
        acs_cmd.append(">")
        try:
            stdout = open(self.getArg("acs_log"), "w")
        except ArgumentException as e:
            stdout = DEVNULL
        acs_cmd.append("&")
        acs_proc = Popen(acs_cmd, cwd=self.getArg("path_acs_target"), stdout=stdout, stderr=stdout)
        #acs_proc = Popen(acs_cmd, stdout=stdout, stderr=stdout)

        # export PID
        with open(os.path.join(self.getArg("path_acs_target"), self.getArg("name_acs") + ".pid"), "w") as pidfile:
            pidfile.write(str(acs_proc.pid))

        # run server
        self.Verbosity.print("Processing ...")
        while True:
            sys.stdout.flush()

            # process server
            try:
                udpp.process()
            except BaseException as e:
                self.Verbosity.print("Received Exception:", str(e))
                acs_proc.terminate()
                acs_proc.wait(timeout=5.0)
                raise e

            # quit parsing when acServer is stopped
            ret = acs_proc.poll()
            if ret is not None:
                self.Verbosity.print("AC server has finished with returncode", ret)
                break
