#!/usr/bin/python3
# pylint: disable=invalid-name
"""
CAT requests listener
"""
import socket
import os
import sys
import time
import base64
from shutil import chown, move, copy
import logging
import sqlite3
import posix_ipc

# set server IP
HOSTIP = 'RADIUS_SP_IP'
HOSTIPv6 = 'RADIUS_SP_IPV6'

SOCKET_C = '/opt/Socket/CAT_requests/queue'
SEM_RR = '/FR_RESTART'
SEM_JUST_SLEEPING = '/FR_SLEEPING'
TEMPLATE_DIR = '/opt/templates/'
TEMPLATE_SITE = 'site_xxx'
TEMPLATE_DETAIL = 'detail_xxx'
TEMPLATES_TLS = ['authorize-1', 'authorize-2', 'pre-proxy', 'post-proxy',
                 'post-auth-1', 'post-auth-2', 'post-auth-3']
TEMPLATE_BLACKLIST = 'authorize-blacklist'
TLS = ['tls2site']
TLS_BLACKLIST = 'authorize-blacklist'
TEMP_DIR = '/opt/scripts/tmp/'
CONF_DIR = '/opt/SPs/'
PSK_DB = CONF_DIR + 'radiussql.db'
FR_SITES_A = '/opt/FR/etc/raddb/sites-available/'
FR_SITES_E = '/opt/FR/etc/raddb/sites-enabled/'
FR_RADDB = '/opt/FR/etc/raddb/'
FR_SITES_A_REL = '../sites-available/'
FR_MODS_A = '/opt/FR/etc/raddb/mods-available/'
FR_MODS_E = '/opt/FR/etc/raddb/mods-enabled/'
FR_MODS_A_REL = '../mods-available/'
TIME_F = "%Y%m%d%H%M%S"
DATE_F = "%Y%m%d"
NL = "\n"
SPACES = "        "
REPLY_USER_NAME = "%{reply:User-Name}"
NAS_ID = "%{base64:%{NAS-Identifier}/%{NAS-IP-Address}/%{NAS-IPv6-Address}/%{Called-Station-Id}}"
TLS_CLIENT = "%{listen:TLS-Client-Cert-Common-Name}"
TLS_CLIENT_SERIAL = "%{toupper:%{listen:TLS-Client-Cert-Serial}}"
TLSPSK_CLIENT = "%{listen:TLS-PSK-Identity}"
OPERATOR_NAME = "        Operator-Name = "
UNLANG_GUEST_VLAN = "update reply {" + NL + \
              "                Tunnel-Private-Group-Id=%s" + NL + \
              "                Tunnel-Medium-Type:=6" + NL + \
              "                Tunnel-Type:=VLAN" + NL + \
              "        }"
UNLANG_VLAN = "        %s ( Stripped-User-Domain == '%s' ) {" + NL + \
              "            update reply {" + NL + \
              "                Tunnel-Private-Group-Id=%s" + NL + \
              "                Tunnel-Medium-Type:=6" + NL + \
              "                Tunnel-Type:=VLAN" + NL + \
              "            }" + NL + \
              "        }"
UNLANG_DOMAIN = "Stripped-User-Domain != '%s'"
CAT_LOG = '/opt/scripts/logs/radius_configuration.log'
MAX_RESTART_REQUESTS = 10
SOCKET_TIMEOUT = 5.0
SELECTREVOKED = 'SELECT cert_serial, cert_notafter, createtime, handled from ' \
                'tls_revoked where client_id="%s"'
UPDATENEW = 'UPDATE tls_revoked set handled=1 where cert_serial="%s"'
IN4HOURS = 4*60


def init_log():
    """
    Initialise logging
    """
    sys.getfilesystemencoding = lambda: 'UTF-8'
    _logger = logging.getLogger(__name__)
    _logger.setLevel(logging.INFO)
    _handler = logging.FileHandler(CAT_LOG, encoding='UTF-8')
    _handler.setLevel(logging.INFO)
    _handler.setFormatter(logging.Formatter(
        '%(asctime)s - %(levelname)s - %(message)s'))
    _logger.addHandler(_handler)
    return _logger


