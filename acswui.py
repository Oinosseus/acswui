#!/usr/bin/python3

# acswui - Asetto Corsa Server Web User Interface
# by Thomas Weinhold
#
# This script supports the acswui installaion, maintenance and update.

import argparse
import os
import sys
import json
from pyacswui import CommandPackage, CommandInstall, CommandSrvrun, CommandDbCleanup, CommandUpdateLocales, CommandUdpPlugin, CommandBackup, CommandRestore



# ---------------------------------
#  - Parse Commandline Arguments -
# ---------------------------------

__helpstring  = "Examples for complete safe setup:\n"
__helpstring += "./acswui local.ini -vv package\n"
__helpstring += "./acswui remote.ini -vv install --install-base-data --root-pwd \"mypassword\"\n"

# main arguments
argparser = argparse.ArgumentParser(prog="acswui", description="Assetto Corsa Server Web User Interface", epilog=__helpstring, formatter_class=argparse.RawTextHelpFormatter)
argparsersubs = argparser.add_subparsers(dest='command')

CommandPackage(argparsersubs)
CommandInstall(argparsersubs)
CommandSrvrun(argparsersubs)
CommandDbCleanup(argparsersubs)
CommandUpdateLocales(argparsersubs)
CommandUdpPlugin(argparsersubs)
CommandBackup(argparsersubs)
CommandRestore(argparsersubs)


# ---------------------
#  - Execute Command -
# ---------------------

args = argparser.parse_args()
args.CmdObject.parseArgs(args)
args.CmdObject.process()
