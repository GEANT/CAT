ARG from=ubuntu:latest
FROM ${from} AS build

ARG DEBIAN_FRONTEND=noninteractive

RUN apt-get update
RUN apt-get install -y devscripts equivs wget quilt gcc curl nmap lsof \
            nettle-dev \
            rsyslog \
            mysql-server bind9-dnsutils \
            nettle-dev mysql-server bind9-dnsutils \
            cron apache2 php libapache2-mod-php telnet vim fetch-crl net-tools
RUN apt-get update
RUN apt-get install -y php-zip php-mysql python3-pymysql python3-pip python3-psutil

WORKDIR /etc/rsyslog.d
COPY rsyslog/23_eduroam.conf .

RUN mkdir -p /var/log/radsecproxy

WORKDIR /var/www/html
RUN mkdir sp
COPY web/html sp
RUN mkdir -p /opt/Socket/CAT_requests
RUN chown -R www-data:www-data /opt/Socket/CAT_requests

WORKDIR /usr/local
RUN mkdir db
COPY mysql/ db/
COPY sqlite/ db/


WORKDIR /etc/apache2
COPY web/site/ports.conf .
COPY web/site/site.conf sites-available/000-default.conf

RUN mkdir -p /usr/local/sbin
WORKDIR /usr/local/sbin
COPY daemon/ .
RUN pip install --break-system-packages  posix-ipc
#RUN pip install --break-system-packages  ./posix_ipc-1.1.1.tar.gz

#
#  Set up NetworkRADIUS extras repository
#
RUN install -d -o root -g root -m 0755 /etc/apt/keyrings \
 && curl -o /etc/apt/keyrings/packages.networkradius.com.asc "https://packages.networkradius.com/pgp/packages%40networkradius.com" \
 && echo "deb [signed-by=/etc/apt/keyrings/packages.networkradius.com.asc] http://packages.networkradius.com/extras/ubuntu/noble noble main" > /etc/apt/sources.list.d/networkradius-extras.list \
 && apt-get update

#
#  Create build directory
#
RUN mkdir -p /usr/local/src/repositories
WORKDIR /usr/local/src/repositories
ARG releasef=freeradius-server-3.2.7
ARG versionf=release_3_2_7
ARG sourcef=https://github.com/FreeRADIUS/freeradius-server/releases/download/${versionf}/${releasef}.tar.gz
ARG versionr=1.11.2
ARG sourcer=https://github.com/radsecproxy/radsecproxy/releases/download/${versionr}/radsecproxy-${versionr}.tar.gz

RUN echo ${sourcef}
RUN wget ${sourcef}
RUN tar xfv ${releasef}.tar.gz
RUN rm ${releasef}.tar.gz

RUN echo ${sourcer}
RUN wget ${sourcer}
RUN tar xfv radsecproxy-${versionr}.tar.gz
RUN rm radsecproxy-${versionr}.tar.gz
#
#  Install build dependencies for all branches from v4 onwards
#
WORKDIR /usr/local/src/repositories/${releasef}
#
#  Install build dependencies
#
RUN if [ -e ./debian/control.in ]; then \
        debian/rules debian/control; \
    fi; \
    echo 'y' | mk-build-deps -irt'apt-get -yV' debian/control

#
#  Build the server

RUN ./configure --prefix=/opt/FR
RUN make 
RUN make install

WORKDIR /opt/FR
RUN mkdir -p templates scripts/logs scripts/tmp etc/raddb/certs/clients
RUN mkdir -p etc/raddb/tls2site/pre-proxy.d etc/raddb/tls2site/post-proxy.d
RUN mkdir -p etc/raddb/tls2site/authorize-blacklist.d
RUN mkdir -p etc/raddb/tls2site/authorize-1.d etc/raddb/tls2site/authorize-2.d
RUN mkdir -p etc/raddb/tls2site/post-auth-1.d etc/raddb/tls2site/post-auth-2.d etc/raddb/tls2site/post-auth-3.d
RUN mkdir mgw

COPY freeRADIUS/raddb/sites-available/site_1999 etc/raddb/sites-available/
COPY freeRADIUS/raddb/sites-available/tls_2083 etc/raddb/sites-available/
COPY freeRADIUS/raddb/sites-available/tls2site etc/raddb/sites-available/
COPY freeRADIUS/raddb/proxy.conf etc/raddb/
COPY freeRADIUS/raddb/mods-available/ etc/raddb/mods-available/
COPY freeRADIUS/raddb/mods-config/queries.conf etc/raddb/mods-config/sql/cui/mysql/queries.conf
COPY freeRADIUS/raddb/mods-config/psksql.sql etc/raddb/mods-config/sql/main/sqlite/psksql.sql
COPY freeRADIUS/raddb/certs/ etc/raddb/certs/
COPY freeRADIUS/raddb/certs/clients/ etc/raddb/certs/clients/
COPY freeRADIUS/raddb/policy.d/ etc/raddb/policy.d/

WORKDIR /opt/
RUN mkdir -p templates scripts/logs scripts/tmp 

COPY daemon/templates/ templates/
COPY daemon/scripts/ scripts/
COPY daemon/blacklist-cron /etc/cron.d/

WORKDIR /usr/local/src/repositories/radsecproxy-${versionr}
RUN ./configure
RUN make
RUN make install
WORKDIR /usr/local/etc
COPY radsecproxy/radsecproxy.conf .
COPY radsecproxy/radsecproxy.conf.d/ radsecproxy.conf.d/

WORKDIR /etc/fetch-crl.d/
COPY fetch-crl/edupki.conf .
RUN /usr/sbin/fetch-crl

ARG freerad_uid=130
ARG freerad_gid=130

RUN groupadd -g ${freerad_gid} -r freerad \
 && useradd -u ${freerad_uid} -g freerad -r -M -d /opt/FR -s /usr/sbin/nologin freerad \
 && apt-get update \
 && apt-get clean \
 && rm -r /var/lib/apt/lists/* 

WORKDIR /
COPY docker-entrypoint.sh.deb docker-entrypoint.sh
RUN chmod +x docker-entrypoint.sh

ENTRYPOINT ["/docker-entrypoint.sh"]
