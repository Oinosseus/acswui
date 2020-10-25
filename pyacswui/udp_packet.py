import struct

class UdpPacket(object):

    def __init__(self, data, addr):
        self.__data = data
        self.__index = 0
        self.__addr = addr


    @property
    def Addr(self):
        return self.__addr


    def readByte(self):
        data = self.__data[self.__index]
        self.__index += 1
        return data


    def readUint16(self):
        uint16 = 0
        uint16 |= self.readByte() << 8
        uint16 |= self.readByte() << 0
        return uint16


    def readUint32(self):
        uint32 = 0
        uint32 |= self.readByte() << 24
        uint32 |= self.readByte() << 16
        uint32 |= self.readByte() << 8
        uint32 |= self.readByte() << 0
        return uint32


    def readInt32(self):
        int32 = self.readUint32()
        if int32 >= 2**31:
            int32 -= 2**32
        return int32


    def readSingle(self):
        b3 = self.readByte()
        b2 = self.readByte()
        b1 = self.readByte()
        b0 = self.readByte()
        single = struct.unpack("!f", bytes([b0, b1, b2, b3]))
        return single


    def readVector3f(self):
        x = self.readSingle()
        y = self.readSingle()
        z = self.readSingle()
        return x, y, z


    def readString(self):
        length = self.__data[self.__index]
        string_start = self.__index + 1
        string_end = string_start + length
        string = self.__data[string_start: string_end]
        self.__index = self.__index + 1 + length
        return string.decode("utf-8")


    def readStringW(self):
        length = self.__data[self.__index]
        length *= 4
        string_start = self.__index + 1
        string_end = string_start + length
        string = self.__data[string_start: string_end]
        self.__index = self.__index + 1 + length
        return string.decode("utf-32")
