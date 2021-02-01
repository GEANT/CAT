#!/usr/bin/python3
import sys
try:
    import posix_ipc
    sys.exit(0)
except ModuleNotFoundError as err:
    # Error handling
    sys.exit(1)