def make_conf(data):
    """
    Make FR configuration for new site or update configuration
    or remove a site configuration
    country = data[0]
    instid = data[1]
    deploymentid = data[2]
    port = data[3]
    """
    _start = time.time()
    _secret = base64.b64decode(data[4]).decode('utf-8')
    _pskkey = base64.b64decode(data[7]).decode('utf-8')
    _guest_vlan = 0
    if len(data) == 10:
        _guest_vlan = str(data[8])
    _toremove = data[len(data)-1]
    _operatorname = ''
    _clientcn = 'SP' + str(data[2]) + '-' + str(data[1])
    if int(_toremove) == 0:
        if data[5] != '':
            _operatorname = base64.b64decode(data[5]).decode('utf-8')
        _vlans = []
        _realms = []
        _realm_vlan = ''
        if data[6] != '':
            _el = base64.b64decode(data[6]).decode('utf-8').split('#')
            _idx = 1
            while _idx < len(_el):
                _if = 'if'
                if _idx > 1:
                    _if = 'els' + _if
                _vlans.append(UNLANG_VLAN % (_if, _el[_idx], _el[0]))
                _realms.append(_el[_idx])
                _idx += 1
            _realm_vlan = NL + '\n'.join(_vlans)
        _vlan_block = ''
        if _guest_vlan != 0:
            _vlan_block = UNLANG_GUEST_VLAN % (_guest_vlan)
            _vlans = []
            if len(_realms) > 0:
                for _r in _realms:
                    _vlans.append(UNLANG_DOMAIN % (_r))
                _vlan_block = 'if (' + ' && '.join(_vlans) + \
                        ') { ' + NL + SPACES + _vlan_block + NL + SPACES + '}' + NL
        _vlan_block = _vlan_block + _realm_vlan
        logger.info('Create/update port: %s, secret: %s, operatorname: %s',
                    data[3], _secret, _operatorname)
        logger.info('VLAN %s', _vlan_block)
    else:
        logger.info('Remove deployment: %s-%s',
                    str(data[2]), str(data[1]))
        _res = remove_site(data[1], data[2])
        if _res == 0:
            logger.info('Nothing to remove')
            return 1
        return _res
    for _tls in TLS:
        if not os.path.exists(TEMP_DIR + _tls):
            os.makedirs(TEMP_DIR + _tls)
        if not os.path.exists(CONF_DIR + _tls):
            os.makedirs(CONF_DIR + _tls)
    _site = [
        _line % {'hostip': HOSTIP,
                 'hostipv6': HOSTIPv6,
                 'country': data[0],
                 'instid': data[1],
                 'deploymentid': data[2],
                 'port': data[3],
                 'secret': _secret,
                 'operatorname': _operatorname,
                 'nas_id': NAS_ID,
                 'vlans': _vlan_block,
                 'reply_username': REPLY_USER_NAME}
        for _line in site_template
    ]
    with open(TEMP_DIR + 'site_' + str(data[2]) + '-' + str(data[1]),
              'w', encoding='utf-8') as _out:
        _out.write(''.join(_site))
    for _tls in TLS:
        for _templ in TEMPLATES_TLS:
            with open(TEMPLATE_DIR + _tls + '/' + _templ,
                      encoding='utf-8') as _f:
                _lines = _f.readlines()
                _alllines = ''.join(_lines) % {
                    'tlsclient' : TLS_CLIENT,
                    'tlspskclient' : TLSPSK_CLIENT,
                    'tlsclientserial' : TLS_CLIENT_SERIAL,
                    'clientcn': _clientcn,
                    'country': data[0],
                    'port': data[3],
                    'instid': data[1],
                    'deploymentid': data[2],
                    'operatorname': _operatorname,
                    'vlans': _realm_vlan}
            with open(TEMP_DIR + _tls + '/' + _templ + '_' +  str(data[2]) +
                      '-' + str(data[1]), 'w', encoding='utf-8') as _out:
                _out.write(''.join(_alllines))
    if not os.path.isfile(TEMP_DIR + 'site_' + str(data[2]) + '-' + str(data[1])):
        logger.error('No %ssite_%s-%s file', TEMP_DIR, str(data[2]), str(data[1]))
        return False

    if os.path.isfile(FR_MODS_A + 'detail_' + str(data[2]) + '-' + str(data[1])):
        _detail = []
    else:
        _detail = [
            _line % {'port': data[3],
                     'instid': data[1],
                     'deploymentid': data[2],
                     'format': DATE_F}
            for _line in detail_template
        ]
        with open(TEMP_DIR + 'detail_' + str(data[2]) + '-' + str(data[1]), 'w',
                  encoding='utf-8') as _out:
            _out.write(''.join(_detail))
        if not os.path.isfile(TEMP_DIR + 'detail_' + str(data[2]) + '-' + str(data[1])):
            logger.error('No %sdetail_%s-%s file', TEMP_DIR, str(data[2]), str(data[1]))
            return False

    copy(TEMP_DIR + 'site_' + str(data[2]) + '-' + str(data[1]),
         CONF_DIR + 'site_' + str(data[2]) + '-' + str(data[1]))
    move(TEMP_DIR + 'site_' + str(data[2]) + '-' + str(data[1]),
         FR_SITES_A + 'site_' + str(data[2]) + '-' + str(data[1]))
    try:
        if not os.path.islink(FR_SITES_E + 'site_' + str(data[2]) + '-' + str(data[1])):
            os.chdir(FR_SITES_E)
            os.symlink(FR_SITES_A_REL + 'site_' + str(data[2]) + '-' + str(data[1]),
                       'site_' + str(data[2]) + '-' + str(data[1]))
        if os.path.isfile(TEMP_DIR + 'detail_' + str(data[2]) + '-' + str(data[1])):
            copy(TEMP_DIR + 'detail_' + str(data[2]) + '-' + str(data[1]),
                 CONF_DIR + 'detail_' + str(data[2]) + '-' + str(data[1]))
            move(TEMP_DIR + 'detail_' + str(data[2]) + '-' + str(data[1]),
                 FR_MODS_A + 'detail_' + str(data[2]) + '-' + str(data[1]))
        if not os.path.islink(FR_MODS_E + 'detail_' + str(data[2]) + '-' + str(data[1])):
            os.chdir(FR_MODS_E)
            os.symlink(FR_MODS_A_REL + 'detail_' + str(data[2]) + '-' + str(data[1]),
                       'detail_' + str(data[2]) + '-' + str(data[1]))
        for _tls in TLS:
            for _templ in TEMPLATES_TLS:
                if os.path.isfile(TEMP_DIR + _tls + '/' + _templ + '_' +
                                  str(data[2]) + '-' + str(data[1])):
                    copy(TEMP_DIR + _tls + '/' + _templ + '_' +
                            str(data[2]) + '-' + str(data[1]),
                            CONF_DIR + _tls + '/' + _templ + '_' +
                            str(data[2]) + '-' + str(data[1]))
                    move(TEMP_DIR + _tls + '/' + _templ + '_' +
                            str(data[2]) + '-' + str(data[1]),
                            FR_RADDB + _tls + '/' + _templ + '.d/' +
                            _templ + '_' + str(data[2]) + '-' + str(data[1]))
    except Exception:
        return False
    cur.execute('''INSERT OR IGNORE INTO psk_keys VALUES ("SP%s-%s", X'%s')''' % (
        str(data[2]), str(data[1]), _pskkey))
    logger.info('key added for keyid SP%s-%s', str(data[2]), str(data[1]))
    con.commit()
    # handled revoked
    blacklist = handle_blacklisted(_clientcn)
    if blacklist != '':
        with open(CONF_DIR + TLS[0] + '/' + TLS_BLACKLIST + '_' +
                  str(data[2]) + '-' + str(data[1]),
                  'w', encoding='utf-8') as _out:
            _out.write(blacklist)

        with open(FR_RADDB + TLS[0] + '/' + TLS_BLACKLIST + '.d/' + TLS_BLACKLIST + '_' +
                  str(data[2]) + '-' + str(data[1]),
                  'w', encoding='utf-8') as _out:
            _out.write(blacklist)
        with open(CONF_DIR + TLS[0] + '/' + TLS_BLACKLIST + '_' +
                  str(data[2]) + '-' + str(data[1]),
                  'w', encoding='utf-8') as _out:
            _out.write(blacklist)

        with open(FR_RADDB + TLS[0] + '/' + TLS_BLACKLIST + '.d/' + TLS_BLACKLIST + '_' +
                  str(data[2]) + '-' + str(data[1]),
                  'w', encoding='utf-8') as _out:
            _out.write(blacklist)

    _end = time.time()
    logger.info('New configuration ready, took %s', str(_end-_start))
    return True

