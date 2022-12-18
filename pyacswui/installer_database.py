
from .verbosity import Verbosity


class InstallerDatabase(object):


    def __init__(self, database, verbosity=0):
        self.__db = database
        self._verbosity = Verbosity(verbosity, self.__class__.__name__)



    def process(self):
        self._verbosity.print("Create database tables")

        self._tables_grey()
        self._tables_red()
        self._tables_purple()
        self._tables_green()

        self._tables_accontent()
        self._tables_polls()
        self._tables_rser()
        self._tables_sessions()
        self._tables_teams()



    def _tables_grey(self):
        verb = Verbosity(self._verbosity)
        verb.print("grey tables")

        # check table installer
        Verbosity(verb).print("check database table `installer`")
        self.__db.appendTable("installer")
        self.__db.appendColumnCurrentTimestamp("installer", "timestamp")
        self.__db.appendColumnString("installer", "version", 10)
        self.__db.appendColumnText("installer", "info")

        # check table ServerPresets
        Verbosity(verb).print("check database table `ServerPresets`")
        self.__db.appendTable("ServerPresets")
        self.__db.appendColumnString("ServerPresets", "Name", 60)
        self.__db.appendColumnUInt("ServerPresets", "Parent")
        self.__db.appendColumnText("ServerPresets", "ParameterData")

        # check table Weathers
        Verbosity(verb).print("check database table `Weathers`")
        self.__db.appendTable("Weathers")
        self.__db.appendColumnString("Weathers", "Name", 60)
        self.__db.appendColumnUInt("Weathers", "Parent")
        self.__db.appendColumnText("Weathers", "ParameterData")

        # check table CarSkinRegistrations
        Verbosity(verb).print("check database table `CarSkinRegistrations`")
        self.__db.appendTable("CarSkinRegistrations")
        self.__db.appendColumnUInt("CarSkinRegistrations", "CarSkin")
        self.__db.appendColumnCurrentTimestamp("CarSkinRegistrations", "Requested")
        self.__db.appendColumnTimestamp("CarSkinRegistrations", "Processed")
        self.__db.appendColumnText("CarSkinRegistrations", "Info")



    def _tables_red(self):
        verb = Verbosity(self._verbosity)
        verb.print("red tables")

        table_name = "Users"
        self.__db.appendTable(table_name)
        self.__db.appendColumnString(table_name, "Name", 50)
        self.__db.appendColumnString(table_name, "Steam64GUID", 50)
        self.__db.appendColumnUInt(table_name, "CurrentSession")
        self.__db.appendColumnText(table_name, "ParameterData")
        self.__db.appendColumnDateTime(table_name, 'LastLogin')
        self.__db.appendColumnUInt(table_name, "RankingGroup")
        self.__db.appendColumnFloat(table_name, "RankingPoints")

        table_name = "Groups"
        self.__db.appendTable(table_name)
        self.__db.appendColumnString(table_name, "Name", 50)

        table_name = "UserGroupMap"
        self.__db.appendTable(table_name)
        self.__db.appendColumnUInt(table_name, "User")
        self.__db.appendColumnUInt(table_name, "Group")

        table_name = "LoginTokens"
        self.__db.appendTable(table_name)
        self.__db.appendColumnUInt(table_name, "User")
        self.__db.appendColumnString(table_name, "Token", 50)
        self.__db.appendColumnString(table_name, "Password", 100)
        self.__db.appendColumnDateTime(table_name, 'Timestamp')

        table_name = "DriverRanking"
        self.__db.appendTable(table_name)
        self.__db.appendColumnUInt(table_name, 'User')
        self.__db.appendColumnCurrentTimestamp(table_name, "Timestamp")
        self.__db.appendColumnText(table_name, "RankingData")
        self.__db.appendColumnFloat(table_name, "RankingPoints")



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


    def _tables_green(self):
        verb = Verbosity(self._verbosity)
        verb.print("green tables")

        # check table SessionLoops
        Verbosity(verb).print("check database table `SessionLoops`")
        self.__db.appendTable("SessionLoops")
        self.__db.appendColumnString("SessionLoops", "Name", 100)
        self.__db.appendColumnInt("SessionLoops", 'Enabled')
        self.__db.appendColumnUInt("SessionLoops", 'Preset')
        self.__db.appendColumnUInt("SessionLoops", 'CarClass')
        self.__db.appendColumnUInt("SessionLoops", 'Track')
        self.__db.appendColumnInt("SessionLoops", 'Slot')
        self.__db.appendColumnTimestamp("SessionLoops", 'LastStart')

        # check table SessionSchedule
        Verbosity(verb).print("check database table `SessionSchedule`")
        self.__db.appendTable("SessionSchedule")
        self.__db.appendColumnTimestamp("SessionSchedule", 'Start')
        self.__db.appendColumnUInt("SessionSchedule", 'CarClass')
        self.__db.appendColumnUInt("SessionSchedule", 'Track')
        self.__db.appendColumnUInt("SessionSchedule", 'ServerPreset')
        self.__db.appendColumnText("SessionSchedule", "ParameterData")
        self.__db.appendColumnTimestamp("SessionSchedule", 'Executed')
        self.__db.appendColumnUInt("SessionSchedule", 'Slot')

        # check table SessionScheduleRegistrations
        Verbosity(verb).print("check database table `SessionScheduleRegistrations`")
        self.__db.appendTable("SessionScheduleRegistrations")
        self.__db.appendColumnUInt("SessionScheduleRegistrations", 'SessionSchedule')
        self.__db.appendColumnUInt("SessionScheduleRegistrations", 'User')
        self.__db.appendColumnUInt("SessionScheduleRegistrations", 'CarSkin')
        self.__db.appendColumnSmallInt("SessionScheduleRegistrations", "Ballast")
        self.__db.appendColumnTinyInt("SessionScheduleRegistrations", "Restrictor")
        self.__db.appendColumnTinyInt("SessionScheduleRegistrations", 'Active')
        self.__db.appendColumnCurrentTimestamp("SessionScheduleRegistrations", "Activated")
        self.__db.appendColumnUInt("SessionScheduleRegistrations", 'TeamCar')



    def _tables_accontent(self):
        verb = Verbosity(self._verbosity)
        verb.print("AC Content tables")

        table_name = "TrackLocations"
        self.__db.appendTable(table_name)
        self.__db.appendColumnString(table_name, "Track", 80)
        self.__db.appendColumnString(table_name, "Name", 80)
        self.__db.appendColumnTinyInt(table_name, "Deprecated")
        self.__db.appendColumnString(table_name, "Country", 80)
        self.__db.appendColumnFloat(table_name, "Latitude")
        self.__db.appendColumnFloat(table_name, "Longitude")
        self.__db.appendColumnText(table_name, "DownloadUrl")
        self.__db.appendColumnTinyInt(table_name, "KunosOriginal")

        table_name = "Tracks"
        self.__db.appendTable(table_name)
        self.__db.appendColumnUInt(table_name, "Location")
        self.__db.appendColumnString(table_name, "Config", 80)
        self.__db.appendColumnString(table_name, "Name", 80)
        self.__db.appendColumnUInt(table_name, "Length")
        self.__db.appendColumnInt(table_name, "Pitboxes")
        self.__db.appendColumnTinyInt(table_name, "Deprecated")
        self.__db.appendColumnString(table_name, "Version", 30)
        self.__db.appendColumnString(table_name, "Author", 50)
        self.__db.appendColumnText(table_name, "Description")
        self.__db.appendColumnBool(table_name, "RpTrackfile")

        table_name = "CarBrands"
        self.__db.appendTable(table_name)
        self.__db.appendColumnString(table_name, "Name", 80)
        self.__db.appendColumnUInt(table_name, "BadgeCar")

        table_name = "Cars"
        self.__db.appendTable(table_name)
        self.__db.appendColumnUInt(table_name, "Brand")
        self.__db.appendColumnString(table_name, "Car", 80)
        self.__db.appendColumnString(table_name, "Name", 80)
        self.__db.appendColumnUInt(table_name, "Parent")
        self.__db.appendColumnTinyInt(table_name, "Deprecated")
        self.__db.appendColumnText(table_name, "Description")
        self.__db.appendColumnText(table_name, "TorqueCurve")
        self.__db.appendColumnText(table_name, "PowerCurve")
        self.__db.appendColumnUInt(table_name, "Weight")
        self.__db.appendColumnText(table_name, "DownloadUrl")
        self.__db.appendColumnTinyInt(table_name, "KunosOriginal")

        table_name = "CarSkins"
        self.__db.appendTable(table_name)
        self.__db.appendColumnUInt(table_name, "Car")
        self.__db.appendColumnString(table_name, "Skin", 50)
        self.__db.appendColumnTinyInt(table_name, "Deprecated")
        self.__db.appendColumnString(table_name, "Number", 20)
        self.__db.appendColumnString(table_name, "Name", 80)
        self.__db.appendColumnUInt(table_name, "Owner")


    def _tables_polls(self):
        verb = Verbosity(self._verbosity)
        verb.print("polls tables")

        table_name = "Polls"
        self.__db.appendTable(table_name)
        self.__db.appendColumnUInt(table_name, 'Creator')
        self.__db.appendColumnSmallInt(table_name, 'IsSecret')
        self.__db.appendColumnUInt(table_name, 'PointsForTracks')
        self.__db.appendColumnUInt(table_name, 'PointsPerTrack')
        self.__db.appendColumnUInt(table_name, 'PointsForCarClasses')
        self.__db.appendColumnUInt(table_name, 'PointsPerCarClass')
        self.__db.appendColumnString(table_name, 'Name', 50)
        self.__db.appendColumnText(table_name, 'Description')
        self.__db.appendColumnDateTime(table_name, 'Closing')

        table_name = "PollTracks"
        self.__db.appendTable(table_name)
        self.__db.appendColumnUInt(table_name, 'Poll')
        self.__db.appendColumnUInt(table_name, 'Track')

        table_name = "PollCarClasses"
        self.__db.appendTable(table_name)
        self.__db.appendColumnUInt(table_name, 'Poll')
        self.__db.appendColumnUInt(table_name, 'CarClass')

        table_name = "PollVotes"
        self.__db.appendTable(table_name)
        self.__db.appendColumnUInt(table_name, 'User')
        self.__db.appendColumnUInt(table_name, 'PollTrack')
        self.__db.appendColumnUInt(table_name, 'PollCarClass')
        self.__db.appendColumnUInt(table_name, 'Points')


    def _tables_rser(self):
        verb = Verbosity(self._verbosity)
        verb.print("rser tables")

        table_name = "RSerSeries"
        self.__db.appendTable(table_name)
        self.__db.appendColumnString(table_name, "Name", 50)
        self.__db.appendColumnText(table_name, "ParamColl")

        table_name = "RSerClasses"
        self.__db.appendTable(table_name)
        self.__db.appendColumnString(table_name, "Name", 30)
        self.__db.appendColumnUInt(table_name, "Priority")
        self.__db.appendColumnUInt(table_name, "CarClass")
        self.__db.appendColumnUInt(table_name, "Series")
        self.__db.appendColumnText(table_name, "ParamColl")
        self.__db.appendColumnBool(table_name, "Active")

        table_name = "RSerSeasons"
        self.__db.appendTable(table_name)
        self.__db.appendColumnString(table_name, "Name", 30)
        self.__db.appendColumnUInt(table_name, "Series")

        table_name = "RSerRegistrations"
        self.__db.appendTable(table_name)
        self.__db.appendColumnUInt(table_name, "User")
        self.__db.appendColumnUInt(table_name, "TeamCar")
        self.__db.appendColumnUInt(table_name, "CarSkin")
        self.__db.appendColumnUInt(table_name, "Class")
        self.__db.appendColumnUInt(table_name, "Season")
        self.__db.appendColumnBool(table_name, "Active")

        table_name = "RSerEvents"
        self.__db.appendTable(table_name)
        self.__db.appendColumnUInt(table_name, "Season")
        self.__db.appendColumnUInt(table_name, "Track")

        table_name = "RSerQualifications"
        self.__db.appendTable(table_name)
        self.__db.appendColumnUInt(table_name, "BestLap")
        self.__db.appendColumnUInt(table_name, "Registration")
        self.__db.appendColumnUInt(table_name, "Event")

        table_name = "RSerSplits"
        self.__db.appendTable(table_name)
        self.__db.appendColumnUInt(table_name, "Event")
        self.__db.appendColumnUInt(table_name, "ServerSlot")
        self.__db.appendColumnTimestamp(table_name, "Start")
        self.__db.appendColumnTimestamp(table_name, "Executed")

        table_name = "RSerResults"
        self.__db.appendTable(table_name)
        self.__db.appendColumnUInt(table_name, "Registration")
        self.__db.appendColumnUInt(table_name, "Event")
        self.__db.appendColumnUInt(table_name, "Position")
        self.__db.appendColumnFloat(table_name, "Points")

        table_name = "RSerStandings"
        self.__db.appendTable(table_name)
        self.__db.appendColumnUInt(table_name, "Registration")
        self.__db.appendColumnUInt(table_name, "Season")
        self.__db.appendColumnUInt(table_name, "Position")
        self.__db.appendColumnFloat(table_name, "Points")


    def _tables_sessions(self):
        verb = Verbosity(self._verbosity)
        verb.print("session tables")

        table_name = "Sessions"
        self.__db.appendTable(table_name)
        self.__db.appendColumnInt(table_name, "SessionIndex")
        self.__db.appendColumnUInt(table_name, "Predecessor")
        self.__db.appendColumnInt(table_name, "ProtocolVersion")
        self.__db.appendColumnInt(table_name, "CurrentSessionIndex")
        self.__db.appendColumnInt(table_name, "SessionCount")
        self.__db.appendColumnString(table_name, 'ServerName', 50)
        self.__db.appendColumnUInt(table_name, "Track")
        self.__db.appendColumnString(table_name, 'Name', 50)
        self.__db.appendColumnInt(table_name, "Type")
        self.__db.appendColumnInt(table_name, "Time")
        self.__db.appendColumnInt(table_name, "Laps")
        self.__db.appendColumnInt(table_name, "WaitTime")
        self.__db.appendColumnInt(table_name, "TempAmb")
        self.__db.appendColumnInt(table_name, "TempRoad")
        self.__db.appendColumnString(table_name, 'WheatherGraphics', 50)
        self.__db.appendColumnInt(table_name, "Elapsed")
        self.__db.appendColumnCurrentTimestamp(table_name, "Timestamp")
        self.__db.appendColumnUInt(table_name, "ServerSlot")
        self.__db.appendColumnUInt(table_name, 'SessionSchedule')
        self.__db.appendColumnUInt(table_name, 'RSerSplit')
        self.__db.appendColumnUInt(table_name, "ServerPreset")
        self.__db.appendColumnBool(table_name, "FinalResultsCalculated")

        table_name = "SessionResultsAc"
        self.__db.appendTable(table_name)
        self.__db.appendColumnSmallInt(table_name, "Position")
        self.__db.appendColumnUInt(table_name, "Session")
        self.__db.appendColumnUInt(table_name, "User")
        self.__db.appendColumnUInt(table_name, "CarSkin")
        self.__db.appendColumnUInt(table_name, "TeamCar")
        self.__db.appendColumnUInt(table_name, "BestLap")
        self.__db.appendColumnUInt(table_name, "TotalTime")
        self.__db.appendColumnSmallInt(table_name, "Ballast")
        self.__db.appendColumnTinyInt(table_name, "Restrictor")
        self.__db.appendColumnUInt(table_name, "RSerClass")

        table_name = "Laps"
        self.__db.appendTable(table_name)
        self.__db.appendColumnUInt(table_name, "RSerClass")
        self.__db.appendColumnUInt(table_name, "Session")
        self.__db.appendColumnUInt(table_name, "User")
        self.__db.appendColumnUInt(table_name, "CarSkin")
        self.__db.appendColumnUInt(table_name, "TeamCar")
        self.__db.appendColumnUInt(table_name, "Laptime")
        self.__db.appendColumnInt(table_name, "Cuts")
        self.__db.appendColumnFloat(table_name, "Grip")
        self.__db.appendColumnSmallInt(table_name, "Ballast")
        self.__db.appendColumnTinyInt(table_name, "Restrictor")
        self.__db.appendColumnCurrentTimestamp(table_name, "Timestamp")

        table_name = "CollisionEnv"
        self.__db.appendTable(table_name)
        self.__db.appendColumnUInt(table_name, "Session")
        self.__db.appendColumnUInt(table_name, "User")
        self.__db.appendColumnUInt(table_name, "CarSkin")
        self.__db.appendColumnUInt(table_name, "TeamCar")
        self.__db.appendColumnFloat(table_name, "Speed")
        self.__db.appendColumnCurrentTimestamp(table_name, "Timestamp")

        table_name = "CollisionCar"
        self.__db.appendTable(table_name)
        self.__db.appendColumnUInt(table_name, "Session")
        self.__db.appendColumnUInt(table_name, "User")
        self.__db.appendColumnUInt(table_name, "OtherUser")
        self.__db.appendColumnUInt(table_name, "CarSkin")
        self.__db.appendColumnUInt(table_name, "OtherCarSkin")
        self.__db.appendColumnUInt(table_name, "TeamCar")
        self.__db.appendColumnFloat(table_name, "Speed")
        self.__db.appendColumnCurrentTimestamp(table_name, "Timestamp")

        table_name = "SessionPenalties"
        self.__db.appendTable(table_name)
        self.__db.appendColumnUInt(table_name, "Session")
        self.__db.appendColumnUInt(table_name, "Officer")
        self.__db.appendColumnUInt(table_name, "User")
        self.__db.appendColumnUInt(table_name, "TeamCar")
        self.__db.appendColumnText(table_name, "Cause")
        self.__db.appendColumnInt(table_name, "PenSf")
        self.__db.appendColumnInt(table_name, "PenTime")
        self.__db.appendColumnUInt(table_name, "PenLaps")
        self.__db.appendColumnInt(table_name, "PenPts")
        self.__db.appendColumnBool(table_name, "PenDsq")
        self.__db.appendColumnBool(table_name, "PenDnf")
        self.__db.appendColumnBool(table_name, "Ignored")

        table_name = "SessionResultsFinal"
        self.__db.appendTable(table_name)
        self.__db.appendColumnSmallInt(table_name, "Position")
        self.__db.appendColumnUInt(table_name, "Session")
        self.__db.appendColumnUInt(table_name, "User")
        self.__db.appendColumnUInt(table_name, "CarSkin")
        self.__db.appendColumnUInt(table_name, "TeamCar")
        self.__db.appendColumnUInt(table_name, "BestLaptime")
        self.__db.appendColumnUInt(table_name, "FinalTime")
        self.__db.appendColumnUInt(table_name, "FinalLaps")
        self.__db.appendColumnText(table_name, "RankingPoints")
        # self.__db.appendColumnInt(table_name, "PenTime")
        # self.__db.appendColumnInt(table_name, "PenPts")
        self.__db.appendColumnBool(table_name, "PenDsq")
        self.__db.appendColumnBool(table_name, "PenDnf")


    def _tables_teams(self):
        verb = Verbosity(self._verbosity)
        verb.print("teams tables")

        table_name = "Teams"
        self.__db.appendTable(table_name)
        self.__db.appendColumnUInt(table_name, 'Owner')
        self.__db.appendColumnString(table_name, 'Name', 50)
        self.__db.appendColumnString(table_name, 'Abbreviation', 5)

        table_name = "TeamMembers"
        self.__db.appendTable(table_name)
        self.__db.appendColumnUInt(table_name, 'Team')
        self.__db.appendColumnUInt(table_name, 'User')
        self.__db.appendColumnCurrentTimestamp(table_name, 'Hiring')
        self.__db.appendColumnBool(table_name, 'PermissionManage')
        self.__db.appendColumnBool(table_name, 'PermissionSponsor')
        # self.__db.appendColumnBool(table_name, 'PermissionRegister')
        self.__db.appendColumnBool(table_name, 'Active')

        table_name = "TeamCarClasses"
        self.__db.appendTable(table_name)
        self.__db.appendColumnUInt(table_name, 'Team')
        self.__db.appendColumnUInt(table_name, 'CarClass')
        self.__db.appendColumnBool(table_name, 'Active')

        table_name = "TeamCars"
        self.__db.appendTable(table_name)
        self.__db.appendColumnUInt(table_name, 'TeamCarClass')
        self.__db.appendColumnUInt(table_name, 'CarSkin')
        self.__db.appendColumnBool(table_name, 'Active')

        table_name = "TeamCarOccupations"
        self.__db.appendTable(table_name)
        self.__db.appendColumnUInt(table_name, 'Member')
        self.__db.appendColumnUInt(table_name, 'Car')
        self.__db.appendColumnBool(table_name, 'Active')
