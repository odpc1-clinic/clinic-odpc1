#!/usr/bin/env bash
# นับแถวทุกตาราง (COUNT(*) จริง ไม่ใช่ค่าประมาณ) — ใช้เทียบต้นทาง/ปลายทางหลังย้ายฐาน
# ใช้: MYSQL_PWD=... rowcounts.sh <client> <host> <port> <user> <db>
CLIENT=$1 HOST=$2 PORT=$3 USER=$4 DB=$5
for t in $("$CLIENT" -h"$HOST" -P"$PORT" -u"$USER" -N -e "select table_name from information_schema.tables where table_schema='$DB' and table_type='BASE TABLE' order by table_name"); do
  echo "$t $("$CLIENT" -h"$HOST" -P"$PORT" -u"$USER" -N -e "select count(*) from \`$DB\`.\`$t\`")"
done