def handle_blacklisted(clientcn):
    """
    Handle blacklisted certificates
    """
    serials = []
    blacklist = ''
    templ = open(TEMPLATE_DIR + TLS[0] + '/' + TEMPLATE_BLACKLIST, encoding='utf-8')
    bl_template = list(templ)
    for _row in cur.execute(SELECTREVOKED % clientcn):
        # _row[0] cert_serial
        # _row[1] cert_notafter
        # _row[2] createtime
        # _row[3] createtime
        _serial = _row[0]
        if _row[3] == 0:
            _now = int(time.time())
            _inmin = int((_now-_row[2])/60)
            if _inmin < IN4HOURS:
                logger.info('%s with serial %s waiting (added %d mins ago)',
                            clientcn, _serial, _inmin)
                continue
            else:
                serials.append(_serial)
        _content = ''.join(bl_template) % {
              'tlsclient' : TLS_CLIENT,
              'tlsclientserial' : TLS_CLIENT_SERIAL,
              'clientcn': clientcn,
              'serial': _serial }
        if blacklist != '':
            blacklist += '\n'
        blacklist += _content
    for _serial in serials:
        cur.execute(UPDATENEW % (_serial))
    if len(serials) > 0:
        con.commit()
    return blacklist

def make_blacklist(data):
    """
    Make blacklist for FR configuration
    only adds data to sqlite DB
    configuration will be change in few hours
    by another job
    """
    _start = time.time()
    _instid = data[0]
    _deploymentid = data[1]
    _serial = data[2]
    _notAfter = data[3]
    _now = int(time.time())
    cur.execute('''INSERT OR IGNORE INTO tls_revoked VALUES ("SP%s-%s", "%s", "%s", %d, 0)''' % (
         _deploymentid, _instid, _serial, _notAfter, _now))
    con.commit()
    return True

