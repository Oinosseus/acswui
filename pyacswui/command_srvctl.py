import os
import subprocess
import signal
import time
import sys


class CommandSrvctl(object):

    def __init__(self, argsubparser):
        argparser_srvctl  = argsubparser.add_parser('srvctl', help="server control (start/stop/status of the AC server)")
        argparser_srvctl_subcommands = argparser_srvctl.add_subparsers(dest='subcommand')

        argparser_srvctl_status = argparser_srvctl_subcommands.add_parser('status', help="print server status and exit")
        argparser_srvctl_status.set_defaults(func=self.subCmdStatus)

        argparser_srvctl_start  = argparser_srvctl_subcommands.add_parser('start', help="start server")
        argparser_srvctl_start.set_defaults(func=self.subCmdStart)

        argparser_srvctl_stop  = argparser_srvctl_subcommands.add_parser('stop', help="stop the server")
        argparser_srvctl_stop.set_defaults(func=self.subCmdStop)



    def getPid(self):
        pid = None
        cp = subprocess.run(['pgrep', 'acServer'], stdout=subprocess.PIPE)
        if len(cp.stdout):
            i = int(cp.stdout)
            if i > 0:
                pid = i
        return pid



    def serverOnline(self):
        return self.getPid() is not None



    def subCmdStatus(self, args, config):
        print("online" if self.serverOnline() else "offline")



    def subCmdStart(self, args, config):
        pid = os.fork()

        # child process (server control)
        if pid == 0:

            subprocess.Popen([sys.argv[0]], stdout=sp.PipeInput)

        # host process
        else:
            print("Server started, PID=" , pid)



    def subCmdStop(self, args, config):
        pid = self.getPid()
        if pid is not None:

            # terminate
            os.kill(pid, signal.SIGTERM)

            # wait for shutdown
            for i in range(10):
                if not self.serverOnline():
                    break
                time.sleep(0.5)

            # check if successul
            if not self.serverOnline():
                print("server stopped")
                return

            # kill
            os.kill(pid, signal.SIGKILL)

            # wait for shutdown
            for i in range(10):
                if not self.serverOnline():
                    break
                time.sleep(0.5)

            # check if successul
            if not self.serverOnline():
                print("server killed")
                return

            # could not stop server
            print("failed to stop process", pid)
