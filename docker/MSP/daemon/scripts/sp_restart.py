#!/usr/bin/python3
# -*- coding: utf-8 -*-
# pylint: disable=invalid-name
"""
FreeRADIUS restart server
"""
import sys
import time
import logging
import posix_ipc
import subprocess
import os
import psutil
from pathlib import Path

SEM_RR = '/FR_RESTART'
SEM_JUST_SLEEPING = '/FR_SLEEPING'
FR_LOG = '/opt/scripts/logs/radius_restart.log'
TIME_F = "%Y%m%d%H%M%S"
RESTART_INTERVAL = 30
RESTART_TIME = '/opt/Socket/CAT_requests/last_fr_restart'
FR_PID = '/opt/FR/var/run/radiusd/radiusd.pid'
#RP_PID = '/var/run/radsecproxy-psk.pid'


def init_log():
    """
    Initialise logging
    """
    sys.getfilesystemencoding = lambda: 'UTF-8'
    _logger = logging.getLogger(__name__)
    _logger.setLevel(logging.INFO)
    _handler = logging.FileHandler(FR_LOG, encoding='UTF-8')
    _handler.setLevel(logging.INFO)
    _handler.setFormatter(logging.Formatter(
        '%(asctime)s - %(levelname)s - %(message)s'))
    _logger.addHandler(_handler)
    return _logger


def radius_restart():
    """Restart server
    """
    start = time.time()
    if sem_restart_req.value > 0:
        logger.info('Clear semaphore before restart (' +
                    str(sem_restart_req.value) + ')')
        while True:
            sem_restart_req.acquire()
            if sem_restart_req.value == 0:
                logger.info("Semaphore cleared")
                break
    if os.path.isfile(FR_PID):
        with open(FR_PID) as _fr:
            _lines = _fr.readlines()
        pid = _lines[0].strip()
        p = psutil.Process(int(pid))
        p.terminate()  #or p.kill()
    subprocess.run(["/opt/FR/sbin/radiusd"])
    #if os.path.isfile(RP_PID):
        #with open(RP_PID) as _fr:
            #_lines = _fr.readlines()
        #pid = _lines[0].strip()
        #p = psutil.Process(int(pid))
        #p.terminate()  #or p.kill()
    #subprocess.run(["/usr/local/sbin/radsecproxy", "-c", "/usr/local/etc/radsecproxy-psk.conf"])
    end = time.time()
    logger.info('FR restart ' + str(end-start) + 's.')
    Path(RESTART_TIME).touch()
    sem_restart_suspended.release()
    time.sleep(RESTART_INTERVAL)
    sem_restart_suspended.acquire()
    

logger = init_log()

try:
    sem_js = posix_ipc.Semaphore(SEM_JUST_SLEEPING)
    posix_ipc.unlink_semaphore(SEM_JUST_SLEEPING)
except Exception as e:
    logger.info('An exception occurred ' + str(e) + ', ' + type(e).__name__)
    pass
sem_restart_req = posix_ipc.Semaphore(SEM_RR, posix_ipc.O_CREAT)
sem_restart_suspended = posix_ipc.Semaphore(SEM_JUST_SLEEPING,
                                            posix_ipc.O_CREAT)
if sem_restart_suspended.value == 1:
    sem_restart_suspended.acquire()
logger.info("Waiting until semaphore's value > 0")
logger.info("Suspended value " + str(sem_restart_suspended.value))

while True:
    sem_restart_req.acquire()
    #now = datetime.datetime.utcnow()
    logger.info('FR restart')
    radius_restart()