def remove_site(site_inst, site_depl):
    """
    Remove site given by site_deploymentid-instid
    if exists
    """
    _del = 0
    if os.path.islink(FR_SITES_E + 'site_' + site_depl + '-' + site_inst):
        logger.info('Remove link %ssite_%s-%s', FR_SITES_E, site_depl, site_inst)
        os.unlink(FR_SITES_E + 'site_' + site_depl + '-' + site_inst)
        os.remove(CONF_DIR + 'site_' + site_depl + '-' + site_inst)
        _del += 1
    if os.path.isfile(FR_SITES_A + 'site_' + site_depl + '-' + site_inst):
        logger.info('Remove file %ssite_%s-%s', FR_SITES_A, site_depl, site_inst)
        os.remove(FR_SITES_A + 'site_' + site_depl + '-' + site_inst)
        _del += 1
    if os.path.islink(FR_MODS_E + 'detail_' + site_depl + '-' + site_inst):
        logger.info('Remove link %sdetail_%s-%s', FR_MODS_E, site_depl, site_inst)
        os.unlink(FR_MODS_E + 'detail_' + site_depl + '-' + site_inst)
        os.remove(CONF_DIR + 'detail_' + site_depl + '-' + site_inst)
        _del += 1
    if os.path.isfile(FR_MODS_A + 'detail_' + site_depl + '-' + site_inst):
        logger.info('Remove file %sdetail_%s-%s', FR_MODS_A, site_depl, site_inst)
        os.remove(FR_MODS_A + 'detail_' + site_depl + '-' + site_inst)
        _del += 1
    for _tls in TLS:
        for _templ in TEMPLATES_TLS:
            if os.path.isfile(FR_RADDB + _tls + '/' + _templ + '.d/' + _templ + '_' +
                    site_depl + '-' + site_inst):
                logger.info('Remove file %s_tls/%s.d/%s_%s-%s',
                            FR_RADDB, _templ, _templ, site_depl, site_inst)
                os.remove(FR_RADDB + _tls + '/' + _templ + '.d/' + _templ +
                            '_' + site_depl + '-' + site_inst)
                os.remove(CONF_DIR + _tls + '/' + _templ +
                            '_' + site_depl + '-' + site_inst)
                _del += 1
            if os.path.isfile(FR_RADDB + _tls +
                              '/authorize-blacklist.d/authorize-blacklist_' +
                              site_depl + '-' + site_inst):
                logger.info(
                    'Remove file %s%s/authorize-blacklist.d/authorize-blacklist_%s-%s',
                            FR_RADDB, _tls, site_depl, site_inst)
                os.remove(FR_RADDB + _tls +
                    '/authorize-blacklist.d/authorize-blacklist_' + site_depl +
                    '-' + site_inst)
                os.remove(CONF_DIR + _tls + '/authorize-blacklist_' + site_depl +
                    '-' + site_inst)
                _del += 1
    cur.execute('''DELETE FROM psk_keys WHERE keyid="%s"''' % ('SP' +
                str(site_depl) + '-' + str(site_inst)))
    con.commit()
    logger.info('key removed for keyid SP%s-%s', str(site_depl), str(site_inst))
    logger.info('Files removed: %d', str(_del))
    return _del == 4

