from .verbosity import Verbosity
from .database import Database
from .udp_packet import UdpPacket


class UdpPluginCarEntry(object):

    def __init__(self, database, entry_id, entry_dict, verbosity=0):

        # sanity check
        if not isinstance(database, Database):
            raise TypeError("Parameter 'database' must be a Database object!")
        if str(entry_id)[:4] != "CAR_":
            raise ValueError("Unexpected entry_id: '%s'" % str(entry_id))

        self.__db = database
        self.__verbosity = Verbosity(verbosity, self.__class__.__name__)
        self.__verbosity2 = Verbosity(self.__verbosity)

        # state vars
        self.__id = int(entry_id[4:])
        self.__carskin_id = None
        self.__driver_id = None
        self.__driver_name = None
        self.__driver_guid = None

        # car realtime data
        self.__rtd_position =  [0.0, 0.0, 0.0]
        self.__rtd_velocity = [0.0, 0.0, 0.0]
        self.__rtd_gear = 1
        self.__rtd_engine_rpm = 0
        self.__rtd_normalized_spline_pos = 0;
        self.__last_lap_time = 0
        self.__last_lap_cuts = 0
        self.__last_lap_grip = 0

        # determine ballast
        self.__ballast = 0
        if 'BALLAST' in entry_dict and entry_dict['BALLAST'] != "":
            self.__ballast = int(entry_dict['BALLAST'])

        # determine restrictor
        self.__restrictor = 0
        if 'RESTRICTOR' in entry_dict and entry_dict['RESTRICTOR'] != "":
            self.__restrictor = int(entry_dict['RESTRICTOR'])

        # identify car
        self.__car_model = entry_dict['MODEL']
        self.__car_id = None
        res = self.__db.fetch("Cars", "Id", {'Car':self.__car_model})
        if len(res) == 0:
            self.__car_id = self.__db.insertRow("Cars", {'Car':self.__car_model, 'Name':"", 'Parent':0, 'Brand':""})
        elif len(res) == 1:
            self.__car_id = res[0]['Id']
        else:
            raise ValueError("Database table 'Cars' is ambigous!")

        # identify car skin
        self.__car_skin = entry_dict['SKIN']
        self.__car_skin_id = None
        res = self.__db.fetch("CarSkins", "Id", {'Car':self.__car_id, 'Skin':self.__car_skin})
        if len(res) == 0:
            self.__car_skin_id = self.__db.insertRow("CarSkins", {'Car':self.__car_id, 'Skin':self.__car_skin})
        elif len(res) == 1:
            self.__car_skin_id = res[0]['Id']
        else:
            raise ValueError("Database table 'CarSkins' is ambigous")

        self.__verbosity.print("Car entry: Id =", self.__id, ", model =", self.__car_model)


    @property
    def Id(self):
        """! CarId of assetto corsa server entry list
        """
        return self.__id


    @property
    def Model(self):
        return self.__car_model


    @property
    def Skin(self):
        return self.__car_skin

    @property
    def SkinId(self):
        return self.__car_skin_id

    @property
    def DriverId(self):
        """! Database User table Id
        """
        return self.__driver_id

    @property
    def DriverGuid(self):
        """! Steam64GUID
        """
        return self.__driver_guid

    @property
    def RealtimeJsonDict(self):
        d = {}
        d['Id'] = self.__id
        d['SkinId'] = self.__car_skin_id
        d['DriverId'] = self.__driver_id
        d['Position'] = self.__rtd_position
        d['Velocity'] = self.__rtd_velocity
        d['Gear'] = self.__rtd_gear
        d['Rpm'] = self.__rtd_engine_rpm
        d['NormalizedSplinePos'] = self.__rtd_normalized_spline_pos
        d['LastLapTime'] = self.__last_lap_time
        d['LastLapCuts'] = self.__last_lap_cuts
        d['LastLapGrip'] = self.__last_lap_grip
        return d


    def occupy(self, driver_name, driver_guid, session):
        """ Incomming driver connection
            ... occupy a seat in this car entry
        """

        # determine user
        self.__driver_guid = driver_guid
        self.__driver_name = driver_name
        self.__driver_id = None
        res = self.__db.fetch("Users", "Id", {'Steam64GUID': self.__driver_guid})
        if len(res) == 0:
            self.__driver_id = self.__db.insertRow("Users", {'Name': self.__driver_name,
                                                             'Steam64GUID': driver_guid,
                                                             'Password': "",
                                                             'CurrentSession': session.Id})
        elif len(res) == 1:
            self.__driver_id = res[0]['Id']
            self.__db.updateRow("Users", self.__driver_id, {"Name": self.__driver_name,
                                                            "CurrentSession": session.Id})
        else:
            raise ValueError("Database table 'Users' is ambigous")

        self.__verbosity.print("Car", self.__id,
                               "occupied by driver: Id =", self.__driver_id,
                               ", name =", self.__driver_name,
                               ", guid =", self.__driver_guid)


    def release(self):
        self.__verbosity2.print("Car", self.__id,
                                "released from driver: Id =", self.__driver_id,
                                ", name =", self.__driver_name)

        # inform that driver is not online anymore
        self.__db.updateRow("Users", self.__driver_id, {"CurrentSession": 0})


        self.__driver_guid = None
        self.__driver_name = None
        self.__driver_id = None


    def complete_lap(self, session, laptime, cuts, grip):

        # sanity check
        if self.__driver_id is None:
            raise ValueError("Cannot report lap, because no driver is connected to this car!")

        self.__verbosity2.print("Car", self.__id,
                               " (" + self.__driver_name + ") completed lap with",
                               cuts, "cuts after", laptime, "ms")

        fields = {}
        fields['Session'] = session.Id
        fields['CarSkin'] = self.__car_skin_id
        fields['User'] = self.__driver_id
        fields['LapTime'] = laptime
        fields['Cuts'] = cuts
        fields['Grip'] = grip
        fields['Ballast'] = self.__ballast
        fields['Restrictor'] = self.__restrictor
        self.__db.insertRow("Laps", fields)

        self.__last_lap_time = laptime
        self.__last_lap_cuts = cuts
        self.__last_lap_grip = grip



    def collision(self, session, speed, other_car_entry=None):

        # sanity check
        if self.__driver_id is None:
            raise ValueError("Cannot report lap, because no driver is connected to this car!")

        # collision with environment
        if other_car_entry is None:
            self.__verbosity2.print("Collision with environment of car ", self.__id,
                                    " (" + self.__driver_name + ") at speed", speed)

            fields = {}
            fields['Session'] = session.Id
            fields['CarSkin'] = self.__car_skin_id
            fields['User'] = self.__driver_id
            fields['Speed'] = speed
            self.__db.insertRow("CollisionEnv", fields)

        # collision with other car
        else:
            self.__verbosity2.print("Collision of car ", self.__id,
                                    " (" + self.__driver_name + ")",
                                    "with other car ", other_car_entry.__id,
                                    " (" + str(other_car_entry.__driver_name) + ")",
                                    "at speed", speed)

            fields = {}
            fields['Session'] = session.Id
            fields['CarSkin'] = self.__car_skin_id
            fields['User'] = self.__driver_id
            fields['Speed'] = speed
            fields['OtherUser'] = other_car_entry.DriverId
            fields['OtherCarSkin'] = other_car_entry.SkinId
            self.__db.insertRow("CollisionCar", fields)


    def realtime_update(self,
                   position,
                   velocity,
                   gear,
                   engine_rpm,
                   normalized_spline_pos):

        self.__rtd_position = position
        self.__rtd_velocity = velocity
        self.__rtd_gear = gear
        self.__rtd_engine_rpm = engine_rpm
        self.__rtd_normalized_spline_pos = normalized_spline_pos;
