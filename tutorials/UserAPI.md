Using CAT access API
======================
Author: Tomasz Wolinewicz, twoln@umk.pl

We document how you can access CAT data without using the provided GUIs

Introduction
------------

Various programs may want to access CAT data thus becoming another CAT UI.
CAT access API is here to enable and ease this access. Please be aware that CAT
data is restricted by Terms of Use. The link to ToU is always sent within CAT results.
Please respect the rules.

Data access is provided by HTTP calls to the script <em>user/API.php</em>. You may use both GET and POST.
The main mandatory argument is <emphasis>action</emphasis> which tells CAT what you require.
Since most data is language-specific, you should normally specify
the <emphasis>lang</emphasis> argument as well.
The list of available languages and their identifiers is available via
the <emphasis>listLanguages</emphasis> call.
In previous versions of CAT, most actions used and <emphasis>id</emphasis> argument with its meaning
depending on the context. This turned out to be impractical, therefore starting from version 1.2
the <emphasis>id</emphasis> argument has been replaced by various arguments whose names are self-explanatory.
The old behaviour is still supported for backwards compatibility, but may be removed in the future.
Also for backwards compatibility, by default the API is run in version 1 mode, you can specify version 2 behaviour
by setting api_version to value 2,

This documentation is for version 2 of the API.

Most calls require an additional argument as well, where you need to provide
an internal CAT identifier of the object that you require. The values of these identifiers
are available via the CAT list calls.

List of available actions
-------------------------
* listLanguages
* listCountries
* listAllIdentityProviders
* listIdentityProviders
* listProfiles
* profileAttributes
* listDevices
* generateInstaller
* downloadInstaller
* sendLogo

JSON structure
--------------

With the exception of sendLogo, which sends an image, data returned by each called is sent as a JSON encoded array.
The main array has three entries: <emphasis>status</emphasis>, <emphasis>data</emphasis> and <emphasis>tou</emphasis>.
The <emphasis>status</emphasis> entry returns the success or failure of the call. If <emphasis>status</emphasis> equals <emphasis>0</emphasis> then the call failes and <emphasis>data</emphasis> could contain an error message. If <emphasis>status</emphasis> equals <emphasis>1</emphasis> then <emphasis>data</emphasis> will contain all returned information.
The <emphasis>tou</emphasis> entry is just the Terms of Use statement.

In most cases <emphasis>data</emphasis> is an array listing requested objects like languages, countries, IdPs, profiles. See the decriptions of individual actions for more information on the <emphasis>data</emphasis> structure.

Actions in detail
-----------------

* listLanguages
  - mandatory arguments:
    + none
  - optional arguments:
    + none
  - data:
    + Array of triples: {"lang", "display", "locale"}.

* listCountries
  - mandatory arguments:
    + none
  - optional arguments:
    + lang
  - data:
    + Array of tuples: {"federation", "display"}.
* listAllIdentityProviders
  - mandatory arguments:
    + none
  - optional arguments:
    + lang
  - data:
    + The main purpose of this action is to provide listing for DiscoJuice, therefore
      the structure of the result is tailored to DiscoJuice's needs.
      The result is an array of tuples {"entityID", "title", "country", "geo", "icon", "idp"}.
      <emphasis>geo</emphasis> and <emphasis>icon</emphasis> are optional. <emphasis>idp</emphasis>
      is provided for conformance reasons, but is just a copy of <emphasis>entityID</emphasis>.
      <emphasis>geo</emphasis> can be either a {"lon", "lat"} tuple or an array of such tupples.

* listIdentityProviders
  - mandatory arguments:
    + federation - the identifier of a country to be listed.
  - optional arguments:
    + lang
  - data:
    + Array of tuples: {"idp", "display"}.

* listProfiles
    - mandatory arguments:
      + idp - the identifier of an IdP
    - optional arguments:
      + lang
      + sort - if equal to 1 sort profiles by name (case-ignore)
    - data:
      + Array of tuples: {"profile", "display", "idp_name", "logo"}.
      <emphasis>logo</emphasis> can be <emphasis>0</emphasis> or <emphasis>1</emphasis> and
      shows if logo is available.

* profileAttributes
    - mandatory arguments:
      + profile - the identifier of the profile to be shown
    - optional arguments:
      + lang
    - data:
      + Array of tuples: {"local_email", "local_phone", "local_url", "description", "devices"}.
      All <emphasis>local_</emphasis> entries  and <emphasis>description</emphasis> are optional.
      <emphasis>devices</emphasis> is an array of tuples {"id", "display", "status", "redirect",
      "eap_customtext", "device_customtext"}.
* listDevices
    - mandatory arguments:
      + profile - the identifier of the profile for which the devices will be listed
    - optional arguments:
      + lang
    - data:
      + array of touples {"device", "display", "status", "redirect", "eap_customtext", "device_customtext"}.
* generateInstaller
    - mandatory arguments:
      + device - identifier of the device; profile - identifier of the profile
    - optional arguments:
      + lang
    - data:
      + array of tuples {"profile", "device", "link", "mime"}.
* downloadInstaller
    - mandatory arguments:
      + device - identifier of the device; profile - identifier of the profile
    - optional arguments:
      + lang
      + generatedfor - either 'user' or 'admin' - defaults to user
    - data:
      + installer file
* sendLogo
    - mandatory arguments:
      + idp - the identifier of the identity provider
    - optional arguments:
      + lang
    - data:
      + logo image
