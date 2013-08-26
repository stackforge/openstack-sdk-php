# Release Notes

This changelog contains the relevant feature additions and bug fixes. To obtain a complete diff between versions you can got to https://github.com/hpcloud/HPCloud-PHP/compare/XXX...XXX where the XXX values are two different tagged versions of the library. For example, https://github.com/hpcloud/HPCloud-PHP/compare/1.0.0-beta6...1.0.0

* 1.2.1 (2013-09-26)

  * Disabling HTTP 1.1 keep-alive for the PHP transport layer. PHP doesn't support keep alive.
  * Fixed CDN::newFromIdentity to pass on the region to enable multi-region support.
  * Updated the CREDITS and contact information for Matt Butcher.

* 1.2.0

  * ObjectStorage::newFromIdentity now works for multiple regions.
  * Added classes for DBaaS Flavor and FlavorDetails.
  * Fixed DBaaS Instance creation to use the new flavor setup.

* 1.1.0

  * DBaaS::newFromIdentity was modified to support the new base URL
    format.
  * All newFromIdentity() constructor functions now support $region
    settings.
  * Proxy configuration has been added to CURLTransport.
  * Fixed autoloader for Windows.

* 1.0.0 (2012-08-09)

  * This is the initial stable release for object storage, CDN, and identity services. DBaaS is currently in private beta as such the bindings for this component are still in beta.
