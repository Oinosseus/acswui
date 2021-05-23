import argparse
import subprocess
import shutil
import os
import json
from .command import Command, ArgumentException
from .database import Database
from .verbosity import Verbosity

class CommandUpdateLocales(Command):

    def __init__(self, argparser):
        Command.__init__(self, argparser, "update-locales", "Update .po translation files for all languages", require_ini_file=False)


    def process(self):

        ref_pot = "./http/locale/ref.pot"
        os.system("find http/ -name \"*.php\" | xargs xgettext -o %s -L php" % ref_pot)

        for locale in os.listdir("./http/locale"):
            if os.path.isdir("./http/locale/" + locale):
                def_po = "./http/locale/" + locale + "/LC_MESSAGES/acswui.po"

                if os.path.isfile(def_po):
                    os.system("msgmerge -o %s %s %s" % (def_po, def_po, ref_pot))
                else:
                    os.system("cp %s %s" % (ref_pot, def_po))

        os.system("rm %s" % ref_pot)
