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

        # save BOP to save it into DB for Lap data
        self.__ballast_current = 0
        self.__restrictor_current = 0

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

        # read preserved GUIDs
        self.__preserved_drivers_guids = []  #Steam64GUIDs that are defined in the entry list
        for guid in entry_dict['GUID'].split(";"):
            if guid != "":
                self.__preserved_drivers_guids.append(guid.strip())

        self.__verbosity.print("Car entry: Id =", self.__id, ", model =", self.__car_model, ", GUIDs =", ",".join(self.__preserved_drivers_guids))

        # remember team-cars
        self.__team_car_id = 0
        if 'TeamCarId' in entry_dict:
           self.__team_car_id = int(entry_dict['TeamCarId'])

        # It happened that completed_lap() was called, but no driver was connected.
        # In this case, count all completed laps to inform later
        self.__missed_completed_laps = 0




    @property
    def Id(self):
        """! CarId of assetto corsa server entry list
        """
        return self.__id


    @property
    def BallastCurrent(self):
        return self.__ballast_current


    @BallastCurrent.setter
    def BallastCurrent(self, value):
        value = int(value)
        if value != self.__ballast_current:
            self.__verbosity.print("Changing ballast on Car ID%s for driver %s (%s) from %ikg to %ikg" % (self.Id, str(self.DriverGuid), str(self.DriverName), self.__ballast_current, value))
        self.__ballast_current = value


    @property
    def Model(self):
        return self.__car_model


    @property
    def RestrictorCurrent(self):
        return self.__restrictor_current


    @RestrictorCurrent.setter
    def RestrictorCurrent(self, value):
        value = int(value)
        if value != self.__restrictor_current:
            self.__verbosity.print("Changing restrictor on Car ID%s for driver %s (%s) from %i%% to %i%%" % (self.Id, str(self.DriverGuid), str(self.DriverName), self.__restrictor_current, value))
        self.__restrictor_current = value


    @property
    def Skin(self):
        return self.__car_skin

    @property
    def SkinId(self):
        return self.__car_skin_id


    @property
    def TeamCarId(self):
        return self.__team_car_id


    @property
    def DriverId(self):
        """! Database User table Id
        """
        return self.__driver_id

    @property
    def DriverGuid(self):
        """! Steam64GUID of the current dirver
        """
        return self.__driver_guid

    @property
    def DriverName(self):
        """! Name of the current driver
        """
        return self.__driver_name

    #@property
    #def PreservedGuids(self):
        #"""! List of Steam64GUID for which this entry is preserved for
        #"""
        #return self.__preserved_drivers_guids

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


    def illegalOccupation(self):
        """! @return True If the current occupation violates the preserved GUIDs
        """
        if self.DriverGuid is None or len(self.__preserved_drivers_guids) == 0:
            return False

        return self.DriverGuid not in self.__preserved_drivers_guids


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
        else:
            self.__driver_id = res[0]['Id']
            self.__db.updateRow("Users", self.__driver_id, {"Name": self.__driver_name,
                                                            "CurrentSession": session.Id})

        self.__verbosity.print("Car", self.__id,
                               "occupied by driver: Id =", self.__driver_id,
                               ", name =", self.__driver_name,
                               ", guid =", self.__driver_guid)

        # Inform about possible missed completed_lap() calls
        if self.__missed_completed_laps > 0:
            columns = {'Session': session.Id,
                       'Cause': "ACswui-Plugin detected possible lost laps",
                       'TeamCar': self.__team_car_id,
                       'User': self.__driver_id if self.__team_car_id == 0 else 0,
                       'PenLaps': self.__missed_completed_laps}
            self.__db.insertRow("SessionPenalties", columns)
            self.__missed_completed_laps = 0


    def release(self):

        if self.__driver_id is None:
            self.__verbosity.print("AC-ERROR at releasing car", self.Id, "because driver is None")

        else:
            self.__verbosity2.print("Car", self.__id,
                                    "released from driver: Id =", self.__driver_id,
                                    ", name =", self.__driver_name)

            # inform that driver is not online anymore
            self.__db.updateRow("Users", self.__driver_id, {"CurrentSession": 0})

        self.__driver_guid = None
        self.__driver_name = None
        self.__driver_id = None
        self.__restrictor_current = 0
        self.__ballast_current = 0


    def complete_lap(self, session, laptime, cuts, grip):

        # sanity check
        if self.__driver_id is None:

            # This actually happened:
            # Driver has disconnect and re-connected before race start (during wait time)
            # Disconnect of driver was reported, but new connection was not reported.
            # Then the race has started and this code was reached after Driver has completed his first lap.

            # No other idea how to catch this situation, than by binning the lap
            self.__verbosity.print("AC-ERROR: complete_lap() called for Car-Id %i, but DriverId is None!\n" % self.Id)
            self.__missed_completed_laps += 1
            return

        # heavy log flooding
        #self.__verbosity2.print("Car", self.__id,
                               #" (" + self.__driver_name + ") completed lap with",
                               #cuts, "cuts after", laptime, "ms")

        fields = {}
        fields['Session'] = session.Id
        fields['CarSkin'] = self.__car_skin_id
        fields['User'] = self.__driver_id
        fields['LapTime'] = laptime
        fields['Cuts'] = cuts
        fields['Grip'] = grip
        fields['Ballast'] = self.__ballast_current
        fields['Restrictor'] = self.__restrictor_current
        fields['TeamCar'] = self.__team_car_id
        self.__db.insertRow("Laps", fields)

        self.__last_lap_time = laptime
        self.__last_lap_cuts = cuts
        self.__last_lap_grip = grip



    def collision(self, session, speed, other_car_entry=None):

        # sanity check
        if self.__driver_id is None:
            self.__verbosity.print("AC-ERROR: collision() called for Car-Id %i, but DriverId is None!\n" % self.Id)
            return

        # collision with environment
        if other_car_entry is None:
            #self.__verbosity2.print("Collision with environment of car ", self.__id,
                                    #" (" + self.__driver_name + ") at speed", speed)

            fields = {}
            fields['Session'] = session.Id
            fields['CarSkin'] = self.__car_skin_id
            fields['User'] = self.__driver_id
            fields['TeamCar'] = self.TeamCarId
            fields['Speed'] = speed
            self.__db.insertRow("CollisionEnv", fields)


        # catch situation where AC inform about a collision
        # but the driver has disconnected before
        # (this has happened: two seconds after a driver has disconnected, AC informed about a crash with this driver :-(
        elif other_car_entry.DriverId is None:
            pass

        # collision with other car
        else:
            #self.__verbosity2.print("Collision of car ", self.__id,
                                    #" (" + self.__driver_name + ")",
                                    #"with other car ", other_car_entry.__id,
                                    #" (" + str(other_car_entry.__driver_name) + ")",
                                    #"at speed", speed)

            fields = {}
            fields['Session'] = session.Id
            fields['CarSkin'] = self.__car_skin_id
            fields['User'] = self.__driver_id
            fields['Speed'] = speed
            fields['OtherUser'] = other_car_entry.DriverId
            fields['OtherCarSkin'] = other_car_entry.SkinId
            fields['TeamCar'] = self.TeamCarId
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
