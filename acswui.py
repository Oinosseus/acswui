#!/usr/bin/python3

# acswui - Asetto Corsa Server Web User Interface
# by Thomas Weinhold
#
# This script supports the acswui installaion, maintenance and update.

import argparse
import os
import sys
import json
#from pyacswui import CommandSrvctl, , ServerPackager
from pyacswui import CommandInstallFiles, CommandInstallHttp, CommandSrvrun



# ---------------------------------
#  - Parse Commandline Arguments -
# ---------------------------------

__helpstring  = "Examples for complete safe setup:\n"
__helpstring += "./acswui -v --ini acswui.ini srvpkg\n"
__helpstring += "./acswui -v --ini acswui.ini --install-base-data install\n"

# main arguments
argparser = argparse.ArgumentParser(prog="acswui", description="Assetto Corsa Server Web User Interface", epilog=__helpstring, formatter_class=argparse.RawTextHelpFormatter)
argparser.add_argument('-i', '--ini', help="read arguments from INI file")
argparser.add_argument('-j', '--json', help="read arguments from json string")
argparser.add_argument('-v', action='count', default=0, help="each 'v' increases the verbosity level")

argparsersubs     = argparser.add_subparsers(dest='command')
#argparser_srvpkg  = argparsersubs.add_parser('srvpkg', help="server packager - preparing files for http and ac server")
#argparser_srvpkg.set_defaults(func=workaround_srvpkg)

CommandInstallFiles(argparsersubs)
CommandSrvrun(argparsersubs)
CommandInstallHttp(argparsersubs)



# ---------------------
#  - Execute Command -
# ---------------------

args = argparser.parse_args()
args.CmdObject.readArgs(args)
args.CmdObject.process()
