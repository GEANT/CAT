#!/bin/bash
/usr/bin/python3 /opt/FR/scripts/check_posix_ipc.py
if [ $? -eq 1 ]
then
  cd /opt/install/posix_ipc-1.0.4
  /usr/bin/python3 setup.py install
fi
