#! /bin/sh

# Example script!
# This script looks up radsec srv records in DNS for the one
# realm given as argument, and creates a server template based
# on that. It currently ignores weight markers, but does sort
# servers on priority marker, lowest number first.
# For host command this is column 5, for dig it is column 1.

usage() {
    echo "Usage: ${0} <realm>"
    exit 1
}

test -n "${1}" || usage

REALM="${1}"
DIGCMD=$(command -v dig)
HOSTCMD=$(command -v host)
PRINTCMD=$(command -v printf)

dig_it_srv() {
    ${DIGCMD} +short srv $SRV_HOST | sort -n -k1 |
    while read line; do
        set $line ; PORT=$3 ; HOST=$4
        $PRINTCMD "\thost ${HOST%.}:${PORT}\n"
    done
}

dig_it_naptr() {
    ${DIGCMD} +short naptr ${REALM} | grep x-eduroam:radius.tls | sort -n -k1 |
    while read line; do
        set $line ; TYPE=$3 ; HOST=$6
        if [ "${TYPE,,}" = "\"s\"" ]; then
            SRV_HOST=${HOST%.}
            dig_it_srv
        fi
    done
}

host_it_srv() {
    ${HOSTCMD} -t srv $SRV_HOST | sort -n -k5 |
    while read line; do
        set $line ; PORT=$7 ; HOST=$8
        $PRINTCMD "\thost ${HOST%.}:${PORT}\n"
    done
}

host_it_naptr() {
    ${HOSTCMD} -t naptr ${REALM} | grep x-eduroam:radius.tls | sort -n -k5 |
    while read line; do
        set $line ; TYPE=$7 ; HOST=${10}
        if [ "${TYPE,,}" = "\"s\"" ]; then
            SRV_HOST=${HOST%.}
            host_it_srv
        fi
    done
}

if [ -x "${DIGCMD}" ]; then
    SERVERS=$(dig_it_naptr)
elif [ -x "${HOSTCMD}" ]; then
    SERVERS=$(host_it_naptr)
else
    echo "${0} requires either \"dig\" or \"host\" command."
    exit 1
fi

if [ -n "${SERVERS}" ]; then
    $PRINTCMD "server dynamic_radsec.${REALM} {\n${SERVERS}\n\ttype TLS\n\tsecret radsec\n\ttls edupkiserver\n\tcertificateNameCheck off\n}\n"
    exit 0
fi

exit 10                         # No server found.
