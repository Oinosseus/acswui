

class VerboseClass(object):

    def __init__(self):
        self.__verbosity = 0
        self.__print_last_level = 0


    @property
    def Verbosity(self):
        return self.__verbosity

    @Verbosity.setter
    def Verbosity(self, level):
        level = int(level)
        if level < 0:
            level = 0
        self.__verbosity = level


    def print(self, level, *args, **kwargs):

        # printing new lines depending on last level
        for i in range(level, self.__print_last_level):
            print()

        if level <= self.__verbosity:
            print("  " * level, *args, **kwargs)
        self.__print_last_level = level
