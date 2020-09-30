CAT, the Configuration Assistant Tool for Enterprise Wi-Fi networks such as eduroam
===================================================================================

CAT collects information about RADIUS/EAP deployments from Wi-Fi network administrators and generates simple-to-use, good-looking, and secure installation programs for users of these networks. The goal is to vastly improve the network security by pushing secure Wi-Fi settings to all users without the need to expose them to or require them to understand all of the underlying technologies and configuration parameters.

[![Code Climate](https://codeclimate.com/github/GEANT/CAT/badges/gpa.svg)](https://codeclimate.com/github/GEANT/CAT)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/GEANT/CAT/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/GEANT/CAT/?branch=master)
[![Build Status](https://scrutinizer-ci.com/g/GEANT/CAT/badges/build.png?b=master)](https://scrutinizer-ci.com/g/GEANT/CAT/build-status/master)

eduroam CAT User Manuals
------------------------
The flagship of CAT, the eduroam CAT (https://cat.eduroam.org), has extensive documentation (with screenshots!) of CAT. You may want to read those for an overview of the features.

[eduroam CAT/Managed IdP National Roaming Operator documentation](https://wiki.geant.org/display/H2eduroam/A+guide+to+eduroam+CAT+2.0+and+eduroam+Managed+IdP+for+National+Roaming+Operator+administrators)

[eduroam CAT institution administrator documentation](https://wiki.geant.org/display/H2eduroam/A+guide+to+eduroam+CAT+for+IdP+administrators)
[eduroam Managed IdP institution administrator documentation](https://wiki.geant.org/display/H2eduroam/A+guide+to+eduroam+Managed+IdP+for+IdP+administrators)

There is no documentation for end users, simply because it's so easy to use on the end-user side that no documentation is required! :-)

The source code is [thoroughly documented](https://geant.github.io/CAT/web/apidoc/) using PhpDocumentor 3.

Large parts of the code can be remote-controlled using the [UserAPI](tutorials/UserAPI.md) and AdminAPI.

Known deployments
-----------------
There are three known production deployments of CAT (please let us know if you are deploying the software and want to be on this list!).

* *eduroam CAT* https://cat.eduroam.org (for all eduroam institutions and users world-wide)
* *eduroam Managed IdP* https://hosted.eduroam.org (deploying exclusively the 'silverbullet' feature set, for eduroam institutions and users world-wide)
* *Enterprise Wi-Fi CAT* https://enterprise-wifi.net (for enterprise networks unrelated to eduroam)

Installation and configuration of your own deployment
-----------------------------------------------------
With the production deployments as listed above, there are probably few use cases you would want to run your own installation. If you do want to deploy CAT yourself, the installation and configuration instructions can be found at [Configuration.md](tutorials/Configuration.md)

Previous Versions of CAT
------------------------
The 1.0.x and 1.1.x versions of CAT were developed on a SVN server of the GEANT project. The code is meanwhile available on a Bitbucket Git instance of the GEANT project: https://bitbucket.software.geant.org/projects/CAT/repos/cat/browse
