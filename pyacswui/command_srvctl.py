import os
import subprocess
import signal
import time
import sys
from .verbose_class import VerboseClass
from .server_runner import ServerRunner
from .db_wrapper import DbWrapper


class CommandSrvctl(VerboseClass):

    def __init__(self, argsubparser):
        VerboseClass.__init__(self)

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
        self.Verbosity = args.v

        online = self.serverOnline()
        self.print(1, "online" if online else "offline")
        exit(0 if online else 1)




    def subCmdStart(self, args, config):
        self.Verbosity = args.v
        pid = os.fork()

        # child process (server control)
        if pid == 0:

            # setup database
            database = DbWrapper(config)
            database.Verbosity = self.Verbosity - 2

            # start server runner
            srv_run = ServerRunner()
            srv_run.Verbosity = self.Verbosity - 1
            srv_run.run(database, config['path_acs'])

        # host process
        else:

            # wait and check if server is running
            time.sleep(2.0);
            if not self.serverOnline():
                raise NotImplementedError("Could not start server!")

            print("Server started, PID=" , pid)



    def subCmdStop(self, args, config):
        self.Verbosity = args.v

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
                self.print(1, "server stopped")
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
                self.print(1, "server killed")
                return

            # could not stop server
            self.print(1, "failed to stop process", pid)
            exit(1)
