import socket
from .verbose_class import VerboseClass
from .udp_packet import UdpPacket


class DriverConnection(object):

    def __init__(self, database,
                 driver_name, driver_guid,
                 car_id, car_model, car_skin):

        self.__car_id = car_id

        # ----------------
        # Clarify CarSkin

        car_id = None
        res = database.fetch("Cars", "Id", {'Car':car_model})
        if len(res) == 0:
            car_id = database.insertRow("Cars", {'Car':car_model, 'Name':"", 'Parent':0, 'Brand':""})
        elif len(res) == 1:
            car_id = res[0]['Id']
        else:
            print("car_model =", car_model, ", car_skin =", car_skin)
            raise ValueError("Database table 'Cars' is ambigous!")

        car_skin_id = None
        res = database.fetch("CarSkins", "Id", {'Car':car_id, 'Skin':car_skin})
        if len(res) == 0:
            car_skin_id = database.insertRow("CarSkins", {'Car':car_id, 'Skin':car_skin})
        elif len(res) == 1:
            car_skin_id = res[0]['Id']
        else:
            print("car_model =", car_model, ", car_skin =", car_skin)
            raise ValueError("Database table 'CarSkins' is ambigous")
        self.__car_skin_id = car_skin_id


        # -------------
        # Clarify User
        user_id = None
        res = database.fetch("Users", "Id", {'Steam64GUID': driver_guid})
        if len(res) == 0:
            user_id = database.insertRow("Users", {'Login':driver_name, 'Steam64GUID':driver_guid, 'Password':""})
        elif len(res) == 1:
            user_id = res[0]['Id']
            database.updateRow("Users", user_id, {"Login": driver_name})
        else:
            print("driver_guid =", driver_guid)
            raise ValueError("Database table 'Users' is ambigous")
        self.__user_id = user_id


    @property
    def CarId(self):
        return self.__car_id

    @property
    def CarSkinId(self):
        return self.__car_skin_id

    @property
    def UserId(self):
        return self.__user_id



