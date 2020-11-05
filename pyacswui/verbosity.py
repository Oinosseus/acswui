

class Verbosity(object):


    def __init__(self, verbosity=0):

        if isinstance(verbosity, Verbosity):
            self._level = verbosity._level - 1
            self._indent = verbosity._indent + 1
        else:
            self._level = int(verbosity)
            self._indent = 0

        if self._level < 0:
            self._level = 0
            self._indent = 0



    def print(self, *args, **kwargs):
        if self._level > 0:
            print("    " * self._indent, end="")
            print(*args, **kwargs)
