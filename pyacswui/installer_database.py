
from .verbosity import Verbosity

class InstallerDatabase(object):


    def __init__(self,
                 database,
                 server_cfg_json,
                 verbosity=0
                ):
        self.__db = database
        self.__server_cfg_json = server_cfg_json
        self._verbosity = Verbosity(verbosity, self.__class__.__name__)



    def process(self):
        self._verbosity.print("Create database tables")

        self._tables_grey()
        self._tables_mustard()
        self._tables_blue()
        self._tables_red()
        self._tables_purple()
        self._tables_brown()
        self._tables_green()
        self._tables_cyan()



    def _tables_grey(self):
        verb = Verbosity(self._verbosity)
        verb.print("grey tables")

        # check table installer
        Verbosity(verb).print("check database table `installer`")
        self.__db.appendTable("installer")
        self.__db.appendColumnCurrentTimestamp("installer", "timestamp")
        self.__db.appendColumnString("installer", "version", 10)
        self.__db.appendColumnText("installer", "info")

        # insert installer info
        self.__db.insertRow("installer", {"version": "0.0", "info": ""})


        # check table CronJobs
        Verbosity(verb).print("check database table `CronJobs`")
        self.__db.appendTable("CronJobs")
        self.__db.appendColumnString("CronJobs", "Name", 60)
        self.__db.appendColumnCurrentTimestamp("CronJobs", "LastStart")
        self.__db.appendColumnUInt("CronJobs", "LastDuration")
        self.__db.appendColumnString("CronJobs", "Status", 50)
        self.__db.appendColumnUInt("CronJobs", "LastSession")


        # check table ServerPresets
        Verbosity(verb).print("check database table `ServerPresets`")
        self.__db.appendTable("ServerPresets")
        self.__db.appendColumnString("ServerPresets", "Name", 60)
        self.__db.appendColumnInt("ServerPresets", "Restricted")

        for section in self.__server_cfg_json:
            for fieldset in self.__server_cfg_json[section]:
                for tag in self.__server_cfg_json[section][fieldset]:
                    tag_dict = self.__server_cfg_json[section][fieldset][tag]
                    db_col_name = tag_dict['DB_COLUMN_NAME']

                    if tag_dict['TYPE'] == "string":
                        self.__db.appendColumnString("ServerPresets", db_col_name, tag_dict['SIZE'])
                    elif tag_dict['TYPE'] in ["int", "enum"]:
                        self.__db.appendColumnInt("ServerPresets", db_col_name)
                    elif tag_dict['TYPE'] == "text":
                        self.__db.appendColumnText("ServerPresets", db_col_name)
                    else:
                        print("db_col_name =", db_col_name)
                        raise NotImplementedError("Unknown field TYPE '%s'" % tag_dict['TYPE'])



    def _tables_mustard(self):
        verb = Verbosity(self._verbosity)
        verb.print("mustard tables")

        # check table Sessions
        Verbosity(verb).print("check database table `Sessions`")
        self.__db.appendTable("Sessions")
        self.__db.appendColumnUInt("Sessions", "Predecessor")
        self.__db.appendColumnInt("Sessions", "ProtocolVersion")
        self.__db.appendColumnInt("Sessions", "SessionIndex")
        self.__db.appendColumnInt("Sessions", "CurrentSessionIndex")
        self.__db.appendColumnInt("Sessions", "SessionCount")
        self.__db.appendColumnString("Sessions", 'ServerName', 50)
        self.__db.appendColumnUInt("Sessions", "Track")
        self.__db.appendColumnString("Sessions", 'Name', 50)
        self.__db.appendColumnInt("Sessions", "Type")
        self.__db.appendColumnInt("Sessions", "Time")
        self.__db.appendColumnInt("Sessions", "Laps")
        self.__db.appendColumnInt("Sessions", "WaitTime")
        self.__db.appendColumnInt("Sessions", "TempAmb")
        self.__db.appendColumnInt("Sessions", "TempRoad")
        self.__db.appendColumnString("Sessions", 'WheatherGraphics', 50)
        self.__db.appendColumnInt("Sessions", "Elapsed")
        self.__db.appendColumnCurrentTimestamp("Sessions", "Timestamp")
        self.__db.appendColumnUInt("Sessions", "ServerSlot")
        self.__db.appendColumnUInt("Sessions", "ServerPreset")
        self.__db.appendColumnUInt("Sessions", 'CarClass')

        # check table SessionResults
        Verbosity(verb).print("check database table `SessionResults`")
        self.__db.appendTable("SessionResults")
        self.__db.appendColumnSmallInt("SessionResults", "Position")
        self.__db.appendColumnUInt("SessionResults", "Session")
        self.__db.appendColumnUInt("SessionResults", "User")
        self.__db.appendColumnUInt("SessionResults", "CarSkin")
        self.__db.appendColumnUInt("SessionResults", "BestLap")
        self.__db.appendColumnUInt("SessionResults", "TotalTime")
        self.__db.appendColumnSmallInt("SessionResults", "Ballast")
        self.__db.appendColumnTinyInt("SessionResults", "Restrictor")

        # check table Laps
        Verbosity(verb).print("check database table `Laps`")
        self.__db.appendTable("Laps")
        self.__db.appendColumnUInt("Laps", "Session")
        self.__db.appendColumnUInt("Laps", "CarSkin")
        self.__db.appendColumnUInt("Laps", "User")
        self.__db.appendColumnUInt("Laps", "Laptime")
        self.__db.appendColumnInt("Laps", "Cuts")
        self.__db.appendColumnFloat("Laps", "Grip")
        self.__db.appendColumnSmallInt("Laps", "Ballast")
        self.__db.appendColumnTinyInt("Laps", "Restrictor")
        self.__db.appendColumnCurrentTimestamp("Laps", "Timestamp")

        # check table CollisionEnv
        Verbosity(verb).print("check database table `CollisionEnv`")
        self.__db.appendTable("CollisionEnv")
        self.__db.appendColumnUInt("CollisionEnv", "Session")
        self.__db.appendColumnUInt("CollisionEnv", "CarSkin")
        self.__db.appendColumnUInt("CollisionEnv", "User")
        self.__db.appendColumnFloat("CollisionEnv", "Speed")
        self.__db.appendColumnCurrentTimestamp("CollisionEnv", "Timestamp")

        # check table CollisionCar
        Verbosity(verb).print("check database table `CollisionCar`")
        self.__db.appendTable("CollisionCar")
        self.__db.appendColumnUInt("CollisionCar", "Session")
        self.__db.appendColumnUInt("CollisionCar", "CarSkin")
        self.__db.appendColumnUInt("CollisionCar", "User")
        self.__db.appendColumnFloat("CollisionCar", "Speed")
        self.__db.appendColumnUInt("CollisionCar", "OtherUser")
        self.__db.appendColumnUInt("CollisionCar", "OtherCarSkin")
        self.__db.appendColumnCurrentTimestamp("CollisionCar", "Timestamp")



    def _tables_blue(self):
        verb = Verbosity(self._verbosity)
        verb.print("blue tables")

        # check table TrackLocations
        Verbosity(verb).print("check database table `TrackLocations`")
        self.__db.appendTable("TrackLocations")
        self.__db.appendColumnString("TrackLocations", "Track", 80)
        self.__db.appendColumnString("TrackLocations", "Name", 80)
        self.__db.appendColumnTinyInt("TrackLocations", "Deprecated")

        # check table Tracks
        Verbosity(verb).print("check database table `Tracks`")
        self.__db.appendTable("Tracks")
        self.__db.appendColumnUInt("Tracks", "Location")
        #self.__db.appendColumnString("Tracks", "Track", 80)
        self.__db.appendColumnString("Tracks", "Config", 80)
        self.__db.appendColumnString("Tracks", "Name", 80)
        self.__db.appendColumnUInt("Tracks", "Length")
        self.__db.appendColumnInt("Tracks", "Pitboxes")
        self.__db.appendColumnTinyInt("Tracks", "Deprecated")
        self.__db.appendColumnString("Tracks", "Version", 30)
        self.__db.appendColumnString("Tracks", "Author", 50)
        self.__db.appendColumnText("Tracks", "Description")

        # check table CarBrands
        Verbosity(verb).print("check database table `CarBrands`")
        self.__db.appendTable("CarBrands")
        self.__db.appendColumnString("CarBrands", "Name", 80)
        self.__db.appendColumnUInt("CarBrands", "BadgeCar")

        # check table Cars
        Verbosity(verb).print("check database table `Cars`")
        self.__db.appendTable("Cars")
        self.__db.appendColumnUInt("Cars", "Brand")
        self.__db.appendColumnString("Cars", "Car", 80)
        self.__db.appendColumnString("Cars", "Name", 80)
        self.__db.appendColumnUInt("Cars", "Parent")
        self.__db.appendColumnTinyInt("Cars", "Deprecated")
        self.__db.appendColumnText("Cars", "Description")
        self.__db.appendColumnText("Cars", "TorqueCurve")
        self.__db.appendColumnText("Cars", "PowerCurve")
        self.__db.appendColumnUInt("Cars", "Weight")

        # check table CarSkins
        Verbosity(verb).print("check database table `CarSkins`")
        self.__db.appendTable("CarSkins")
        self.__db.appendColumnUInt("CarSkins", "Car")
        self.__db.appendColumnString("CarSkins", "Skin", 50)
        self.__db.appendColumnTinyInt("CarSkins", "Deprecated")
        self.__db.appendColumnString("CarSkins", "Steam64GUID", 50)
        self.__db.appendColumnString("CarSkins", "Number", 20)
        self.__db.appendColumnString("CarSkins", "Name", 80)
        self.__db.appendColumnString("CarSkins", "Team", 80)



    def _tables_red(self):
        verb = Verbosity(self._verbosity)
        verb.print("red tables")

        # check table users
        Verbosity(verb).print("check database table `Users`")
        self.__db.appendTable("Users")
        self.__db.appendColumnString("Users", "Name", 50)
        self.__db.appendColumnString("Users", "Steam64GUID", 50)
        self.__db.appendColumnString("Users", "Color", 10)
        self.__db.appendColumnTinyInt("Users", "Privacy")
        self.__db.appendColumnString("Users", "Locale", 30)
        self.__db.appendColumnUInt("Users", "CurrentSession")

        # check table Groups
        Verbosity(verb).print("check database table `Groups`")
        self.__db.appendTable("Groups")
        self.__db.appendColumnString("Groups", "Name", 50)
        self.__db.appendColumnTinyInt("Groups", "Admin_Group_Management")
        self.__db.appendColumnTinyInt("Groups", "Admin_User_Management")
        self.__db.appendColumnTinyInt("Groups", "ViewServerContent")
        self.__db.appendColumnTinyInt("Groups", "ViewServerContent_Tracks")
        self.__db.appendColumnTinyInt("Groups", "ViewServerContent_Cars")
        self.__db.appendColumnTinyInt("Groups", "ViewServerContent_CarClasses")
        self.__db.appendColumnTinyInt("Groups", "CarClass_Edit")
        self.__db.appendColumnTinyInt("Groups", "ViewUsers")

        # check table UserGroupMap
        Verbosity(verb).print("check database table `UserGroupMap`")
        self.__db.appendTable("UserGroupMap")
        self.__db.appendColumnUInt("UserGroupMap", "User")
        self.__db.appendColumnUInt("UserGroupMap", "Group")



    def _tables_purple(self):
        verb = Verbosity(self._verbosity)
        verb.print("purple tables")

        # check table CarClasses
        Verbosity(verb).print("check database table `CarClasses`")
        self.__db.appendTable("CarClasses")
        self.__db.appendColumnString("CarClasses", 'Name', 50)
        self.__db.appendColumnText("CarClasses", "Description")

        # check table CarClassesMap
        Verbosity(verb).print("check database table `CarClassesMap`")
        self.__db.appendTable("CarClassesMap")
        self.__db.appendColumnUInt("CarClassesMap", 'CarClass')
        self.__db.appendColumnUInt("CarClassesMap", 'Car')
        self.__db.appendColumnSmallInt("CarClassesMap", 'Ballast')
        self.__db.appendColumnSmallInt("CarClassesMap", 'Restrictor')

        # check table CarClassOccupationMap
        Verbosity(verb).print("check database table `CarClassOccupationMap`")
        self.__db.appendTable("CarClassOccupationMap")
        self.__db.appendColumnUInt("CarClassOccupationMap", 'CarClass')
        self.__db.appendColumnUInt("CarClassOccupationMap", 'User')
        self.__db.appendColumnUInt("CarClassOccupationMap", 'CarSkin')



    def _tables_brown(self):
        verb = Verbosity(self._verbosity)
        verb.print("brown tables")

        # check table RacePollDates
        Verbosity(verb).print("check database table `RacePollDates`")
        self.__db.appendTable("RacePollDates")
        self.__db.appendColumnDateTime("RacePollDates", 'Date')
        self.__db.appendColumnUInt("RacePollDates", 'User')

        # check table RacePollDateMap
        Verbosity(verb).print("check database table `RacePollDateMap`")
        self.__db.appendTable("RacePollDateMap")
        self.__db.appendColumnUInt("RacePollDateMap", 'User')
        self.__db.appendColumnUInt("RacePollDateMap", 'Date')
        self.__db.appendColumnInt("RacePollDateMap", 'Availability')

        # check table RacePollCarClasses
        Verbosity(verb).print("check database table `RacePollCarClasses`")
        self.__db.appendTable("RacePollCarClasses")
        self.__db.appendColumnUInt("RacePollCarClasses", 'User')
        self.__db.appendColumnUInt("RacePollCarClasses", 'CarClass')
        self.__db.appendColumnUInt("RacePollCarClasses", 'Score')

        # check table RacePollTracks
        Verbosity(verb).print("check database table `RacePollTracks`")
        self.__db.appendTable("RacePollTracks")
        self.__db.appendColumnUInt("RacePollTracks", 'Track')
        self.__db.appendColumnUInt("RacePollTracks", 'CarClass')

        # check table RacePollTrackMap
        Verbosity(verb).print("check database table `RacePollTrackMap`")
        self.__db.appendTable("RacePollTrackMap")
        self.__db.appendColumnUInt("RacePollTrackMap", 'User')
        self.__db.appendColumnUInt("RacePollTrackMap", 'Track')
        self.__db.appendColumnUInt("RacePollTrackMap", 'Score')


        # check table Polls
        Verbosity(verb).print("check database table `Polls`")
        self.__db.appendTable("Polls")
        self.__db.appendColumnUInt("Polls", 'Creator')
        self.__db.appendColumnSmallInt("Polls", 'IsSecret')
        self.__db.appendColumnUInt("Polls", 'PointsForTracks')
        self.__db.appendColumnUInt("Polls", 'PointsPerTrack')
        self.__db.appendColumnUInt("Polls", 'PointsForCarClasses')
        self.__db.appendColumnUInt("Polls", 'PointsPerCarClass')
        self.__db.appendColumnString("Polls", 'Name', 50)
        self.__db.appendColumnText("Polls", 'Description')
        self.__db.appendColumnDateTime("Polls", 'Closing')

        # check table PollTracks
        Verbosity(verb).print("check database table `PollTracks`")
        self.__db.appendTable("PollTracks")
        self.__db.appendColumnUInt("PollTracks", 'Poll')
        self.__db.appendColumnUInt("PollTracks", 'Track')

        # check table PollCarClasses
        Verbosity(verb).print("check database table `PollCarClasses`")
        self.__db.appendTable("PollCarClasses")
        self.__db.appendColumnUInt("PollCarClasses", 'Poll')
        self.__db.appendColumnUInt("PollCarClasses", 'CarClass')

        # check table PollVotes
        Verbosity(verb).print("check database table `PollVotes`")
        self.__db.appendTable("PollVotes")
        self.__db.appendColumnUInt("PollVotes", 'User')
        self.__db.appendColumnUInt("PollVotes", 'PollTrack')
        self.__db.appendColumnUInt("PollVotes", 'PollCarClass')
        self.__db.appendColumnUInt("PollVotes", 'Points')




    def _tables_green(self):
        verb = Verbosity(self._verbosity)
        verb.print("green tables")

        # check table Championships
        Verbosity(verb).print("check database table `Championships`")
        self.__db.appendTable("Championships")
        self.__db.appendColumnUInt("Championships", 'ServerPreset')
        self.__db.appendColumnString("Championships", "Name", 100)
        self.__db.appendColumnString("Championships", "CarClasses", 100)
        self.__db.appendColumnString("Championships", "QualifyPositionPoints", 100)
        self.__db.appendColumnString("Championships", "RacePositionPoints", 100)
        self.__db.appendColumnString("Championships", "RaceTimePoints", 100)
        self.__db.appendColumnString("Championships", "RaceLeadLapPoints", 100)
        self.__db.appendColumnString("Championships", "BallanceBallast", 100)
        self.__db.appendColumnString("Championships", "BallanceRestrictor", 100)
        self.__db.appendColumnString("Championships", "Tracks", 100)

        # check table SessionQueue
        Verbosity(verb).print("check database table `SessionQueue`")
        self.__db.appendTable("SessionQueue")
        self.__db.appendColumnString("SessionQueue", "Name", 100)
        self.__db.appendColumnInt("SessionQueue", 'Enabled')
        self.__db.appendColumnInt("SessionQueue", 'SeatOccupations')
        self.__db.appendColumnUInt("SessionQueue", 'Preset')
        self.__db.appendColumnUInt("SessionQueue", 'CarClass')
        self.__db.appendColumnUInt("SessionQueue", 'Track')
        self.__db.appendColumnInt("SessionQueue", 'Slot')

        # check table SessionSchedule
        Verbosity(verb).print("check database table `SessionSchedule`")
        self.__db.appendTable("SessionSchedule")
        self.__db.appendColumnString("SessionSchedule", "Name", 100)
        self.__db.appendColumnTimestamp("SessionSchedule", 'Start')
        self.__db.appendColumnInt("SessionSchedule", 'SeatOccupations')
        self.__db.appendColumnUInt("SessionSchedule", 'Preset')
        self.__db.appendColumnUInt("SessionSchedule", 'CarClass')
        self.__db.appendColumnUInt("SessionSchedule", 'Track')
        self.__db.appendColumnInt("SessionSchedule", 'Slot')
        self.__db.appendColumnInt("SessionSchedule", 'Executed')



    def _tables_cyan(self):
        verb = Verbosity(self._verbosity)
        verb.print("cyan tables")

        # check table DriverRanking
        Verbosity(verb).print("check database table `DriverRanking`")
        self.__db.appendTable("DriverRanking")
        self.__db.appendColumnUInt("DriverRanking", 'User')
        self.__db.appendColumnCurrentTimestamp("DriverRanking", "Timestamp")
        self.__db.appendColumnFloat("DriverRanking", 'XP_R')
        self.__db.appendColumnFloat("DriverRanking", 'XP_Q')
        self.__db.appendColumnFloat("DriverRanking", 'XP_P')
        self.__db.appendColumnFloat("DriverRanking", 'SX_R')
        self.__db.appendColumnFloat("DriverRanking", 'SX_Q')
        self.__db.appendColumnFloat("DriverRanking", 'SX_RT')
        self.__db.appendColumnFloat("DriverRanking", 'SX_BT')
        self.__db.appendColumnFloat("DriverRanking", 'SF_CT')
        self.__db.appendColumnFloat("DriverRanking", 'SF_CE')
        self.__db.appendColumnFloat("DriverRanking", 'SF_CC')

        # check table StatsGeneral
        Verbosity(verb).print("check database table `StatsGeneral`")
        self.__db.appendTable("StatsGeneral")
        self.__db.appendColumnCurrentTimestamp("StatsGeneral", "Timestamp")
        self.__db.appendColumnUInt("StatsGeneral", "LastScannedLap")
        self.__db.appendColumnUInt("StatsGeneral", "LastScannedColCar")
        self.__db.appendColumnUInt("StatsGeneral", "LastScannedColEnv")
        self.__db.appendColumnUInt("StatsGeneral", "LapsValid")
        self.__db.appendColumnUInt("StatsGeneral", "LapsInvalid")
        self.__db.appendColumnUInt("StatsGeneral", "MetersValid")
        self.__db.appendColumnUInt("StatsGeneral", "MetersInvalid")
        self.__db.appendColumnUInt("StatsGeneral", "SecondsValid")
        self.__db.appendColumnUInt("StatsGeneral", "SecondsInvalid")
        self.__db.appendColumnUInt("StatsGeneral", "Cuts")
        self.__db.appendColumnUInt("StatsGeneral", "CollisionsCar")
        self.__db.appendColumnUInt("StatsGeneral", "CollisionsEnvironment")

        # check table StatsTrackPopularity
        Verbosity(verb).print("check database table `StatsTrackPopularity`")
        self.__db.appendTable("StatsTrackPopularity")
        self.__db.appendColumnCurrentTimestamp("StatsTrackPopularity", "Timestamp")
        self.__db.appendColumnUInt("StatsTrackPopularity", "LastScannedLap")
        self.__db.appendColumnUInt("StatsTrackPopularity", "Track")
        self.__db.appendColumnUInt("StatsTrackPopularity", "LapCount")
        self.__db.appendColumnFloat("StatsTrackPopularity", "Popularity")
        self.__db.appendColumnFloat("StatsTrackPopularity", "LaptimeCumulative")

        # check table StatsCarClassPopularity
        Verbosity(verb).print("check database table `StatsCarClassPopularity`")
        self.__db.appendTable("StatsCarClassPopularity")
        self.__db.appendColumnCurrentTimestamp("StatsCarClassPopularity", "Timestamp")
        self.__db.appendColumnUInt("StatsCarClassPopularity", "CarClass")
        self.__db.appendColumnUInt("StatsCarClassPopularity", "LastScannedLap")
        self.__db.appendColumnUInt("StatsCarClassPopularity", "LapCount")
        self.__db.appendColumnFloat("StatsCarClassPopularity", "TimeCount")
        self.__db.appendColumnFloat("StatsCarClassPopularity", "MeterCount")
        self.__db.appendColumnFloat("StatsCarClassPopularity", "Popularity")


