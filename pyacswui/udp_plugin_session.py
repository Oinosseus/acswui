from .verbosity import Verbosity
from .database import Database
from .udp_packet import UdpPacket
import json

class UdpPluginSession(object):

    def __init__(self, server_slot, server_preset, car_class,
                 database, packet, predecessor=None,
                 referenced_session_schedule_id = None,
                 verbosity=0):

        # sanity check
        if not isinstance(database, Database):
            raise TypeError("Parameter 'database' must be a Database object!")
        if not isinstance(packet, UdpPacket):
            raise TypeError("Parameter 'packet' must be a UdpPacket object!")

        self.__server_slot = int(server_slot)
        self.__server_preset = int(server_preset)
        self.__car_class = int(car_class)
        self.__db = database
        self.__db_field_cache = {}
        self._db_id = None
        self.__db_id_predecessor = 0
        if predecessor is not None and predecessor._db_id is not None:
            self.__db_id_predecessor = predecessor._db_id
        self.__referenced_session_schedule_id = referenced_session_schedule_id
        self.__verbosity = Verbosity(verbosity, self.__class__.__name__)

        # calling this update creates a new session
        self.update(packet)



    @property
    def Id(self):
        return self._db_id


    @property
    def IsActive(self):
        return False if self._db_id is None else True



    def update(self, packet):

        # sanity check
        if not isinstance(packet, UdpPacket):
            raise TypeError("Parameter 'packet' must be a UdpPacket object!")
        if packet.Index != 1:
            raise ValueError("It is assumed, that exactely only byte no. 1 is read from the packet!")

        # reack packet
        protocol_version = packet.readByte()
        session_index = packet.readByte()
        current_session_index = packet.readByte()
        session_count = packet.readByte()
        server_name = packet.readStringW()
        track_name = packet.readString()
        track_config = packet.readString()
        session_name = packet.readString()
        session_type = packet.readByte()
        session_time = packet.readUint16()
        session_laps = packet.readUint16()
        session_waittime = packet.readUint16()
        temp_amb = packet.readByte()
        temp_road = packet.readByte()
        weather_graphics = packet.readString()
        elapsed_ms = packet.readInt32()

        # identify track
        query = "SELECT Tracks.Id FROM `Tracks` INNER JOIN TrackLocations ON Tracks.Location = TrackLocations.Id WHERE Tracks.Config = '%s' AND TrackLocations.Track = '%s'" % (track_config, track_name)
        res = self.__db.rawQuery(query, True)
        if len(res) > 0:
            track_id = res[0][0]
        else:
            fields = {}
            fields['Track'] = track_name
            fields['Config'] = track_config
            fields['Name'] = ""
            fields['Length'] = 0
            fields['Pitboxes'] = 0
            track_id = self.__db.insertRow("Tracks", fields)

        # check if update is a new session
        is_new_session = False
        if 'ProtocolVersion' not in self.__db_field_cache or self.__db_field_cache['ProtocolVersion'] != protocol_version:
            is_new_session = True
        if 'SessionIndex' not in self.__db_field_cache or self.__db_field_cache['SessionIndex'] != session_index:
            is_new_session = True
        if 'CurrentSessionIndex' not in self.__db_field_cache or self.__db_field_cache['CurrentSessionIndex'] != current_session_index:
            is_new_session = True
        if 'Track' not in self.__db_field_cache or self.__db_field_cache['Track'] != track_id:
            is_new_session = True
        if 'Type' not in self.__db_field_cache or self.__db_field_cache['Type'] != session_type:
            is_new_session = True

        # setup database fields
        self.__db_field_cache = {}
        self.__db_field_cache['Predecessor'] = self.__db_id_predecessor
        self.__db_field_cache['ProtocolVersion'] = protocol_version
        self.__db_field_cache['SessionIndex'] = session_index
        self.__db_field_cache['CurrentSessionIndex'] = current_session_index
        self.__db_field_cache['SessionCount'] = session_count
        self.__db_field_cache['ServerName'] = server_name
        self.__db_field_cache['Track'] = track_id
        self.__db_field_cache['Name'] = session_name
        self.__db_field_cache['Type'] = session_type
        self.__db_field_cache['Time'] = session_time
        self.__db_field_cache['Laps'] = session_laps
        self.__db_field_cache['WaitTime'] = session_waittime
        self.__db_field_cache['TempAmb'] = temp_amb
        self.__db_field_cache['TempRoad'] = temp_road
        self.__db_field_cache['WheatherGraphics'] = weather_graphics
        self.__db_field_cache['Elapsed'] = elapsed_ms
        self.__db_field_cache['ServerSlot'] = self.__server_slot
        self.__db_field_cache['ServerPreset'] = self.__server_preset
        self.__db_field_cache['CarClass'] = self.__car_class
        if self.__referenced_session_schedule_id is None:
            self.__db_field_cache['SessionSchedule'] = 0
        else:
            self.__db_field_cache['SessionSchedule'] = self.__referenced_session_schedule_id

        # save to db
        if is_new_session:
            self._db_id = self.__db.insertRow("Sessions", self.__db_field_cache)
            self.__verbosity.print("New Session: Id =", self._db_id, ", name =", session_name)
        else:
            self.__db.updateRow("Sessions", self._db_id, self.__db_field_cache)
            # Verbosity(self.__verbosity).print("Session-Id", self._db_id, ", elapsed %0.1fs" % (elapsed_ms * 1e-3))



    def parse_result_json(self, result_json_file_path, entryies):
        """
            @param entryies a list of UdpPluginCarEntry objects
        """

        # ignore, if session is not in database (maybe empty session)
        if self.Id is None:
            self.__verbosity.print("For empty session, ignore result JSON '%s'" % result_json_file_path)
            return
        else:
            self.__verbosity.print("For Session-Id", self.Id, "parsing result JSON '%s'" % result_json_file_path)

        # decode result file
        json_dict = {}
        with open(result_json_file_path, "r") as f:
            json_dict = json.load(f)

        #check validity
        if "Cars" not in json_dict:
            print("ERROR: No 'Cars' found in '%s'!" % result_json_file_path)
            return
        if "Result" not in json_dict:
            print("ERROR: No 'Result' found in '%s'!" % result_json_file_path)
            return

        # ensure to delete all previous results
        query = "DELETE FROM SessionResults WHERE Session = $session_id"
        self.__db.rawQuery("DELETE FROM SessionResults WHERE Session = " + str(self.Id))

        # loop over all result entries
        position = 0
        already_listed_user_ids = []
        for rslt in json_dict['Result']:
            position += 1

            # find user
            steam64guid = rslt['DriverGuid']
            if steam64guid.lower().find("kicked") >= 0:
                continue
            user_ids = self.__db.findIds("Users", {"Steam64GUID": steam64guid})
            if len(user_ids) != 1:
                print("ERROR: No excat match for Steam64GUID '%s'" % steam64guid)
                continue
            user = user_ids[0]

            # find car model
            rslt_car_id = rslt['CarId']
            rslt_car_model = json_dict['Cars'][rslt_car_id]['Model']
            car_ids = self.__db.findIds("Cars", {"Car": rslt_car_model})
            if len(car_ids) != 1:
                print("ERROR: No excat match for car model '%s'" % rslt_car_model)
                continue
            car = car_ids[0]

            # find car skin
            rlst_car_skin = json_dict['Cars'][rslt_car_id]['Skin']
            skin_ids = self.__db.findIds("CarSkins", {"Car": car, "Skin": rlst_car_skin})
            if len(skin_ids) != 1:
                print("ERROR: No excat match for car skin '%s' at car %i" % (rslt_car_model, car))
                continue
            skin = skin_ids[0]

            # find TeamCar
            team_car_id = 0
            for e in entryies:
                if e.Id == rslt_car_id:
                    team_car_id = e.TeamCarId
                    break

            # list single drivers only once
            # but ignore for TeamCars
            if team_car_id < 1:
                if user in already_listed_user_ids:
                    continue
                else:
                    already_listed_user_ids.append(user)

            # stroe result
            fields = {}
            fields['Position'] = position
            fields['Session'] = self.Id
            fields['User'] = user if team_car_id < 1 else 0  # if car was driven by a team, ignore the user
            fields['CarSkin'] = skin
            fields['BestLap'] = rslt['BestLap']
            fields['TotalTime'] = rslt['TotalTime']
            fields['Ballast'] = rslt['BallastKG']
            fields['Restrictor'] = rslt['Restrictor']
            fields['TeamCar'] = team_car_id
            self.__db.insertRow("SessionResults", fields)
