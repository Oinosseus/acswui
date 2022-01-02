
class Verbosity(object):

    CurrentIndentLevel = 0


    def __init__(self, verbosity=0, group_prefix=""):

        Verbosity.CurrentIndentLevel += 1

        # parent
        self._parent = None
        if isinstance(verbosity, Verbosity):
            self._parent = verbosity

        # verbosity level
        self._MaxIndentLevel = 0
        if self._parent:
            self._MaxIndentLevel = self._parent._MaxIndentLevel
        elif type(verbosity) == type(123):
            self._MaxIndentLevel = verbosity

        # group prefix
        self._group_prefix = None
        #if group_prefix is None:
            #if self._parent:
                #self._group_prefix = self._parent._group_prefix
        #else:
        self._group_prefix = str(group_prefix)

        self._any_print = False


    def __del__(self):
        Verbosity.CurrentIndentLevel -= 1

        # for debugging (should not happen)
        if Verbosity.CurrentIndentLevel < 0:
            raise ValueError("Droping below zero!")


    def level(self):
        level = self._MaxIndentLevel - Verbosity.CurrentIndentLevel + 1
        if level < 0:
            level = 0
        return level


    def print(self, *args, **kwargs):

        if Verbosity.CurrentIndentLevel < (self._MaxIndentLevel + 1):

            # indent
            print("    " * (Verbosity.CurrentIndentLevel - 1), end="")

            p = self._parent
            if p and p._group_prefix and not p._any_print:
                p.print("...")

            # group_prefix
            if self._group_prefix:
                print("[" + self._group_prefix + "] ", end="")

            # print message
            print(*args, **kwargs)
            self._any_print = True




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
