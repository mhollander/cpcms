import pandas as pd
import numpy as np
import tarfile


df = pd.DataFrame(0,
                columns=["1998","1999","2000"], #,"2001","2002","2003","2004","2005","2006","2007","2008"],
                index=range(1,68))

df['CountyNum'] = ["{:02d}".format(x) for x in range(1,68)]


newDF = df.copy()

def getCountyCount(filelist, x):
    county = "-"+x+     "-"
    return len([x for x in filelist if county in x])


for year in df:
    fn = year + ".tar.xz"
    try:
        tf = tarfile.open(name=fn,mode='r:xz')
    except FileNotFoundError:
        print("skipping "+fn) 
        continue
    else:
        print("counting dockets in " + fn);
        filelist = tf.getnames()
        tf.close()
        newDF[year] = df.apply(lambda x: getCountyCount(filelist, x['CountyNum']), axis=1)
        print("finished")

newDF.to_csv("out.csv")						
print(newDF)