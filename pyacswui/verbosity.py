import datetime

class Verbosity(object):

    def __init__(self, verbosity=0, group_prefix=""):

        # parent
        if isinstance(verbosity, Verbosity):
            self._verbose_level = verbosity._verbose_level + 1
        else:
            self._verbose_level = int(verbosity)

        # limit to zero
        if self._verbose_level < 0:
            self._verbose_level = 0

        # group prefix
        if isinstance(verbosity, Verbosity):
            self._group_prefix = verbosity._group_prefix + "." + str(group_prefix)
        else:
            self._group_prefix = str(group_prefix)


    def __del__(self):
        pass


    def level(self):
        return self._verbose_level


    def print(self, *args, **kwargs):

        # timestamp
        t = datetime.datetime.now()
        print(t.strftime("%H:%M:%S") + "  ", end="")

        # indent
        print("    " * self._verbose_level, end="")

        # group_prefix
        if len(self._group_prefix) > 0:
            print("[" + self._group_prefix + "] ", end="")

        # print message
        print(*args, **kwargs)




# testing
if __name__ == "__main__":


    class Helper(object):

        def __init__(self, verbosity = 0):
            self.__verbosity = Verbosity(verbosity, self.__class__.__name__)


        def getHelp(self, verbosity=0):
            self.__verbosity.print("Help requested")



    class Worker(object):

        def __init__(self, verbosity=0):
            self.__verbosity = Verbosity(verbosity, self.__class__.__name__)
            self._helper = Helper(self.__verbosity)
            self.__verbosity.print("initialization done")


        def process(self):
            self.__verbosity.print("Begin processing")
            self.subProcess1()
            self.subProcess2()


        def subProcess1(self):
            verb = Verbosity(self.__verbosity)
            verb.print("sub process 1 part A")
            self._helper.getHelp()
            self.subProcess2()
            verb.print("sub process 1 part B")
            self._helper.getHelp()


        def subProcess2(self):
            verb = Verbosity(self.__verbosity)
            verb.print("sub process 2")
            self._helper.getHelp()



    myWorker = Worker(verbosity=99)
    myWorker.process()
