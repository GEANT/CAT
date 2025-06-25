#!/usr/bin/python3
# pylint: disable=invalid-name
"""
Hand freeRADIUS blacklist
"""
import os
import sys
import time
import logging
import sqlite3
import posix_ipc

# set server IP
HOSTIP = 'RADIUS_SP_IP'
HOSTIPv6 = 'RADIUS_SP_IPV6'

TEMPLATE_DIR = '/opt/templates/'
FR_RADDB = '/opt/FR/etc/raddb/'
TLS2SITE = 'tls2site'
TEMPLATE_BLACKLIST = 'authorize-blacklist'
TLS_BLACKLIST = 'authorize-blacklist'
TEMP_DIR = '/opt/scripts/tmp/'
CONF_DIR = '/opt/SPs/'
RADIUS_DB = CONF_DIR + 'radiussql.db'
TIME_F = "%Y%m%d%H%M%S"
DATE_F = "%Y%m%d"
NL = "\n"
TLS_CLIENT = "%{listen:TLS-Client-Cert-Common-Name}"
TLS_CLIENT_SERIAL = "%{toupper:%{listen:TLS-Client-Cert-Serial}}"
REVOKED_LOG = '/opt/scripts/logs/revoked.log'

SEM_RR = '/FR_RESTART'

def init_log():
    """
    Initialise logging
    """
    sys.getfilesystemencoding = lambda: 'UTF-8'
    _logger = logging.getLogger(__name__)
    _logger.setLevel(logging.INFO)
    _handler = logging.FileHandler(REVOKED_LOG, encoding='UTF-8')
    _handler.setLevel(logging.INFO)
    _handler.setFormatter(logging.Formatter(
        '%(asctime)s - %(levelname)s - %(message)s'))
    _logger.addHandler(_handler)
    return _logger

sem_restart_req = posix_ipc.Semaphore(SEM_RR)

logger = init_log()

con = sqlite3.connect(RADIUS_DB)
cur = con.cursor()

templ = open(TEMPLATE_DIR + TLS2SITE + '/' + TEMPLATE_BLACKLIST, 'r', encoding='utf-8')
bl_template = list(templ)
templ.close()

SELECTNEW = 'SELECT * FROM tls_revoked where handled=0'
UPDATENEW = 'UPDATE tls_revoked set handled=1 where cert_serial="%s"'
IN4HOURS = 4*60

now = int(time.time())
blacklist = {}
serials = []
for row in cur.execute(SELECTNEW):
    # row[0] client_id
    # row[1] cert_serial
    # row[2] cert_notafter
    # row[3] createtime
    # row[4] handled
    _clientcn = row[0]
    _suffix = _clientcn[3:]
    _serial = row[1]
    _inmin = int((now-row[3])/60)
    if _inmin < IN4HOURS:
        logger.info('%s with serial %s waiting (added %d mins ago)', _clientcn, _serial, _inmin)
        continue
    if not os.path.isfile(CONF_DIR + 'site_' + _suffix):
        logger.info('%s with serial %s still waiting (added %d mins ago) because deployment is not acitve',
                    _clientcn, _serial, _inmin)
        continue
    _content = ''.join(bl_template) % {
                'tlsclient' : TLS_CLIENT,
                'tlsclientserial' : TLS_CLIENT_SERIAL,
                'clientcn': _clientcn,
                'serial': _serial }
    if _suffix not in blacklist:
        blacklist[_suffix] = []
    blacklist[_suffix].append(_content)
    logger.info('%s with serial %s blacklisted', _clientcn, _serial)
    serials.append(_serial)
# remember to set handled=1 for each serial
if len(blacklist) > 0:
    for _suffix, _contents in blacklist.items():
        _content = '\n'.join(_contents)
        _lines = []
        if os.path.isfile(CONF_DIR + TLS2SITE + '/' + TLS_BLACKLIST + '_' + _suffix):
            with open(CONF_DIR + TLS2SITE + '/' + TLS_BLACKLIST + '_' + _suffix,
                      'r', encoding='utf-8') as _in:
                _lines = _in.readlines()
        _content = ''.join(_lines) + _content
        with open(CONF_DIR + TLS2SITE + '/' + TLS_BLACKLIST + '_' + _suffix,
                  'w', encoding='utf-8') as _out:
            _out.write(_content)

        with open(FR_RADDB + TLS2SITE + '/' + TLS_BLACKLIST + '.d/' + TLS_BLACKLIST + '_' + _suffix,
                  'w', encoding='utf-8') as _out:
            _out.write(_content)
    for _serial in serials:
        cur.execute(UPDATENEW % (_serial))
    con.commit()
    sem_restart_req.release()
