#!/usr/bin/bash
/opt/scripts/sp_restart.py &
sleep 2
/opt/scripts/radius_configuration.py &
