#!/usr/bin/bash
/opt/FR/scripts/fr_restart.py &
sleep 2
/opt/FR/scripts/fr_configuration.py &
