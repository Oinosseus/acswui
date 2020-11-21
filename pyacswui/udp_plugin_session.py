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
        self.__verbosity = Verbosity(verbosity)

        # status vars to identify session
        self.__id = None
        self.__track_id = None
        self.__session_index = None
        self.__current_session_index = None
        self.__session_type = None

        # calling this update creates a new session
        self.update(packet)


    @property
    def Id(self):
        return self.__id


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

        # check wether to create a new session
        create_new_session = False
        create_new_session |= self.__id is None
        create_new_session |= self.__track_id != track_id
        create_new_session |= self.__session_index != session_index
        create_new_session |= self.__current_session_index != current_session_index
        create_new_session |= self.__session_type != session_type

        # save session identification status vars
        self.__track_id = track_id
        self.__session_index = session_index
        self.__current_session_index = current_session_index
        self.__session_type = session_type

        # setup database fields
        fields = {}
        fields['ProtocolVersion'] = protocol_version
        fields['SessionIndex'] = self.__session_index
        fields['CurrentSessionIndex'] = self.__current_session_index
        fields['SessionCount'] = session_count
        fields['ServerName'] = server_name
        fields['Track'] = self.__track_id
        fields['Name'] = session_name
        fields['Type'] = self.__session_type
        fields['Time'] = session_time
        fields['Laps'] = session_laps
        fields['WaitTime'] = session_waittime
        fields['TempAmb'] = temp_amb
        fields['TempRoad'] = temp_road
        fields['WheatherGraphics'] = weather_graphics
        fields['Elapsed'] = elapsed_ms

        # update database
        if create_new_session:
            self.__id = self.__db.insertRow("Sessions", fields)
            self.__verbosity.print("New Session: Id =", self.__id, ", name =", session_name)

        else:
            self.__database.updateRow("Sessions", self.__id, fields)

