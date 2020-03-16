Client Certificates - Issuing and Root CA
=========================================
When the Silverbullet feature is used, this directory has to contain the CAs
which issue the client certificates. The following six files need to be in
place:

- rootca-RSA.pem : The self-signed root in RSA variant
- rootca-ECDSA.pem : The self-signed root in ECDSA variant
- real-RSA.pem : The issuing (intermediate) CA certificate in RSA variant
- real-RSA.key : the private key to the real-RSA.pem certificate (can not be in encrypted form!)
- real-ECDSA.pem : The issuing (intermediate) CA certificate in ECDSA variant
- real-ECDSA.key : the private key to the real-ECDSA.pem certificate (can not be in encrypted form!)
