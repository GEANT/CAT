Governance
==========

Preface
-------
CAT development is funded by a series of projects in the European Commission's
Framework programmes since over a decade (spanning 'Framework Programme 7',
'Horizon Europe' and already confirmed for the upcoming 'Horizon' programme). 
It is an integral part of the funding for the roaming consortium 'eduroam',
with the aim to enable and facilitate global federated Wi-Fi roaming with 
enterprise-grade security.

Mission Statement
-----------------
CAT improves the onboarding process and experience for WPA-Enterprise networks
in general, and for eduroam users and administrators in particular. Improvements
are along the axes of convenience and security, where security improvements
always take precedence over convenience.

With CAT, eduroam end users receive state-of-the-art secure onboarding for 
wireless and wired network connections. 
eduroam IdP administrators receive tooling to facilitate easy onboarding and 
local server configuration validation.
eduroam SP administrators receive tooling to verify eduroam user support issues.
NRENs receive a multi-level tenancy system that allows them to manage their 
eduroam IdPs' and eduroam SPs' participation in CAT.

Given that eduroam networks are merely a specific deployment profile of WPA-
Enterprise networks in general, the CAT team strives to create a product that
can be used in any other Enterprise Wi-Fi deployment scenario, by making
eduroam-specific features configurable.

Stakeholders
------------
CAT stakeholders are
* primarily: the National Research and Education Networks of the planet (NRENs)
* by extension: the Research and Education Institutions connected to the NRENs
  which choose to participate in the eduroam roaming consortium as Identity
  Providers (eduroam IdPs) and/or Service Providers (eduroam SPs)
* by further extension: all eduroam end users whose eduroam IdP or visited SP
  has chosen to opt towards using CAT services

As an indication of size: as of mid 2022, 68 NRENs are registered in CAT; 
approx. 4000 IdP/SP organisations have signed up; an estimate of multiple 
million end users are using CAT services.

The CAT development team stays in contact with its stakeholders via the
following means:
* CAT-specific mailing lists
  - cat-users@lists.geant.org (end users, IdP/SP administrators, NREN personnel
    open mailing list)
    https://lists.geant.org/sympa/subscribe/cat-users
  - cat-devel@lists.geant.org (CAT development team including translators, open
    mailing list)
    https://lists.geant.org/sympa/subscribe/cat-devel
  - cat-core-devel@lists.geant.org (CAT core development team, open mailing 
    list)
    https://lists.geant.org/sympa/subscribe/cat-core-devel
  - cat-announce@lists.geant.org (CAT announcements, open mailing list)
    https://lists.geant.org/sympa/subscribe/cat-announce
* eduroam NREN mailing lists
  - eduroam@lists.geant.org (NRENs in Europe, closed list, regular VC meetings)
  - gegc@lists.geant.org (Global eduroam Governance Committee, closed list)
  - development@lists.eduroam.org (open mailing list, regular VC meetings)
    https://lists.eduroam.org/sympa/subscribe/development
* Slack channel
  https://eduroam.slack.com

Requirements Engineering
------------------------
Requirements stemming from the stakeholder community are collected from the 
stakeholders identified above using the communications channels identified 
above.

Furthermore, members of the development team are also integrated into the
eduroam technical development environment, with memberships in relevant industry
fora such as Wi-Fi Alliance (WFA) and Wireless Broadband Alliance (WBA). Outputs
from these fora that imply a need for change are ingested from there and become
feature suggestions, which are subsequently discussed and validated with the
stakeholder community.

Validation of outcomes
----------------------
New releases of CAT are first deployed into a dedicated test instance, with an
announcement requesting stakeholders to test these versions, both regarding
whether new features were implemented correctly, and whether there are any
regressions in existing functionality.

IPR and Data Protection
-----------------------
The CAT source code is audited for its licensing dependencies and potential 
issues in the larger framework of the GEANT software assurance task. Major
releases of CAT require a sign-off by the GEANT IPR coordinator prior to 
release.

The CAT source code is written with a data minimisation by design approach. Only
the minimum extent possible of personal information is retained. As an example,
end user information is only logged in the context of web server access logs;
private keys generated in the context of eduroam Managed IdP functionality are
strictly only kept in memory, etc.

For all system users, only a pseudonym (eduPersonTargetedId or equivalent) and
information about how they got enrolled into the system is retained. 

