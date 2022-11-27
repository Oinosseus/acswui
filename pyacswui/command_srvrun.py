import datetime
import os
import os.path
import sys
import time

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
        self.add_argument('--real-penalty', action='store_true', help="Set this flag to lunch the real penalty plugin")
        self.add_argument('--ac-server-wrapper', action='store_true', help="Set this flag to lunch AC by ac-server-wrapper")
        self.add_argument('-v', action='count', default=0, help="each 'v' increases the verbosity level")


    def process(self):
        self._verbosity = Verbosity(self.getArg("v"), self.__class__.__name__)

        slot_str = str(self.getArg("slot"))
        iso8601_str = datetime.datetime.utcnow().replace(microsecond=0).isoformat()



        # prepare real penalty UDP plugin as separate process
        self._verbosity.print("starting real penalty plugin")
        path_rp = os.path.abspath(os.path.join(self.getGeneralArg("path-data"), "real_penalty", slot_str))
        rp_cmd = []
        rp_cmd.append(os.path.join(path_rp, "ac_penalty"))



        # prepare ACswui UDP plugin as separate process
        self._verbosity.print("starting ACswui plugin")
        path_acswui = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
        path_acswui_udpp_ini = os.path.abspath(os.path.join(self.getGeneralArg("path-data"), "acswui_udp_plugin", "acswui_udp_plugin_" + slot_str + ".ini"))
        path_log_acswuiudpp = os.path.join(self.getGeneralArg("path-data"), "logs_srvrun", "slot" + slot_str + ".acswui_udp_plugin." + iso8601_str + ".log")
        acswui_udpp_cmd = []
        acswui_udpp_cmd.append(os.path.join(path_acswui, "acswui.py"))
        acswui_udpp_cmd.append("udpplugin")
        acswui_udpp_cmd.append("-" + ("v"*self._verbosity.level()))
        acswui_udpp_cmd.append(path_acswui_udpp_ini)
        try:
            stdout_log_acswuiplugin = open(path_log_acswuiudpp, "w")
        except ArgumentException as e:
            stdout_log_acswuiplugin = DEVNULL


        # prepare ac server as separate process
        path_data_acserver = os.path.join(self.getGeneralArg("path-data"), "acserver", "slot" + slot_str)
        path_data_acserver_cfg = os.path.join(path_data_acserver, "cfg")
        path_log_acserver = os.path.join(self.getGeneralArg("path-data"), "logs_srvrun", "slot" + slot_str + ".acServer." + iso8601_str + ".log")
        acserver_cmd = []
        if self.getArg("ac-server-wrapper"):
            self._verbosity.print("Start AC server wrapper")
            acserver_cmd.append("node")
            acserver_cmd.append(os.path.join(path_acswui, "submodules", "ac-server-wrapper", "ac-server-wrapper.js"))
            acserver_cmd.append("--executable=" + os.path.join(path_data_acserver, "acServer"))
            acserver_cmd.append(path_data_acserver_cfg)
        else:
            self._verbosity.print("Start AC server")
            path_entry_list = os.path.join(path_data_acserver_cfg, "entry_list.ini")
            path_server_cfg = os.path.join(path_data_acserver_cfg, "server_cfg.ini")
            acserver_cmd.append(os.path.join(path_data_acserver, "acServer"))
            acserver_cmd.append("-c")
            acserver_cmd.append(path_server_cfg)
            acserver_cmd.append("-e")
            acserver_cmd.append(path_entry_list)
            #acserver_cmd.append(">")
            #acserver_cmd.append("&")
        try:
            stdout_log_acserver = open(path_log_acserver, "w")
        except ArgumentException as e:
            stdout_log_acserver = DEVNULL


        # lunch processes
        if self.getArg("real-penalty"):
            rp_proc = Popen(rp_cmd, cwd=path_rp, stdout=DEVNULL, stderr=DEVNULL)
        acswui_udpp_proc = Popen(acswui_udpp_cmd, cwd=path_acswui, stdout=stdout_log_acswuiplugin, stderr=stdout_log_acswuiplugin)
        acserver_proc = Popen(acserver_cmd, cwd=path_data_acserver, stdout=stdout_log_acserver, stderr=stdout_log_acserver)
        with open(os.path.join(path_data_acserver, "acServer.pid"), "w") as pidfile:
            pidfile.write(str(acserver_proc.pid))


        # monitor processes
        # tear down all if any of them has finished processing
        self._verbosity.print("monitoring ...")
        while True:
            sys.stdout.flush()

            # quit parsing when RP plugin is stopped
            if self.getArg("real-penalty"):
                ret = rp_proc.poll()
                if ret is not None:
                    self._verbosity.print("Real Penalty plugin has finished with returncode", ret)
                    break


            # quit parsing when acswui plugin is stopped
            ret = acswui_udpp_proc.poll()
            if ret is not None:
                self._verbosity.print("ACswui UDP plugin has finished with returncode", ret)
                break


            # quit parsing when acServer is stopped
            ret = acserver_proc.poll()
            if ret is not None:
                self._verbosity.print("AC server has finished with returncode", ret)
                break

            time.sleep(0.1)  # wait to save CPU time


        # grant sub processes one OS process execution round after AC has finished
        time.sleep(0.5)


        # friendly ask to finish processing
        if self.getArg("real-penalty"):
            if rp_proc.poll() is None:
                self._verbosity.print("terminate real-penalty")
                rp_proc.terminate()
        if acswui_udpp_proc.poll() is None:
            self._verbosity.print("terminate acswui udp plugin")
            acswui_udpp_proc.terminate()
        if acserver_proc.poll() is None:
            self._verbosity.print("terminate ac server")
            acserver_proc.terminate()


        # allow some time to shutdown processes
        time_start = time.time()
        while True:

            # timeout for termination
            if (time.time() - time_start) > 5.0:
                break

            # skip wait time if all processes are down
            if acserver_proc.poll() is not None:  # AC server has shut down
                if acswui_udpp_proc.poll() is not None:  # ACswui UDP plugin has shut down
                    if not self.getArg("real-penalty") or rp_proc.poll() is not None:  # real penalty has shut down
                        break


        # kill processing
        if self.getArg("real-penalty"):
            if rp_proc.poll() is None:
                self._verbosity.print("kill real-penalty")
                rp_proc.kill()
        if acswui_udpp_proc.poll() is None:
            self._verbosity.print("kill acswui udp plugin")
            acswui_udpp_proc.kill()
        if acserver_proc.poll() is None:
            self._verbosity.print("kill ac server")
            acserver_proc.kill()

        self._verbosity.print("finish server run")
