from .verbosity import Verbosity
from .database import Database
from .udp_packet import UdpPacket

class UdpPluginSession(object):

    def __init__(self, database, packet, verbosity=0):

        # sanity check
        if not isinstance(database, Database):
            raise TypeError("Parameter 'database' must be a Database object!")
        if not isinstance(packet, UdpPacket):
            raise TypeError("Parameter 'packet' must be a UdpPacket object!")

        self.__db = database
        self.__db_field_cache = {}
        self.__db_id = None
        self.__verbosity = Verbosity(verbosity)

        # calling this update creates a new session
        self.update(packet)



    @property
    def Id(self):
        if self.__db_id is None:
            self.__save_cache()
        return self.__db_id



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
        res = self.__db.fetch("Tracks", ["Id"], {'Track':track_name, 'Config':track_config})
        if len(res) > 0:
            track_id = res[0]['Id']
        else:
            fields = {}
            fields['Track'] = track_name
            fields['Config'] = track_config
            fields['Name'] = ""
            fields['Length'] = 0
            fields['Pitboxes'] = 0
            track_id = self.__database.insertRow("Tracks", fields)

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

        # immediately save cache when existing session is updated
        # If new session, inform that cache needs to be saved
        if not is_new_session:
            self.__save_cache()
        else:
            self.__db_id = None
            self.__verbosity.print("New Session: Id =", self.__db_id, ", name =", session_name)



    def __save_cache(self):
        if self.__db_id is None:
            self.__db_id = self.__db.insertRow("Sessions", self.__db_field_cache)

        else:
            self.__database.updateRow("Sessions", self.__db_id, self.__db_field_cache)