For the specific software deployment on https://cat.eduroam.org, a Privacy
Statement was created in cooperation with the GEANT GDPR coordinator, and is
linked to from the deployment's entry page.

Team Organisation
-----------------
The development team currently consists of three core members, who are in 
regular contact regarding the CAT product. Decisions are typically taken
unanimously after thorough discussion of the best option and consequences for
the product and the stakeholders.

A significant amount of translation team members is available to translate the
prose strings in the product into over 10 languages. The translation team
members are called to action via the cat-devel mailing list as a release comes
close.

Translations are done on the third-party platform Transifex. The CAT team
homepage is here: https://www.transifex.com/eduroam_devel/

New languages are accepted into the releases according to the following rule:
end-user visible areas need to be fully translated (language catalogues "core",
"web_user" and "devices". Once a language is included in any patchlevel release
of a given "major.minor" release train it will continue to be included even if
translation completeness drops below 100% for these end-user facing areas. When
a new minor or major version release train is started, all languages are
considered "new" and need to fulfill the inclusion rule.

Code Quality
============

Preface
-------
CAT has chosen GitHub to host its source code, website and some Continuous
Integration actions. Up to version 1.1, a Git repository internal to the GEANT
project was used, but discontinued for transparency reasons and superior
functionality and third-party integrations available in GitHub.

The development team is aware of past and current criticism on the GitHub
platform and is continuously monitoring whether the platform introduced an 
unacceptable impact on further development.

Design and Implementation
--------------
CAT is written in object-oriented PHP. New releases are constantly adapted to
new PHP versions.

CAT uses a modular design wherever possible. The product consists of distinct
areas for NREN/NRO management levels, IdP/SP management levels, and end user
areas. Installers are generated for a multitude of platforms, all of which have
their own device module. The language and format of the installers is tailored
to the capabilities and needs of the target operating system. E.g., for Windows,
a code-signed executable is generated, while Apple devices are served a signed 
XML file for consumption with the Preferences app.

Further details about the various areas, modules in place, their interaction,
and dependencies with external components, are available in the "CAT Project
Overview.odp" presentation in this folder.

Source code is documented extensively, and all classes and functions have
sufficient information to generate automated PHPDoc documentation. Coding 
strives to follow PHP PSR nomenclatures and standards closely. The Scrutinizer
CI checks are parametrised to raise issues when observing non-conformities in
code documentation.

Quality Assurance
-----------------
CAT source code is checked on every code push with a suite of Continuous 
Integration checks, using the CI/CD tool "Scrutinizer". Scrutinizer's checks
include a static code analysis (including security analysis) and containerized
build check scripts that verify large parts of the product's building blocks
(see the file .scrutinizer.yml in the root directory).

The results of CI checks and code analysis are public at 
https://scrutinizer-ci.com/g/GEANT/CAT/?branch=release_2_1

In addition, there are a number of additional installer checks configured as
GitHub actions.

All developers are encouraged to review the Scrutinizer CI and GitHub Action
results on their own (they receive automated mails if any breakage is 
introduced). In addition, prior to packaging a release, all outstanding issues
are reviewed by a Dev team member.

Build, Delivery and Deployment
------------------------------
CAT does not follow the Continuous Deployment paradigm. Releases are 
traditionally packaged "tarball" style releases, deployed manually.

Deployment is following DevOps paradigms where the Dev part of the team prepares
the release tarball, and the Ops part of the team uses that tarball for 
deployment on test and later production systems. Tests are announced on the 
various stakeholder mailing lists and sufficient time is given to all interested
parties to find regressions or inspect functionality and UI changes.

Upon successful testing, the Ops team deploys the source code of a new version
on the various eduroam-owned deployments, which are currently:
* cat.eduroam.org (per institution RADIUS configuration / installers)
* hosted.eduroam.org (eduroam "Managed IdP" per-user installers)
* msp-pilot.eduroam.org (eduroam "Managed SP" - system in pilot phase)

Deployment planning takes the academic year into account - releases are planned
during the large semester holidays to minimise impact and allow slow ramp-up
of tool usage after an update.

Change Management
-----------------
Issues are tracked using GitHub's Issue Tracker functionality. Bug reports and
feature requests are also ingested informally from the product mailing lists;
the developers typically suggest reporters to raise a GitHub issue to facilitate
tracking of requests.
