
class Verbosity(object):

    def __init__(self, verbosity=0, group_prefix=""):

        #Verbosity.CurrentIndentLevel += 1
        self._verbose_level = 0
        self._indent_level = 0

        # parent
        self._parent = None
        if isinstance(verbosity, Verbosity):
            self._parent = verbosity
            self._verbose_level = self._parent._verbose_level - 1
            self._indent_level = self._parent._indent_level + 1
        else:
            self._verbose_level = int(verbosity)

        # limit to zero
        if self._verbose_level < 0:
            self._verbose_level = 0

        # group prefix
        self._group_prefix = str(group_prefix)
        if self._group_prefix == "" and self._parent is not None:
            self._group_prefix = self._parent._group_prefix + "."

        self._anything_printed = False


    def __del__(self):
        pass


    def level(self):
        return self._verbose_level


    def print(self, *args, **kwargs):

        if self._verbose_level > 0:

            # indent
            print("    " * self._indent_level, end="")

            # group_prefix
            if self._group_prefix:
                print("[" + self._group_prefix + "] ", end="")

            # print message
            print(*args, **kwargs)
            self._anything_printed = True




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
