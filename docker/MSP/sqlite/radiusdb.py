#!/usr/bin/python3
import sqlite3
CONF_DIR = '/opt/SPs/'
con = sqlite3.connect(CONF_DIR + 'radiussql.db')
cur = con.cursor()

# Create table
cur.execute('''CREATE TABLE IF NOT EXISTS "tls_revoked" (
        client_id TEXT,
        cert_serial TEXT,
        cert_notafter TEXT,
        createtime INTEGER,
        handled INTEGER default 0,
        UNIQUE (client_id, cert_serial))''')
cur.execute('''CREATE TABLE IF NOT EXISTS "psk_keys" (
        keyid TEXT UNIQUE,
        key BLOB)''')

con.commit()
con.close()
