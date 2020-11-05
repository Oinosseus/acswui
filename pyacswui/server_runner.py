import subprocess
from .verbose_class import VerboseClass
from .udp_plugin_server import UdpPluginServer

class ServerRunner(VerboseClass):

    def __init__(self):
        VerboseClass.__init__(self)

    def run(self, db_wrapper, path_to_ac_server_dir, logfile=None):
        # setup UDP plugin listener
        self.print(1, "starting acServer")
        udp_srv = UdpPluginServer("127.0.0.1", 9603, db_wrapper)
        udp_srv.Verbosity = self.Verbosity - 1

        cmd = []
        cmd.append("./acServer")
        cmd.append(">")
        if logfile is not None:
            cmd.append(logfile)
        else:
            cmd.append("/dev/null")
        cmd.append("2>&1")
        cmd.append("&")

        # start acServer as separate process
        self.print(1, "starting acServer process")
        server_proc = subprocess.Popen(cmd, cwd=path_to_ac_server_dir)

        # run server
        self.print(1, "run server plugin")
        while True:

            # process server
            try:
                udp_srv.process()
            except BaseException as e:
                server_proc.terminate()
                server_proc.wait(timeout=5.0)
                raise e

            # quit parsing when acServer is stopped
            ret = server_proc.poll()
            if ret is not None:
                self.print(1, "acServer has finished with returncode", ret)
                break
