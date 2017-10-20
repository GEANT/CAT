CAT, the Configuration Assistant Tool for Enterprise Wi-Fi networks such as eduroam
===================================================================================

CAT collects information about RADIUS/EAP deployments from Wi-Fi network administrators and generates simple-to-use, good-looking, and secure installation programs for users of these networks. The goal is to vastly improve the network security by pushing secure Wi-Fi settings to all users without the need to expose them to or require them to understand all of the underlying technologies and configuration parameters.

[![Code Climate](https://codeclimate.com/github/GEANT/CAT/badges/gpa.svg)](https://codeclimate.com/github/GEANT/CAT)

eduroam CAT User Manuals
------------------------
The flagship of CAT, the eduroam CAT (https://cat.eduroam.org), has extensive documentation (with screenshots!) of CAT. You may want to read those for an overview of the features.

[eduroam CAT federation administrator documentation](https://wiki.geant.org/display/H2eduroam/A+guide+to+eduroam+CAT+for+federation+administrators)

[eduroam CAT institution administrator documentation](https://wiki.geant.org/display/H2eduroam/A+guide+to+eduroam+CAT+for+institution+administrators)

There is no documentation for end users, simply because it's so easy to use on the end-user side that no documentation is required! :-)

Known deployments
-----------------
There are three known production deployments of CAT (please let us know if you are deploying the software and want to be on this list!).

* *eduroam CAT* https://cat.eduroam.org (for all eduroam institutions and users world-wide, except Germany)
* *DFN eduroam CAT* https://cat.eduroam.de (for eduroam institutions in Germany and their users)
* *Enterprise Network CAT* https://802.1x-config.org (for enterprise networks unrelated to eduroam)

Installation and configuration of your own deployment
-----------------------------------------------------
With the production deployments as listed above, there are probably few use cases you would want to run your own installation. If you do want to deploy CAT yourself, the installation and configuration instructions can be found at [Configuration.md](tutorials/Configuration.md)

Previous Versions of CAT
------------------------
The 1.0.x and 1.1.x versions of CAT were developed on a SVN server of the GEANT project. Please refer to https://forge.geant.net/forge/display/CAT/Home for access to the source code and release tarballs of these earlier versions.
