#!/bin/bash

HOME="/home/sboxx.org/epg"
TMP="/home/sboxx.org/tmp"
LOG="/home/sboxx.org/log/epg.log"
URL="http://linux-sat.tv/epg/tvprogram_ua_ru.gz"
FILE="$(basename $URL)"

exec 5>&1
exec 6>&2
exec >$LOG 2>&1

cd $TMP

[ -f "$FILE" ] && BEFORE=$(stat -c %Y $FILE)

/usr/bin/wget --timestamping $URL

[ -f "$FILE" ] && AFTER=$(stat -c %Y $FILE)

[ -z "$AFTER" ] && exit

if [ "$BEFORE" != "$AFTER" ] ; then
    echo "$BEFORE vs $AFTER"
    exec 1>&5
    exec 2>&6
    $HOME/epg2db.php
fi
