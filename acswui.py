#!/usr/bin/python3

# acswui - Asetto Corsa Server Web User Interface
# by Thomas Weinhold
#
# This script supports the acswui installaion, maintenance and update.

import argparse
import os
import sys
from pyacswui import ServerPackager, Installer



# ---------------------------------
#  - Parse Commandline Arguments -
# ---------------------------------

__helpstring  = "Examples for complete safe setup:\n"
__helpstring += "./acswui -v --ini acswui.ini srvpkg\n"
__helpstring += "./acswui -v --ini acswui.ini --install-base-data install\n"

# main arguments
argparser = argparse.ArgumentParser(prog="acswui", description="Assetto Corsa Server Web User Interface", epilog=__helpstring, formatter_class=argparse.RawTextHelpFormatter)
argparser.add_argument('-i', '--ini', help="path to config file")
argparser.add_argument('--install-base-data', action="store_true", help="install basic http data (default groups, etc.)")
argparser.add_argument('-v', action='count', default=0, help="each 'v' increases the verbosity level")
argparsersubs     = argparser.add_subparsers(dest='command')
argparser_srvpkg  = argparsersubs.add_parser('srvpkg', help="server packager - preparing files for http and ac server")
argparser_install = argparsersubs.add_parser('install',help="install / update database and configure http server")


# get arguments
args = argparser.parse_args()
#print("\n\nargs=\n", args);

# check config file
if args.ini is None or not os.path.isfile(args.ini):
    raise ValueError("Parameter --ini must be set with a existing config file!")

# parse config file
config = {}
with open(args.ini, "r") as f:
    for line in f.readlines():

        line = line.strip()

        # ignore commented lines
        if line[:1] == "#":
            continue

        # ignore empty lines
        if line == "":
            continue

        # split keys and values
        split = line.split("=",1)
        if len(split) > 1:
            config.update({split[0].strip(): split[1].strip()})

# ---------------------
#  - Execute Command -
# ---------------------

# ServerPackager
if args.command == "srvpkg":
    srvpkg = ServerPackager(config, args.v)
    srvpkg.work()

# Installer
elif args.command == "install":
    install = Installer(config, args.v, args.install_base_data)
    install.work()

# unknown command
else:
    raise ValueError("unknown command")


# finish
if int(args.v) > 0:
    print("Finished")

