import re
import subprocess

class Version(object):

    def __init__(self, certain_version_string = None):

        self.Major = 0
        self.Minor = 0
        self.Patch = 0
        self.DetailedString = ""

        if certain_version_string is None:
            self.__fromCurrent()
        else:
            self.__fromString(certain_version_string)




    def __fromCurrent(self):
        """
            @return A Version object with current version information
        """
        cmd = ["git", "describe"]
        cmd.append("--long")
        cp = subprocess.run(cmd, capture_output=True, check=True)
        git_describe = cp.stdout.decode("utf-8").strip()
        self.__fromString(git_describe)


    def __fromString(self, version_string):
        """
            @param version_string like "v1.2.3"
            @return a tuple of (major, minor, patch)
        """

        # try vx.y.z
        m = re.match(".*([0-9]+)\.([0-9]+)\.([0-9]+)(.*)", version_string)
        if m:
            self.Major = int(m.group(1))
            self.Minor = int(m.group(2))
            self.Patch = int(m.group(3))
            self.DetailedString  = "%i.%i.%i%s" % (self.Major, self.Minor, self.Patch, m.group(4))

        # try vx.y
        else:
            m = re.match(".*([0-9]+)\.([0-9]+)(.*)", version_string)
            self.Major = int(m.group(1))
            self.Minor = int(m.group(2))
            self.Patch = 0
            self.DetailedString  = "%i.%i.%i%s" % (self.Major, self.Minor, self.Patch, m.group(3))


    def stringCompact(self):
        return "v%i.%i.%i" % (self.Major, self.Minor, self.Patch)


    def stringDetailed(self):
        if len(self.DetailedString):
            return self.DetailedString
        else:
            return self.stringCompact()


    def __lt__(self, other):

        # compare major
        if self.Major < other.Major:
            return True
        elif self.Major > other.Major:
            return False

        # compare minor
        if self.Minor < other.Minor:
            return True
        elif self.Minor > other.Minor:
            return False

        # compare patch
        if self.Patch < other.Patch:
            return True
        elif self.Patch > other.Patch:
            return False

        # equal
        return False


    def __le__(self, other):

        # compare major
        if self.Major < other.Major:
            return True
        elif self.Major > other.Major:
            return False

        # compare minor
        if self.Minor < other.Minor:
            return True
        elif self.Minor > other.Minor:
            return False

        # compare patch
        if self.Patch < other.Patch:
            return True
        elif self.Patch > other.Patch:
            return False

        # equal
        return True


    def __gt__(self, other):

        # compare major
        if self.Major > other.Major:
            return True
        elif self.Major < other.Major:
            return False

        # compare minor
        if self.Minor > other.Minor:
            return True
        elif self.Minor < other.Minor:
            return False

        # compare patch
        if self.Patch > other.Patch:
            return True
        elif self.Patch < other.Patch:
            return False

        # equal
        return False


    def __ge__(self, other):

        # compare major
        if self.Major > other.Major:
            return True
        elif self.Major < other.Major:
            return False

        # compare minor
        if self.Minor > other.Minor:
            return True
        elif self.Minor < other.Minor:
            return False

        # compare patch
        if self.Patch > other.Patch:
            return True
        elif self.Patch < other.Patch:
            return False

        # equal
        return True


    def __eq__(self, other):

        # compare major
        if self.Major != other.Major:
            return False

        # compare minor
        if self.Minor != other.Minor:
            return False

        # compare patch
        if self.Patch != other.Patch:
            return False

        # equal
        return True


    def __ne__(self, other):

        # compare major
        if self.Major != other.Major:
            return True

        # compare minor
        if self.Minor != other.Minor:
            return True

        # compare patch
        if self.Patch != other.Patch:
            return True

        # equal
        return False
