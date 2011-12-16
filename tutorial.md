# Using the HPCloud PHP API

This tutorial explains how you can use the PHP API to connect to your HP
Cloud services and interact programmatically.

## Object Storage (Swift)

One of the services that HP Cloud offers is called "Object Storage".
This service provides a useful means of storing objects (usually files)
on a service that you control, but that is available to other services
in your cloud (and optionally is availably publically).

This section of the tutorial describes how you can write PHP code to
interact with the Object Storage service.

### Using Stream Wrappers

There are two main methods for accessing HPCloud through this library.
The first is through PHP *stream wrappers*. In PHP, stream wrappers
provide a facility with which you can access various data streams (like
a webpage, the data in a ZIP file, or an HP Cloud object store) as if
they were local files on your file system.

Stream wrappers have a huge advantage for you: You can use the normal
file system functions (`fread()`, `mkdir()`, `file_get_contents()`, etc)
to access things not necessarily on your local filesystem. The HP Cloud
library integrates with this facility of PHP.


### Using the HPCloud Classes

While the stream wrappers are a fantastic way to accomplish many common
tasks, sometimes you need a finer level of control, or you wish to use
an Object-Oriented API. We provide you with the classes you need to work
this way.

(Deep dark secret: Actually, these are the classes that underly the
stream wrappers.)

In this section of the tutorial, we focus on using this API as a
data-access layer.
