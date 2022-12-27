from .command import Command, ArgumentException
import datetime
import os
import shutil
import subprocess
from .verbosity import Verbosity


class CommandBackup(Command):

    def __init__(self, argparser):
        Command.__init__(self, argparser, "backup", "Create a backup of the current acswui system into the path-srvpkg/backup/ directory")
        self.add_argument('--overwrite', action="store_true", help="If path-srvpkg/backup/ is not empty it will be cleaned up before creating the backup")
        self.add_argument('-z', action="store_true", help="This will call 7z to zi the backup, it will be called 'acswui_backup_yyyy-mm-ddTHH:MM:SS.7z'")
        self._v = Verbosity(0, "CMD Backup")

    def process(self):

        # ensure that target directory is ready
        self._v.print("checking path-srvpkg directory")
        backup_path = os.path.join(self.getGeneralArg("path-srvpkg"), "backup")
        if os.path.isdir(backup_path):
            if len(os.listdir(backup_path)) > 0:
                if self.getArg("overwrite"):
                    self.cleanDir(backup_path)
                else:
                    raise NotImplementedError("Backup target directory is not empty: " + backup_path)
        else:
            self.mkdirs(backup_path)

        # additional directories
        backup_path_htdata = os.path.join(backup_path, "htdata")
        self.mkdirs(backup_path_htdata)
        backup_path_data = os.path.join(backup_path, "data")
        self.mkdirs(backup_path_data)

        # dump database
        self._v.print("dump database")
        backup_path_sql = os.path.join(backup_path, "database.sql")
        fd = open(backup_path_sql, "w")
        cmd = ["mysqldump"]
        cmd.append("--password=" + self.getGeneralArg("db-password"))
        cmd.append("--port=" + self.getGeneralArg("db-port"))
        cmd.append("--user=" + self.getGeneralArg("db-user"))
        cmd.append("--host=" + self.getGeneralArg("db-host"))
        cmd.append("--protocol=TCP")
        cmd.append(self.getGeneralArg("db-database"))
        subprocess.run(cmd, stdout=fd, check=True)
        fd.close()

        # backup team logos
        self._v.print("save team logos")
        src = os.path.join(self.getGeneralArg("path-htdata"), "htmlimg", "team_logos")
        dst = os.path.join(backup_path_htdata, "htmlimg", "team_logos")
        self.copytree(src, dst)

        # backup rserlogos
        self._v.print("save race series logos")
        src = os.path.join(self.getGeneralArg("path-htdata"), "htmlimg", "rser_logos")
        dst = os.path.join(backup_path_htdata, "htmlimg", "rser_logos")
        self.copytree(src, dst)

        # acswui config
        self._v.print("save acswui config")
        src = os.path.join(self.getGeneralArg("path-data"), "acswui_config")
        dst = os.path.join(backup_path_data, "acswui_config")
        self.copytree(src, dst)

        # owned car skins
        self._v.print("save owned car skins")
        src = os.path.join(self.getGeneralArg("path-data"), "htcache", "owned_skins")
        dst = os.path.join(backup_path_data, "htcache", "owned_skins")
        self.copytree(src, dst)

        # zip
        if self.getArg("z"):
            self._v.print("pack backup (zip)")
            dst = os.path.join(backup_path, "..", "acswui_backup_" + datetime.datetime.utcnow().replace(microsecond=0).isoformat() + ".7z")
            subprocess.run(["7z", "a", dst, backup_path], check=True)
            shutil.rmtree(backup_path)
