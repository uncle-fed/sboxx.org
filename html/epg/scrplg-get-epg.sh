#!/bin/sh
# title:Get TV Guide
 
# change your key here
EPG_CODE="xt"

# get epg.dat location
source /var/etc/pgi.conf
[ -s /tmp/epg.dat ] && EPG_DATA_DIR=/tmp

# check PGI image version
VER=$(awk -Fp '/[0-9\.]pgi/{print $1}' /usr/share/sbox/db/.version 2>/dev/null)
[ -z "$VER" ] && echo "Error: this script is for PGI images only!" && exit
VMAJ=$(echo "$VER" | cut -d. -f1)
[ "$VMAJ" != "1" ] &&  echo "Error: Non-supported PGI version!" && exit

# get epg data
VMIN=$(echo "$VER" | cut -d. -f2)
/usr/bin/wget --header='Accept-Encoding:gzip' -qO- \
	"http://ipbox.linkpc.net/epg/?version=$VMIN&code=$EPG_CODE" | \
	/bin/gunzip | /usr/bin/sqlite3 $EPG_DATA_DIR/epg.dat

# the end
echo "Finished EPG import!"
