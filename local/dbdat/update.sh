#!/bin/bash -e

SAT_LIST="/var/local/dbdat/sql/sat.sql"
TP_STATIC="/var/local/dbdat/sql/tp_static.sql"
TP_PREV="/var/local/dbdat/sql/tp_prev.sql"
TP_CUR="/var/local/dbdat/sql/tp_curr.sql"
TP_ARCH="/var/local/dbdat/sql/archive"
DB_EMPTY="/var/local/dbdat/dat/db.empty.dat"
DB_FINAL="/var/local/dbdat/dat/db.dat"

DB_WEB="/var/www/html/dbdat.zip"
HIST_WEB="/var/www/html/dbdat.txt"

NEW_TP_LIST=$(/usr/bin/php /var/local/dbdat/update.php $SAT_LIST all)

if [ -s "$TP_CUR" ] ; then
    FDSTAMP=$(stat --format=%Y $TP_CUR)
    FDATE=$(date -d @$FDSTAMP +%Y%m%d.%H%M%S)
    cp -f $TP_CUR $TP_ARCH/tp.$FDATE.sql
    mv -f $TP_CUR $TP_PREV
fi

echo "$NEW_TP_LIST" > $TP_CUR

cp -f $DB_EMPTY $DB_FINAL

echo "INSERT INTO options VALUES('tpdate', '$(date +%Y%m%d)'); COMMIT; VACUUM;" | \
    cat $SAT_LIST $TP_CUR $TP_STATIC - | \
    sqlite3 $DB_FINAL

rm -f $DB_WEB
zip -j $DB_WEB $DB_FINAL
chown apache:apache $DB_WEB
chmod 0400 $DB_WEB

[ -s "$TP_PREV" ] || exit 0

HIST=$(date +'%-d %b %Y:')
DIFF=$(diff -u0 $TP_PREV $TP_CUR | grep '^[+\-]INSERT' || true)

if [ -z "$DIFF" ] ; then
    HIST="$HIST no change"
else
    CBAND=$(awk -F, '/^INSERT/{ID=gensub(/.*\(/,"",1,$1); if (ID<300 && $7 != 2) {printf("%d:", ID)}}' $SAT_LIST)

    # $1=sat_name, $2=sat_id, $3=freq, $4=sr, $5=pol, $11=mod, $12=fec, $13=sty
    HIST="$HIST\n"$(echo "$DIFF" | awk -F, 'BEGIN{
        CBAND=":'$CBAND'"
        split("1/2,2/3,3/4,3/5,4/5,5/6,6/7,7/8,8/9,9/10", FECTYPE)
        MODTYPE[0] = "QPSK"
        MODTYPE[1] = "8PSK"
        CPOL[0] = "R"
        CPOL[1] = "L"
        KPOL[0] = "V"
        KPOL[1] = "H"
    }
    {
        SAT = gensub(/.*--\s*/,"",1,$NF)
        SIGN = substr($1,1,1)
        FREQ = $3
        SR = $4
        POL = CBAND ~ ":"$2":" ? CPOL[$5] : KPOL[$5]
        FEC = FECTYPE[$12] ? FECTYPE[$12] : "auto"
        MOD = MODTYPE[$11] ? MODTYPE[$11] : "?"
        printf("   %21-s%s %5d/%s %5d %4-s %s\n", SAT, SIGN, FREQ, POL, SR, FEC, MOD)
    }')
fi

echo -e "$HIST" > $HIST_WEB.tmp
echo "-------------------------------------------------" >> $HIST_WEB.tmp
cat $HIST_WEB >> $HIST_WEB.tmp
mv -f $HIST_WEB.tmp $HIST_WEB
chown apache:apache $HIST_WEB
chmod 0400 $HIST_WEB
