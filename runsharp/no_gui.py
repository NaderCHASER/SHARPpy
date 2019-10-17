# A quick attempt at a QUI-less SHARPpy plotter. You can pass a single argument 
# with a filename, or 4 with the SHARPpy model name, run time as YYYYMMDDHH, 
# forecast hour, and site. Almost all of the code is just modified from 
# full_gui.py. Still requires PySide and Qt, but the no windows open, and the 
# script just exits once image is save.
# ie python no_gui.py OUN.txt or 
#    python no_gui.py "3km NAM" 2018041112 3 KOUN

import sys, os
import numpy as np
import warnings
import utils.frozenutils as frozenutils

HOME_DIR = os.path.join(os.path.expanduser("~"), ".sharppy")

if len(sys.argv) > 1 and sys.argv[1] == '--debug':
    debug = True
    sys.path.insert(0, os.path.normpath(os.getcwd() + "/.."))
else:
    debug = False
    np.seterr(all='ignore')
    warnings.simplefilter('ignore')

if frozenutils.isFrozen():
    if not os.path.exists(HOME_DIR):
        os.makedirs(HOME_DIR)

    outfile = open(os.path.join(HOME_DIR, 'sharppy-out.txt'), 'w')

    sys.stdout = outfile
    sys.stderr = outfile
    
from sharppy.viz.SPCWindow import SPCWindow
from sharppy.viz.map import MapWidget 
import sharppy.sharptab.profile as profile
from sharppy.io.decoder import getDecoders
#from sharppy._sharppy_version import __version__, __version_name__
from datasources import data_source
from utils.async import AsyncThreads
from utils.progress import progress

from PySide.QtCore import *
from PySide.QtGui import *
import datetime as date
from functools import wraps, partial
import cProfile
from os.path import expanduser, splitext
import ConfigParser
import traceback
from functools import wraps, partial

class SHARPPlot(object):
    date_format = "%Y-%m-%d %HZ"
    run_format = "%d %B %Y / %H%M UTC"

    async = AsyncThreads(2, debug)
    HOME_DIR = os.path.join(os.path.expanduser("~"), ".sharppy")
    cfg_file_name = os.path.join(HOME_DIR,'sharppy.ini')

    def __init__(self):
        self.config = ConfigParser.RawConfigParser()
        self.config.read(SHARPPlot.cfg_file_name)
        if not self.config.has_section('paths'):
            self.config.add_section('paths')
            self.config.set('paths', 'load_txt', expanduser('~'))
        self.skew = None
        self.data_sources = data_source.loadDataSources()
    def plot(self, filename):
        self.skewApp(filename)
        if self.skew is not None:
            pixmap = QPixmap.grabWidget(self.skew.spc_widget)
            pixmap.save('/data/' + splitext(filename)[0] + '.jpg', 'JPG', 80)
        self.skew = None
    def plot_src(self, model, run, fhour, site):
        
        self.skewApp(model=model, run=run, fhour=fhour, site=site)
        if self.skew is not None:
            pixmap = QPixmap.grabWidget(self.skew.spc_widget)
            pixmap.save('/data/' + model + '_' + site + '_' + run + '_' + fhour + '.jpg', 'JPG', 80)
        self.skew = None
    def get_loc(self, model, run, site):
        loc = None
        for x in self.data_sources[model].getAvailableAtTime(run):
            if x['icao'].upper() == site.upper():
                loc = x
                break
            elif x['iata'].upper() == site.upper():
                loc = x
                break
            elif x['srcid'].upper() == site.upper():
                loc = x
                break
        return loc
    def loadArchive(self, filename):
        """
        Get the archive sounding based on the user's selections.
        Also reads it using the Decoders and gets both the stationID and the profile objects
        for that archive sounding.
        """

        for decname, deccls in getDecoders().iteritems():
            try:
                dec = deccls(filename)
                break
            except:
                dec = None
                continue

        if dec is None:
            raise IOError("Could not figure out the format of '%s'!" % filename)

        profs = dec.getProfiles()
        stn_id = dec.getStnId()

        return profs, stn_id
    def skewApp(self, filename=None, model=None, site=None, run=None, fhour=None):
        """
        Create the SPC style SkewT window, complete with insets
        and magical funtimes.
        :return:
        """

        failure = False

        exc = ""

        ## if the profile is an archived file, load the file from
        ## the hard disk
        if filename is not None:
            model = "Archive"
            prof_collection, stn_id = self.loadArchive(filename)
            disp_name = stn_id

            run = prof_collection.getCurrentDate()
        else:
        ## otherwise, download with the data thread
            prof_idx = [int(fhour)]
            disp_name = site
            run = date.datetime.strptime(run, "%Y%m%d%H")
            model = model
            loc = self.get_loc(model, run, site)

            if self.data_sources[model].getForecastHours() == [ 0 ]:
                prof_idx = [ 0 ]
            print(prof_idx, disp_name, run, model, self.data_sources[model].getForecastHours())
            ret = loadData(self.data_sources[model], loc, run, prof_idx)

            if isinstance(ret[0], Exception):
                exc = ret[0]
                failure = True
            else:
                prof_collection = ret[0]

        if not failure:
            prof_collection.setMeta('model', model)
            prof_collection.setMeta('run', run)
            prof_collection.setMeta('loc', disp_name)

            if not prof_collection.getMeta('observed'):
                # If it's not an observed profile, then generate profile objects in background.
                prof_collection.setAsync(SHARPPlot.async)

            if self.skew is None:
                # If the SPCWindow isn't shown, set it up.
                self.skew = SPCWindow( cfg=self.config)

            self.skew.addProfileCollection(prof_collection)
        else:
            print exc
@progress(SHARPPlot.async)
def loadData(data_source, loc, run, indexes, __text__=None, __prog__=None):
    """
    Loads the data from a remote source. Has hooks for progress bars.
    """
    if __text__ is not None:
        __text__.emit("Decoding File")

    url = data_source.getURL(loc, run)
    decoder = data_source.getDecoder(loc, run)
    dec = decoder(url)

    if __text__ is not None:
        __text__.emit("Creating Profiles")

    profs = dec.getProfiles(indexes=indexes)
    return profs

if __name__ == '__main__':
    app = QApplication([])
    main = SHARPPlot()
    if len(sys.argv) == 2:
        main.plot(sys.argv[1])
    elif len(sys.argv) == 5:
        main.plot_src(*sys.argv[1:5])
