# Using the OpenStack PHP-CLient API

This tutorial explains how you can use the PHP API to connect to your OpenStack
services and interact programmatically.

## Object Storage (Swift)

One of the services that OpenStack offers is called "Object Storage".
This service provides a useful means of storing objects (usually files)
on a service that you control, but that is available to other services
in your cloud (and optionally is availably publically).

This section of the tutorial describes how you can write PHP code to
interact with the Object Storage service.

## Authenticating to Object Storage

There are two ways to authenticate to Object Storage:

- Legacy Swift authentication
- Control Services authentication

For legacy swift authentication, you will need to use your Tenant ID, your username,
and your password, along with the URL to the Object Storage endpoint.

### Using Stream Wrappers

There are two main methods for accessing OpenStack through this library.
The first is through PHP *stream wrappers*. In PHP, stream wrappers
provide a facility with which you can access various data streams (like
a webpage, the data in a ZIP file, or an OpenStack object store) as if
they were local files on your file system.

Stream wrappers have a huge advantage for you: You can use the normal
file system functions (`fread()`, `mkdir()`, `file_get_contents()`, etc)
to access things not necessarily on your local filesystem. The PHP-Client
library integrates with this facility of PHP.


### Using the PHP-Client Classes

While the stream wrappers are a fantastic way to accomplish many common
tasks, sometimes you need a finer level of control, or you wish to use
an Object-Oriented API. We provide you with the classes you need to work
this way.

(Deep dark secret: Actually, these are the classes that underly the
stream wrappers.)

In this section of the tutorial, we focus on using this API as a
data-access layer.

#### Main Classes

- \OpenStack\Bootstrap: Provides services for bootstrapping the library.
  It's not necessary, but it can be helpful.
- \OpenStack\ObjectStorage: The main interface to the OpenStack object
  storage.

## Slightly Irreverant Glossary

*Tenant ID:* You service provider will provide you with
an account ID and a secret key, along with a URL, that can be used to
access the cloud APIs.

*Container:* A namespace extension useful for differentiating object
space with pseudo-containment logical units. Or, a directory. (see
_Object Storage_)

*Object Storage:* A service provided by OpenStack that allows you to store
entire files on the cloud. Files can be organized into _containers_, which are
rough analogs to file system directories (or folders).