class UdpPluginServer(VerboseClass):

    def __init__(self, address, read_port, db_wrapper):
        VerboseClass.__init__(self)

        self.__database = db_wrapper
        self.__session_db_id = None
        self.__session_track_id = None
        self.__driver_connections = []

        # bind UDP socket
        self.__sock = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
        self.__sock.bind((address, read_port))
        self.__sock.settimeout(0.1)



    def process(self):

        try:
            data, addr = self.__sock.recvfrom(2**12)
            pkt = UdpPacket(data, addr)
            self.parse_packet(pkt)
        except socket.timeout:
            pass



    def parse_packet(self, pkt):

        prot = pkt.readByte()
        self.print(1, "UDP receive packet ", prot, "on address", pkt.Addr)


        if prot == 50 or prot == 59:

            version = pkt.readByte()
            sess_index = pkt.readByte()
            current_session_index = pkt.readByte()
            session_count = pkt.readByte()

            if prot == 50:
                self.print(1 ,"ACSP_NEW_SESSION")
            else:
                self.print(1, "ACSP_SESSION_INFO")

            self.print(2, "version:", version)
            self.print(2, "sess_index:", sess_index)
            self.print(2, "current_session_index:", current_session_index)
            self.print(2, "session_count:", session_count)

            server_name = pkt.readStringW()
            track_name = pkt.readString()
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
            self.print(2, "server name:", server_name)
            self.print(2, "track name / config:", track_name, "/", track_config)
            self.print(2, "session name:", session_name)
            self.print(2, "session type:", session_type)
            self.print(2, "session time:", session_time)
            self.print(2, "session laps:", session_laps)
            self.print(2, "session wait time:", session_waittime)
            self.print(2, "temp amb / road:", temp_amb, "/", temp_road)
            self.print(2, "weather graphics", weather_graphics)
            self.print(2, "elapsed ms", elapsed_ms)

            self.update_session(version, sess_index, current_session_index, session_count,
                                server_name, track_name, track_config,
                                session_name, session_type, session_time, session_laps, session_waittime,
                                temp_amb, temp_road, weather_graphics, elapsed_ms)


        elif prot == 51 or prot == 52:

            driver_name = pkt.readStringW()
            driver_guid = pkt.readStringW()
            car_id = pkt.readByte()
            car_model = pkt.readString()
            car_skin = pkt.readString()

            self.print(2, "driver_name:", driver_name)
            self.print(2, "driver_guid:", driver_guid)
            self.print(2, "car_id:", car_id)
            self.print(2, "car_model:", car_model)
            self.print(2, "car_skin:", car_skin)

            if prot == 51:
                self.print(1, "ACSP_NEW_CONNECTION")
                self.connection_new(driver_name, driver_guid,
                                    car_id, car_model, car_skin)
            else:
                self.print(1, "ACSP_CONNECTION_CLOSED")
                self.connection_delete(car_id)


        elif prot == 53:
            print("ACSP_CAR_UPDATE")

        elif prot == 54:
            print("ACSP_CAR_INFO")

        elif prot == 55:
            print("ACSP_END_SESSION")

        elif prot == 73:
            self.print(1, "ACSP_LAP_COMPLETED")
            car_id = pkt.readByte()
            laptime = pkt.readUint32()
            cuts = pkt.readByte()
            self.print(2, "car_id:", car_id)
            self.print(2, "laptime:", laptime)
            self.print(2, "cuts:", cuts)

            cars_count = pkt.readByte()
            for i in range(cars_count):
                rcar_id = pkt.readByte()
                rtime = pkt.readUint32()
                rlaps = pkt.readUint16()
                has_completed_flag = pkt.readByte()
                self.print(2, "rcar_id: ", rcar_id, ", rtime: ", rtime, ", rlaps: ", rlaps, ", has_completed_flag: ", has_completed_flag, sep="")

            grip = pkt.readSingle()
            self.print(2, "grip level:", grip)

            self.complete_lap(car_id, laptime, cuts, grip)


        elif prot == 56:
            version = pkt.readByte()
            print("ACSP_VERSION:", version)


        elif prot == 60:
            err_str = pkt.readStringW()
            self.print(1, "ACSP_ERROR", err_str)


        elif prot == 130:
            self.print(1, "ACSP_CLIENT_EVENT")
            ev_type = pkt.readByte()
            car_id = pkt.readByte()
            other_car_id = None

            if ev_type == 10: # collision with car
                other_car_id = pkt.readByte()

            speed = pkt.readSingle()
            world_pos = pkt.readVector3f()
            rel_pos = pkt.readVector3f()

            if ev_type == 10: # collision with car
                self.print(2, "Collision with other car")
                self.collision_car(car_id, speed, other_car_id)
            elif ev_type == 11: # collision with environment
                self.print(2, "Collision with environment")
                self.collision_env(car_id, speed)
            else:
                self.print(2, "undefined event")

            self.print(3, "car_id:", car_id)
            self.print(3, "other_car_id:", other_car_id)
            self.print(3, "speed:", speed)
            self.print(3, "world_pos:", world_pos)
            self.print(3, "rel_pos:", rel_pos)


        elif prot == 57:
            print("ACSP_CHAT")
        elif prot == 58:
            print("ACSP_CLIENT_LOADED")
        else:
            print("UNKNOWN PACKET", prot)



    def get_driver_connection(self, car_id):
        for dc in self.__driver_connections:
            if dc.CarId == car_id:
                return dc
        return None




    def connection_new(self, driver_name, driver_guid,
                             car_id, car_model, car_skin):

        # check if ambigous
        for dc in self.__driver_connections:
            if dc.CarId == car_id:
                raise ValueError("CarId=%i is already connected!" % car_id)

        # new connection
        dc = DriverConnection(self.__database,
                              driver_name, driver_guid,
                              car_id, car_model, car_skin)

        # save connection
        self.__driver_connections.append(dc)



    def connection_delete(self, car_id):

        while True:

            pop_index = None
            for idx in range(len(self.__driver_connections)):
                dc = self.__driver_connections[idx]
                if dc.CarId == car_id:
                    pop_index = idx
                    break

            if pop_index is not None:
                self.__driver_connections.pop(pop_index)
            else:
                break



    def complete_lap(self, car_id, laptime, cuts, grip):
        dc = self.get_driver_connection(car_id)
        fields = {}
        fields['Session'] = self.__session_db_id
        fields['CarSkin'] = dc.CarSkinId
        fields['User'] = dc.UserId
        fields['LapTime'] = laptime
        fields['Cuts'] = cuts
        fields['Grip'] = grip
        self.__database.insertRow("Laps", fields)



    def collision_env(self, car_id, speed):
        dc = self.get_driver_connection(car_id)
        fields = {}
        fields['Session'] = self.__session_db_id
        fields['CarSkin'] = dc.CarSkinId
        fields['User'] = dc.UserId
        fields['Speed'] = speed
        self.__database.insertRow("CollisionEnv", fields)



    def collision_car(self, car_id, speed, other_car_id):
        dc = self.get_driver_connection(car_id)
        other_dc = self.get_driver_connection(other_car_id)
        fields = {}
        fields['Session'] = self.__session_db_id
        fields['CarSkin'] = dc.CarSkinId
        fields['User'] = dc.UserId
        fields['Speed'] = speed
        fields['OtherUser'] = other_dc.UserId
        fields['OtherCarSkin'] = other_dc.CarSkinId
        self.__database.insertRow("CollisionCar", fields)



    def update_session(self, protocol_version,
                             session_index,
                             current_session_index,
                             session_count,
                             server_name,
                             track_name,
                             track_config,
                             session_name,
                             session_type,
                             session_time,
                             session_laps,
                             session_waittime,
                             temp_amb,
                             temp_road,
                             wheater_graphics,
                             elapsed):


        # ---------
        # Track Id

        res = self.__database.fetch("Tracks", ["Id"], {'Track':track_name, 'Config':track_config})
        if len(res) > 0:
            self.__session_track_id = res[0]['Id']
        else:
            fields = {}
            fields['Track'] = track_name
            fields['Config'] = track_config
            fields['Name'] = ""
            fields['Length'] = 0
            fields['Pitboxes'] = 0
            self.__session_track_id = self.__database.insertRow("Tracks", fields)


        # -----------
        # Session Id

        if self.__session_db_id == None:
            new_session = True

        else:

            # check if databse contains this session
            where_dict = {}
            where_dict['ProtocolVersion'] = protocol_version
            where_dict['SessionIndex'] = session_index
            where_dict['CurrentSessionIndex'] = current_session_index
            where_dict['SessionCount'] = session_count
            where_dict['ServerName'] = server_name
            where_dict['Track'] = self.__session_track_id
            where_dict['Name'] = session_name
            where_dict['Type'] = session_type

            res = self.__database.fetch("Sessions", ["Id"], where_dict, "Id", False)
            if len(res) > 0:
                if res[0]['Id'] == self.__session_db_id:
                    new_session = False
                else:
                    new_session = True
            else:
                new_session = True


        # ---------------------
        # Update Session Table

        fields = {}
        fields['ProtocolVersion'] = protocol_version
        fields['SessionIndex'] = session_index
        fields['CurrentSessionIndex'] = current_session_index
        fields['SessionCount'] = session_count
        fields['ServerName'] = server_name
        fields['Track'] = self.__session_track_id
        fields['Name'] = session_name
        fields['Type'] = session_type
        fields['Time'] = session_time
        fields['Laps'] = session_laps
        fields['WaitTime'] = session_waittime
        fields['TempAmb'] = temp_amb
        fields['TempRoad'] = temp_road
        fields['WheatherGraphics'] = wheater_graphics
        fields['Elapsed'] = elapsed

        if new_session:
            self.__session_db_id = self.__database.insertRow("Sessions", fields)
            self.print(1, "New Session in Database:", fields)
        else:
            self.__database.updateRow("Sessions", self.__session_db_id, fields)
