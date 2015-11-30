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

__helpstring = "FIXME"

# main arguments
argparser = argparse.ArgumentParser(prog="acswui", description="Assetto Corsa Server Web User Interface", epilog=__helpstring)
argparsersubs = argparser.add_subparsers(dest='command')

# command install
argparser_install = argparsersubs.add_parser('install',help="install or update acswui")

# command srvpkg
argparser_srvpkg = argparsersubs.add_parser('srvpkg', help="server packager")

# add common arguments to add_subparsers
for parser in [argparser_install, argparser_srvpkg]:
  parser.add_argument('--args-file', help="path to an text file that contains all the arguments")
  parser.add_argument('-v', action='count', default=0, help="each 'v' increases the verbosity level")
  parser.add_argument('--path-ac', help="path to the assetto corsa game directory")
  parser.add_argument('--path-acs', help="path to the assetto corsa server directory")
  parser.add_argument('--db-host', help="the database server host")
  parser.add_argument('--db-port', help="the database server port")
  parser.add_argument('--db-user', help="the database server username")
  parser.add_argument('--db-database', help="the database server database")
  parser.add_argument('--db-passwd', help="the database server database")

# get arguments
args = argparser.parse_args()
#print("\n\nargs=\n", args);

# load arguments from file if argument --args-file was set
if hasattr(args, 'args_file') and args.args_file is not None:

    # load argument list from file
    with open (args.args_file, "r") as argsfile:
        fileargs = argsfile.read().split()

    # get arguments from commadn line
    # ignore fist commadnline argument (program name)
    # ignore command argument
    cmdargs = []
    cmdargs += [ element for element in sys.argv[1:] if element != args.command]

    # reparse arguments
    # command is applied as first argument
    # commandline arguments have higher priority than file arguments
    args = argparser.parse_args([args.command] + fileargs + cmdargs)



# ---------------------
#  - Execute Command -
# ---------------------

# ServerPackager
if args.command == "srvpkg":
    srvpkg = ServerPackager()
    srvpkg.work(args)

# Installer
if args.command == "install":
    install = Installer()
    install.work(args)
