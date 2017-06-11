#!/bin/bash
dest="/home/sboxx.org/public_html/dbdat.zip"
cd /home/sboxx.org/dbdat/
./update.php all && rm -rf $dest && zip $dest db.dat
