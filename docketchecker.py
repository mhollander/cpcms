import os, os.path, re, sys

dir = "dockets"
counties = range(1,68)

assert 1996 < int(sys.argv[1]) < 2018, "Year is out of range 1997-2007"

total = 0

for i in counties:
    testdir = os.path.join(dir, sys.argv[1])
    regex = r".*-[0]?" + str(i) + "-.*"
    num = len([name for name in os.listdir(testdir) if (re.match(regex,name) and os.path.isfile(os.path.join(testdir, name)))])
    total += num
    print("County %s: %s" % (i, num))
	

print("Total Dockets: %s" % (total))