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
        query = "SELECT Tracks.Id FROM `Tracks` INNER JOIN TrackLocations ON Tracks.Location = TrackLocations.Id WHERE Tracks.Config = '%s' AND TrackLocations.Track = '%s';" % (track_config, track_name)
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
        else:
            self.__db.updateRow("Sessions", self._db_id, self.__db_field_cache)
        self.__verbosity.print("Update Session: Id =", self._db_id, ", name =", session_name)
