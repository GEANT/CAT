#!/usr/bin/python3
# -*- coding: utf-8 -*-
# pylint: disable=invalid-name
"""
FreeRADIUS restart server
"""
import sys
import time
import datetime
import logging
import dbus
import posix_ipc
from pathlib import Path

SEM_RR = '/FR_RESTART'
SEM_JUST_SLEEPING = '/FR_SLEEPING'
FR_LOG = '/opt/FR/scripts/logs/fr_restart.log'
TIME_F = "%Y%m%d%H%M%S"
RESTART_INTERVAL = 30
RESTART_TIME = '/opt/Socket/CAT_requests/last_fr_restart'


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
    """Restart server via systemctl
    """
    start = time.time()
    sysbus = dbus.SystemBus()
    systemd1 = sysbus.get_object('org.freedesktop.systemd1',
                                 '/org/freedesktop/systemd1')
    manager = dbus.Interface(systemd1, 'org.freedesktop.systemd1.Manager')
    if sem_restart_req.value > 0:
        logger.info('Clear semaphore before restart (' +
                    str(sem_restart_req.value) + ')')
        while True:
            sem_restart_req.acquire()
            if sem_restart_req.value == 0:
                logger.info("Sempahore cleared")
                break
    try:
        manager.RestartUnit('radiusd.service', 'fail')
    except dbus.exceptions.DBusException as err:
        print('FR restared skipped ' + str(err))
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
    logger.info('An exception occured ' + str(e) + ', ' + type(e).__name__)
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
    now = datetime.datetime.utcnow()
    logger.info('FR restart')
    radius_restart()
