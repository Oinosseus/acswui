import socket
from .verbose_class import VerboseClass
from .udp_packet import UdpPacket

class UdpServer(VerboseClass):

    def __init__(self, address, read_port):
        VerboseClass.__init__(self)

        self.__sock = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
        self.__sock.bind((address, read_port))
        self.__sock.settimeout(0.1)
        print("HERE", self.__sock)


    def process(self):

        try:
            data, addr = self.__sock.recvfrom(2**12)
            pkt = UdpPacket(data, addr)
        except socket.timeout:
            pkt = None


        if pkt is not None:
            self.parse_packet(pkt)



    def parse_packet(self, pkt):

        prot = pkt.readByte()
        self.print(1, "UDP receive packet ", prot, "on address", pkt.Addr)


        if prot == 50 or prot == 59:

            version = pkt.readByte()
            sess_index = pkt.readByte()
            current_session_index = pkt.readByte()
            session_count = pkt.readByte()

            if prot == 50:
                self.print(2, "ACSP_NEW_SESSION")
            else:
                self.print(2, "ACSP_SESSION_INFO")

            self.print(3, "version:", version)
            self.print(3, "sess_index:", sess_index)
            self.print(3, "current_session_index:", current_session_index)
            self.print(3, "session_count:", session_count)

            server_name = pkt.readStringW()
            track = pkt.readString()
            track_config = pkt.readString()
            session_name = pkt.readString()
            session_type = pkt.readByte()
            session_time = pkt.readUint16()
            session_laps = pkt.readUint16()
            session_waittime = pkt.readUint16()
            temp_amb = pkt.readByte()
            temp_road = pkt.readByte()
            weather_graphics = pkt.readString()
            elapsed_ms = pkt.readInt32()
            self.print(3, "server name:", server_name)
            self.print(3, "track / config:", track, "/", track_config)
            self.print(3, "session name:", session_name)
            self.print(3, "session type:", session_type)
            self.print(3, "session time:", session_time)
            self.print(3, "session laps:", session_laps)
            self.print(3, "session wait time:", session_waittime)
            self.print(3, "temp amb / road:", temp_amb, "/", temp_road)
            self.print(3, "weather graphics", weather_graphics)
            self.print(3, "elapsed ms", elapsed_ms)

            if prot == 50:
                pass


        elif prot == 51 or prot == 52:

            if prot == 51:
                self.print(2, "ACSP_NEW_CONNECTION")
            else:
                self.print(2, "ACSP_CONNECTION_CLOSED")

            driver_name = pkt.readStringW()
            driver_guid = pkt.readStringW()
            car_id = pkt.readByte()
            car_model = pkt.readString()
            car_skin = pkt.readString()

            self.print(3, "driver_name:", driver_name)
            self.print(3, "driver_guid:", driver_guid)
            self.print(3, "car_id:", car_id)
            self.print(3, "car_model:", car_model)
            self.print(3, "car_skin:", car_skin)


        elif prot == 53:
            print("ACSP_CAR_UPDATE")

        elif prot == 54:
            print("ACSP_CAR_INFO")

        elif prot == 55:
            print("ACSP_END_SESSION")

        elif prot == 73:
            self.print(2, "ACSP_LAP_COMPLETED")
            car_id = pkt.readByte()
            laptime = pkt.readUint32()
            cuts = pkt.readByte()
            self.print(3, "car_id:", car_id)
            self.print(3, "laptime:", laptime)
            self.print(3, "cuts:", cuts)

            cars_count = pkt.readByte()
            for i in range(cars_count):
                rcar_id = pkt.readByte()
                rtime = pkt.readUint32()
                rlaps = pkt.readUint16()
                has_completed_flag = pkt.readByte()
                self.print(3, "rcar_id:", rcar_id)
                self.print(4, "rtime:", rtime)
                self.print(4, "rlaps:", rlaps)
                self.print(4, "has_completed_flag:", has_completed_flag)

            grip = pkt.readSingle()
            self.print(3, "grip level:", grip)


        elif prot == 56:
            version = pkt.readByte()
            print("ACSP_VERSION:", version)


        elif prot == 60:
            err_str = pkt.readStringW()
            self.print(2, "ACSP_ERROR", err_str)


        elif prot == 130:
            self.print(2, "ACSP_CLIENT_EVENT")
            ev_type = pkt.readByte()
            car_id = pkt.readByte()
            other_car_id = None

            if ev_type == 10: # collision with car
                other_car_id = pkt.readByte()

            speed = pkt.readSingle()
            world_pos = pkt.readVector3f()
            rel_pos = pkt.readVector3f()

            if ev_type == 10: # collision with car
                self.print(3, "Collision with other car")
            elif ev_type == 11: # collision with environment
                self.print(3, "Collision with environment")
            else:
                self.print(3, "undefined event")

            self.print(4, "car_id:", car_id)
            self.print(4, "other_car_id:", other_car_id)
            self.print(4, "speed:", speed)
            self.print(4, "world_pos:", world_pos)
            self.print(4, "rel_pos:", rel_pos)


        elif prot == 57:
            print("ACSP_CHAT")
        elif prot == 58:
            print("ACSP_CLIENT_LOADED")
        else:
            print("UNKNOWN PACKET", prot)
