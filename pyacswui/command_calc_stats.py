import json
import os.path
import pymysql
import statistics
import subprocess
import matplotlib
import matplotlib.pyplot

from .command import Command
from .database import Database
from .verbosity import Verbosity


class CommandCalcStats(Command):

    def __init__(self, argparser):
        Command.__init__(self, argparser, "calc-stats", "Calculate statistics and write the results to the http content directory")

        ##################
        # Basic Arguments

        # database
        self.add_argument('--db-host', help="Database host (not needed when global config is given)")
        self.add_argument('--db-port', help="Database port (not needed when global config is given)")
        self.add_argument('--db-database', help="Database name (not needed when global config is given)")
        self.add_argument('--db-user', help="Database username (not needed when global config is given)")
        self.add_argument('--db-password', help="Database password (not needed when global config is given)")
        self.add_argument('--http-guid', help="Name of webserver group on the server (needed to chmod access rights)")
        self.add_argument('--fast', action="store_true", help="Try to just update the new data instead of everything")

        # http settings
        self.add_argument('--http-path-acs-content', help="Path that stores AC data for http access (eg. track car preview images)")


    def process(self):
        self.Verbosity2 = Verbosity(self.Verbosity)

        # setup database
        self.__db = Database(host=self.getArg("db_host"),
                             port=self.getArg("db_port"),
                             database=self.getArg("db_database"),
                             user=self.getArg("db_user"),
                             password=self.getArg("db_password"),
                             verbosity=Verbosity(Verbosity(self.Verbosity))
                             )

        # scan for allowed cars in a class
        self.__allowed_carclass_cars = {}
        for row_carclass in self.__db.fetch("CarClasses", ['Id'], {}):
            carclass_id = int(row_carclass['Id'])
            self.__allowed_carclass_cars[carclass_id] = {}

            for row_car in self.__db.fetch("CarClassesMap", ['Car', 'Ballast', 'Restrictor'], {"CarClass": carclass_id}):
                car_id = int(row_car['Car'])
                ballast = int(row_car['Ballast'])
                restrictor = int(row_car['Restrictor'])

                self.__allowed_carclass_cars[carclass_id][car_id] = {}
                self.__allowed_carclass_cars[carclass_id][car_id]['Ballast'] = ballast
                self.__allowed_carclass_cars[carclass_id][car_id]['Restrictor'] = restrictor

        self.__calc_carclass_popularity()



    def change_group(self, path):
        try:
            self.getArg("http-guid")
            chgrp = True
        except ArgumentException:
            chgrp = False
        if chgrp:
            cmd = ["chgrp", self.getArg("http-guid"), path]
            subprocess.run(cmd)



    def __dump_json(self, var, filepath):

        # dump json to file
        with open(filepath, "w") as f:
            json.dump(var, f, indent=4)
        self.change_group(filepath)



    def __calc_carclass_popularity(self):
        self.Verbosity.print("car class popularity")

        # count drivers
        amount_drivers = 0
        for row in self.__db.fetch("Users", ['Steam64GUID'], {}):
            if row['Steam64GUID'] != "":
                amount_drivers += 1


        # get track lengths
        track_lengths = {}
        for row_track in self.__db.fetch("Tracks", ['Id', 'Length'], {}):
            track_id = int(row_track['Id'])
            track_length = row_track['Length']
            track_lengths[track_id] = int(track_length)


        # get car classes
        carclasse_popularity_dict = {}
        for row_carclass in self.__db.fetch("CarClasses", ['Id', 'Name'], {}):
            carclass_id = int(row_carclass['Id'])
            row_carclass['DriversList'] = []
            row_carclass['DrivenLaps'] = 0
            row_carclass['DrivenMeters'] = 0
            row_carclass['DrivenSeconds'] = 0
            row_carclass['Popularity'] = 0.0
            carclasse_popularity_dict[carclass_id] = row_carclass


            # count laps and drivers
            query = "SELECT Sessions.Track, Laps.User, CarSkins.Car, Laps.Ballast, Laps.Restrictor, Laps.Laptime FROM Laps"
            query += " INNER JOIN Sessions On Sessions.Id=Laps.Session"
            query += " INNER JOIN CarSkins On CarSkins.Id=Laps.CarSkin"
            cursor = pymysql.cursors.SSDictCursor(self.__db.Handle)
            cursor.execute(query)
            for row_lap in cursor:
                track_id = int(row_lap['Track'])
                user_id = int(row_lap['User'])
                car_id = int(row_lap['Car'])
                car_ballast = int(row_lap['Ballast'])
                car_restrictor = int(row_lap['Restrictor'])
                laptime = int(row_lap['Laptime'])

                # check car class compliance
                if car_id not in self.__allowed_carclass_cars[carclass_id]:
                    continue
                if car_ballast < self.__allowed_carclass_cars[carclass_id][car_id]['Ballast']:
                    continue
                if car_restrictor < self.__allowed_carclass_cars[carclass_id][car_id]['Restrictor']:
                    continue

                # count stats
                carclasse_popularity_dict[carclass_id]['DrivenLaps'] += 1
                carclasse_popularity_dict[carclass_id]['DrivenMeters'] += track_lengths[track_id]
                carclasse_popularity_dict[carclass_id]['DrivenSeconds'] += laptime
                if user_id not in carclasse_popularity_dict[carclass_id]['DriversList']:
                    carclasse_popularity_dict[carclass_id]['DriversList'].append(user_id)


        # normalize values
        for carclass_id in carclasse_popularity_dict.keys():

            mseconds = carclasse_popularity_dict[carclass_id]['DrivenSeconds']
            seconds = int(mseconds / 1000)
            carclasse_popularity_dict[carclass_id]['DrivenSeconds'] = seconds


        # calculate popularities
        # according to diriven length rated by drivers
        popularity_max = None
        for carclass_id in carclasse_popularity_dict.keys():

            popularity = carclasse_popularity_dict[carclass_id]['DrivenMeters']
            popularity *= len(carclasse_popularity_dict[carclass_id]['DriversList']) / amount_drivers
            carclasse_popularity_dict[carclass_id]['Popularity'] = popularity
            if popularity_max is None or popularity > popularity_max:
                popularity_max = popularity

        # normalize popularity to maximum popularity
        for carclass_id in carclasse_popularity_dict.keys():
            popularity = carclasse_popularity_dict[carclass_id]['Popularity']
            carclasse_popularity_dict[carclass_id]['Popularity'] = popularity / popularity_max

        # create sorted list
        carclass_popularity_list = []
        for carclass_tuple in sorted(carclasse_popularity_dict.items(), key=lambda x: x[1]['Popularity'], reverse=True):
            carclass_popularity_list.append(carclass_tuple[1])

        # dump
        self.__dump_json(carclass_popularity_list, os.path.join(self.getArg('http-path-acs-content'), "stats_carclass_popularity.json"))
