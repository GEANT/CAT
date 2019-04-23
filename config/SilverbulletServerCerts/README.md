Server Certificates and CAs
===========================
When the Silverbullet feature is used, this directory has to contain the CAs
and server certificates for all Federations which are enabled in the system.

For each federation, the structure is as follows:

* (directory) <federation identifier in uppercase>
  - (file) root.pem : the self-signed root certificate for this Federation

(e.g. directory "LU" with the single file "server.pem" inside)

The server names follow a schema: the suffix is defined in 
\config\ConfAssistant::SILVERBULLET['server_suffix']. The full name will be
auth.<federation><suffix>

Naturally, the RADIUS server needs to have the actual server certificate with
that name; but it is not needed here on the web server part.

Similarly, the server certificate should contain a CRL Distribution Point and
the CRLDP URL needs to exist and serve an actual, valid CRL for the root CA.
This is again not (necessarily) done on the CAT web server but could be 
anywhere.
