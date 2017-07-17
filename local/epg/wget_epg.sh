#!/bin/bash -e

URL="http://epg.in.ua/epg/tvprogram_ua_ru.gz"
FILE="$(basename $URL)"

SCRIPT=$(readlink -f "$0")
HOME=$(dirname "$SCRIPT")
DATA="$HOME/data"
DB="$HOME/db/db.sqlite"
LOG="/var/local/log/epg.log"
UA="Mozilla/5.0 (Windows NT 6.1; Win64; x64; rv:54.0) Gecko/20100101 Firefox/54.0"

exec 5>&1
exec 6>&2
exec >$LOG 2>&1

cd $DATA

BEFORE=$(stat -c %Y $FILE 2>/dev/null) || true

/usr/bin/wget --user-agent="$UA" --timestamping $URL

AFTER=$(stat -c %Y $FILE 2>/dev/null)

if [ "$BEFORE" != "$AFTER" ] ; then
    echo "$BEFORE vs $AFTER"
    exec 1>&5
    exec 2>&6
    /usr/bin/php $HOME/epg2db.php $DATA/$FILE $DB
fi
