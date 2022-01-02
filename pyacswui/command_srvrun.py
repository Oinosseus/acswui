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
        self.add_argument('--real-penalty', action='store_true', help="Set this flag to lunch the real penalty plugin")
        self.add_argument('-v', action='count', default=0, help="each 'v' increases the verbosity level")


    def process(self):
        self._verbosity = Verbosity(self.getArg("v"), self.__class__.__name__)

        slot_str = str(self.getArg("slot"))



        # prepare real penalty UDP plugin as separate process
        self._verbosity.print("starting real penalty plugin")
        path_rp = os.path.abspath(os.path.join(self.getGeneralArg("path-data"), "real_penalty", slot_str))
        rp_cmd = []
        rp_cmd.append(os.path.join(path_rp, "ac_penalty"))



        # prepare ACswui UDP plugin as separate process
        self._verbosity.print("starting ACswui plugin")
        path_acswui = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
        path_acswui_udpp_ini = os.path.abspath(os.path.join(self.getGeneralArg("path-data"), "acswui_udp_plugin", "acswui_udp_plugin_" + slot_str + ".ini"))
        path_log_acswuiudpp = os.path.join(self.getGeneralArg("path-data"), "logs_srvrun", "slot_" + slot_str + ".acswui_udp_plugin.log")
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
        self._verbosity.print("Start AC server")
        path_data_acserver = os.path.join(self.getGeneralArg("path-data"), "acserver")
        path_entry_list = os.path.join(path_data_acserver, "cfg", "entry_list_" + slot_str + ".ini")
        path_server_cfg = os.path.join(path_data_acserver, "cfg", "server_cfg_" + slot_str + ".ini")
        path_log_acserver = os.path.join(self.getGeneralArg("path-data"), "logs_srvrun", "slot_" + slot_str + ".acServer.log")
        acserver_cmd = []
        acserver_cmd.append(os.path.join(path_data_acserver, "acServer" + slot_str))
        acserver_cmd.append("-c")
        acserver_cmd.append(path_server_cfg)
        acserver_cmd.append("-e")
        acserver_cmd.append(path_entry_list)
        acserver_cmd.append(">")
        try:
            stdout_log_acserver = open(path_log_acserver, "w")
        except ArgumentException as e:
            stdout_log_acserver = DEVNULL
        acserver_cmd.append("&")


        # lunch processes
        if self.getArg("real-penalty"):
            rp_proc = Popen(rp_cmd, cwd=path_rp, stdout=DEVNULL, stderr=DEVNULL)
        acswui_udpp_proc = Popen(acswui_udpp_cmd, cwd=path_acswui, stdout=stdout_log_acswuiplugin, stderr=stdout_log_acswuiplugin)
        acserver_proc = Popen(acserver_cmd, cwd=path_data_acserver, stdout=stdout_log_acserver, stderr=stdout_log_acserver)
        with open(os.path.join(path_data_acserver, "acServer" + slot_str + ".pid"), "w") as pidfile:
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


        # friendly ask to finish processing
        if self.getArg("real-penalty"):
            if rp_proc.poll() is None:
                rp_proc.terminate()
        if acswui_udpp_proc.poll() is None:
            acswui_udpp_proc.terminate()
        if acserver_proc.poll() is None:
            acserver_proc.terminate()


        # allow some time to shutdown processes
        time_start = time.time()
        while True:
            if (time.time() - time_start) > 5:
                break;
            if rp_proc.poll() is None and acswui_udpp_proc.poll() is None:
                if self.getArg("real-penalty"):
                    if acserver_proc.poll() is None:
                        break
                else:
                    break


        # kill processing
        if self.getArg("real-penalty"):
            if rp_proc.poll() is None:
                rp_proc.kill()
        if acswui_udpp_proc.poll() is None:
            acswui_udpp_proc.kill()
        if acserver_proc.poll() is None:
            acserver_proc.kill()

