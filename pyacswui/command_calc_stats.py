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

        self.__calc_track_popularity()
        self.__calc_carclass_popularity()
        self.__calc_track_records()
        self.__calc_carclass_records()



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



    def __calc_track_popularity(self):
        self.Verbosity.print("track popularity")

        # count drivers
        amount_drivers = 0
        for row in self.__db.fetch("Users", ['Steam64GUID'], {}):
            if row['Steam64GUID'] != "":
                amount_drivers += 1

        # get track info
        tracks_dict = {}
        for row in self.__db.fetch("Tracks", ['Id', 'Name', 'Track', 'Config', 'Length', 'Pitboxes'], {}):
            row['DriversList'] = []
            row['DrivenLaps'] = 0
            row['DrivenMeters'] = 0
            row['DrivenSeconds'] = 0
            row['Popularity'] = 0.0
            tracks_dict.update({row['Id']: row})

        # count laps and drivers
        query = "SELECT Sessions.Track, Users.Id, Laps.Laptime FROM Laps"
        query += " INNER JOIN Sessions On Sessions.Id=Laps.Session"
        query += " INNER JOIN Users On Users.Id=Laps.User"
        cursor = pymysql.cursors.SSDictCursor(self.__db.Handle)
        cursor.execute(query)
        for row in cursor:
            track_id = row['Track']
            user_id = row['Id']
            laptime = int(row['Laptime'])
            tracks_dict[track_id]['DrivenLaps'] += 1
            tracks_dict[track_id]['DrivenMeters'] += tracks_dict[track_id]['Length']
            tracks_dict[track_id]['DrivenSeconds'] += laptime
            if user_id not in tracks_dict[track_id]['DriversList']:
                tracks_dict[track_id]['DriversList'].append(user_id)

        # normalize values
        for track_id in tracks_dict.keys():
            mseconds = tracks_dict[track_id]['DrivenSeconds']
            seconds = int(mseconds / 1000)
            tracks_dict[track_id]['DrivenSeconds'] = seconds


        # calculate popularity
        # according to diriven length rated by drivers
        popularity_max = None
        track_ids_to_be_popped = []
        for track_id in tracks_dict.keys():

            if tracks_dict[track_id]['DrivenMeters'] == 0:
                track_ids_to_be_popped.append(track_id)
                continue

            popularity = tracks_dict[track_id]['DrivenMeters']
            popularity *= len(tracks_dict[track_id]['DriversList']) / amount_drivers
            tracks_dict[track_id]['Popularity'] = popularity
            if popularity_max is None or popularity > popularity_max:
                popularity_max = popularity

        # remove undriven tracks
        for track_id in track_ids_to_be_popped:
            tracks_dict.pop(track_id)

        # normalize popularity to maximum popularity
        for track_id in tracks_dict.keys():
            popularity = tracks_dict[track_id]['Popularity']
            tracks_dict[track_id]['Popularity'] = popularity / popularity_max

        # create sorted list
        track_popularity_list = []
        for track_dict in sorted(tracks_dict.items(), key=lambda x: x[1]['Popularity'], reverse=True):
            track_popularity_list.append(track_dict[1])

        # dump
        self.__dump_json(track_popularity_list, os.path.join(self.getArg('http-path-acs-content'), "stats_track_popularity.json"))



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



    def __calc_track_records(self):
        """
            Generated Json:
            {
                TrackId : {
                    CarClassId: [LapId<1st>, LapId<2nd>, LapId<3rd>, ...],
                    ...
                },
                ...
            }

            TODO: The execution time of this script may can significantly improved
            when not iterating over the Laps table for each track and car-class.
        """
        self.Verbosity.print("track records")


        track_records_dict = {}


        # iterate over all tracks
        for row_track in self.__db.fetch("Tracks", ['Id', 'Name'], {}):
            track_id = int(row_track['Id'])
            track_name = row_track['Name']


            # iterate over car classes
            for row_carclass in self.__db.fetch("CarClasses", ['Id', 'Name'], {}):
                carclass_id = int(row_carclass['Id'])
                carclass_name = row_carclass['Name']

                # dictionary that holds the best lap of each user
                # for this carclass on this track
                # key=user-id, value=laptime
                user_records = {}

                # iterate over all laps
                query = "SELECT Laps.Id, Laps.Laptime, Laps.User, Laps.Ballast, Laps.Restrictor, CarSkins.Car FROM `Laps`"
                query += " INNER JOIN Sessions ON Sessions.Id=Laps.Session"
                query += " INNER JOIN CarSkins ON CarSkins.Id=Laps.CarSkin"
                query += " WHERE Laps.Cuts = 0 AND Sessions.Track = " + str(track_id)
                cursor = pymysql.cursors.SSDictCursor(self.__db.Handle)
                cursor.execute(query)
                for row_lap in cursor:

                    lap_id = int(row_lap['Id'])
                    lap_time = int(row_lap['Laptime'])
                    lap_user = int(row_lap['User'])
                    lap_ballast = int(row_lap['Ballast'])
                    lap_restrictor = int(row_lap['Restrictor'])
                    lap_car = int(row_lap['Car'])

                    # check for car class compliance
                    if lap_car not in self.__allowed_carclass_cars[carclass_id]:
                        continue
                    if lap_ballast < self.__allowed_carclass_cars[carclass_id][lap_car]['Ballast']:
                        continue
                    if lap_restrictor < self.__allowed_carclass_cars[carclass_id][lap_car]['Restrictor']:
                        continue

                    # save laptime for user
                    if lap_user not in user_records or lap_time < user_records[lap_user]['LapTime']:
                        user_records[lap_user] = {}
                        user_records[lap_user]['LapTime'] = lap_time
                        user_records[lap_user]['LapId'] = lap_id


                # sort laptimes
                best_laps = []
                for user_record in sorted(user_records.items(), key=lambda x: x[1]['LapTime']):
                    best_laps.append(user_record[1]['LapId'])


                # store records
                if best_laps != []:
                    if track_id not in track_records_dict:
                        track_records_dict[track_id] = {}
                    if carclass_id not in track_records_dict[track_id]:
                        track_records_dict[track_id][carclass_id] = []
                    track_records_dict[track_id][carclass_id] = best_laps


                # report
                if best_laps != []:
                    self.Verbosity2.print("Found best laps on track '%s' with car class '%s'" % (track_name, carclass_name))


        # dump
        self.__dump_json(track_records_dict, os.path.join(self.getArg('http-path-acs-content'), "stats_track_records.json"))



    def __calc_carclass_records(self):
        """
            Generated Json:
            {
                CarClassId : {
                    TrackId: [LapId<1st>, LapId<2nd>, LapId<3rd>, ...],
                    ...
                },
                ...
            }

            TODO: The execution time of this script may can significantly improved
            when not iterating over the Laps table for each track and car-class.
        """
        self.Verbosity.print("car class records")

        carclass_records_dict = {}


        # iterate over car classes
        for row_carclass in self.__db.fetch("CarClasses", ['Id', 'Name'], {}):
            carclass_id = int(row_carclass['Id'])
            carclass_name = row_carclass['Name']

            # iterate over all tracks
            for row_track in self.__db.fetch("Tracks", ['Id', 'Name'], {}):
                track_id = int(row_track['Id'])
                track_name = row_track['Name']


                # dictionary that holds the best lap of each user
                # for this carclass on this track
                # key=user-id, value=laptime
                user_records = {}


                # iterate over all laps
                query = "SELECT Laps.Id, Laps.Laptime, Laps.User, Laps.Ballast, Laps.Restrictor, CarSkins.Car FROM `Laps`"
                query += " INNER JOIN Sessions ON Sessions.Id=Laps.Session"
                query += " INNER JOIN CarSkins ON CarSkins.Id=Laps.CarSkin"
                query += " WHERE Laps.Cuts = 0 AND Sessions.Track = " + str(track_id)
                cursor = pymysql.cursors.SSDictCursor(self.__db.Handle)
                cursor.execute(query)
                for row_lap in cursor:

                    lap_id = int(row_lap['Id'])
                    lap_time = int(row_lap['Laptime'])
                    lap_user = int(row_lap['User'])
                    lap_ballast = int(row_lap['Ballast'])
                    lap_restrictor = int(row_lap['Restrictor'])
                    lap_car = int(row_lap['Car'])

                    # check for car class compliance
                    if lap_car not in self.__allowed_carclass_cars[carclass_id]:
                        continue
                    if lap_ballast < self.__allowed_carclass_cars[carclass_id][lap_car]['Ballast']:
                        continue
                    if lap_restrictor < self.__allowed_carclass_cars[carclass_id][lap_car]['Restrictor']:
                        continue

                    # save laptime for user
                    if lap_user not in user_records or lap_time < user_records[lap_user]['LapTime']:
                        user_records[lap_user] = {}
                        user_records[lap_user]['LapTime'] = lap_time
                        user_records[lap_user]['LapId'] = lap_id


                # sort laptimes
                best_laps = []
                for user_record in sorted(user_records.items(), key=lambda x: x[1]['LapTime']):
                    best_laps.append(user_record[1]['LapId'])


                # store records
                if best_laps != []:
                    if carclass_id not in carclass_records_dict:
                        carclass_records_dict[carclass_id] = {}

                    if track_id not in carclass_records_dict[carclass_id]:
                        carclass_records_dict[carclass_id][track_id] = []
                    carclass_records_dict[carclass_id][track_id] = best_laps


                # report
                if best_laps != []:
                    self.Verbosity2.print("Found best laps with car class '%s' on track '%s'" % (carclass_name, track_name))


        # dump
        self.__dump_json(carclass_records_dict, os.path.join(self.getArg('http-path-acs-content'), "stats_carclass_records.json"))
