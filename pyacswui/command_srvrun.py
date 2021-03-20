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
        self.add_argument('--slot', help="Server slot number")


    def process(self):

        # setup database
        self.Verbosity.print("Setup Database")
        db = Database(host=self.getGeneralArg("db-host"),
                      port=self.getGeneralArg("db-port"),
                      database=self.getGeneralArg("db-database"),
                      user=self.getGeneralArg("db-user"),
                      password=self.getGeneralArg("db-password"),
                      verbosity=Verbosity(Verbosity(self.Verbosity))
                      )

        # paths
        path_data_acserver = os.path.join(self.getGeneralArg("path-data"), "acserver")
        path_realtime_json = os.path.join(self.getGeneralArg("path-htdata"), "realtime", self.getArg("slot") + ".json")
        path_entry_list = os.path.join(path_data_acserver, "cfg", "entry_list_" + self.getArg("slot") + ".ini")
        path_server_cfg = os.path.join(path_data_acserver, "cfg", "server_cfg_" + self.getArg("slot") + ".ini")
        path_log_acserver = os.path.join(self.getGeneralArg("path-data"), "logs_acserver", "srvrun_" + self.getArg("slot") + ".log")

        # read server config
        self.Verbosity.print("Read config files")
        server_cfg = ConfigParser()
        server_cfg.read(path_server_cfg)

        # setup UDP Plugin
        self.Verbosity.print("Setup UDP plugin server")
        server_slot_id = server_cfg['ACSWUI']['SERVER_SLOT']
        udpp_port_server = server_cfg['SERVER']['UDP_PLUGIN_LOCAL_PORT']
        udpp_cfg = server_cfg['SERVER']['UDP_PLUGIN_ADDRESS'].split(":")
        udpp_addr = udpp_cfg[0]
        udpp_port_plugin = udpp_cfg[1]
        udpp = UdpPluginServer(server_slot_id,
                               udpp_port_server, udpp_port_plugin, db,
                               path_entry_list,
                               path_data_acserver,
                               path_realtime_json,
                               verbosity=self.Verbosity)
        udpp.process() # run once just to ensure that it does not crash immediately

        # start ac server as separate process
        self.Verbosity.print("Start AC server")
        acs_cmd = []
        acs_cmd.append(os.path.join(path_data_acserver, "acServer" + self.getArg("slot")))
        acs_cmd.append("-c")
        acs_cmd.append(path_server_cfg)
        acs_cmd.append("-e")
        acs_cmd.append(path_entry_list)
        acs_cmd.append(">")
        try:
            stdout = open(path_log_acserver, "w")
        except ArgumentException as e:
            stdout = DEVNULL
        acs_cmd.append("&")
        acs_proc = Popen(acs_cmd, cwd=path_data_acserver, stdout=stdout, stderr=stdout)
        #acs_proc = Popen(acs_cmd, stdout=stdout, stderr=stdout)

        # export PID
        with open(os.path.join(path_data_acserver, "acServer" + self.getArg("slot") + ".pid"), "w") as pidfile:
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