logger = init_log()
if os.path.exists(SOCKET_C):
    os.remove(SOCKET_C)

con = sqlite3.connect(PSK_DB)
cur = con.cursor()

server_c = socket.socket(socket.AF_UNIX, socket.SOCK_STREAM)
server_c.settimeout(SOCKET_TIMEOUT)
server_c.bind(SOCKET_C)
chown(SOCKET_C, 'HTTPD_USER', 'HTTPD_GROUP')
sem_restart_req = posix_ipc.Semaphore(SEM_RR)
sem_restart_suspended = posix_ipc.Semaphore(SEM_JUST_SLEEPING)

templ = open(TEMPLATE_DIR + TEMPLATE_SITE, encoding='utf-8')
site_template = list(templ)
templ.close()
templ = open(TEMPLATE_DIR + TEMPLATE_DETAIL, encoding='utf-8')
detail_template = list(templ)
templ.close()
logger.info('Listening on socket %s', SOCKET_C)
server_c.listen(1)
req_cnt = 0
waited = 0
while True:
    try:
        conn, addr = server_c.accept()
        waited = 0
        if sem_restart_suspended.value == 0 and req_cnt > MAX_RESTART_REQUESTS:
            logger.info('Semaphore released for fr_restart, request count: %d',
                        req_cnt)
            waited = req_cnt = 0
            sem_restart_req.release()
    except OSError:
        if req_cnt > 0:
            if sem_restart_suspended.value > 0:
                logger.info('Postpone, fr_restart process just sleeping %d',
                            sem_restart_suspended.value)
                continue
            logger.info('Semaphore released for fr_restart, request count: %d',
                        req_cnt)
            waited = req_cnt = 0
            sem_restart_req.release()
        continue
    buff = conn.recv(1024).decode('utf-8')
    elems = buff.split(':')
    logger.info('Received %d elements', len(elems))
    if len(elems) == 4:
        if make_blacklist(elems):
            conn.send(b"OK")
            logger.info("blacklist modified")
        else:
            conn.send(b"FAILURE")
            logger.info("blacklist modification failed")
    elif len(elems) == 9 or len(elems) == 10:
        if make_conf(elems):
            conn.send(b"OK")
            req_cnt = req_cnt + 1
            logger.info("requests count is %d", req_cnt)
        else:
            conn.send(b"FAILURE")
    else:
        conn.send(b"FAILURE")
