Coding and Documentation Style Guide     {#styleguide}
====================================

This guide explain coding style, coding structure, and documentation
style.

## TL;DR

- Read the [coding standards](https://github.com/mattfarina/Coding-Standards)
  to learn why we code the way we do.
- Read about [PHPDoc](http://www.phpdoc.org/)
  if you're curious about our source code documentation.
- Two spaces, no tabs.
- WE LOVE GITHUB ISSUES AND PULL REQUESTS

## Code Style

The code in this package rigidly conforms to a given coding standard.
The standard we use is published <a href="https://github.com/mattfarina/Coding-Standards">here</a> and is based
on the Drupal coding standards, the PEAR coding standards, and several
other popular standards.

Important highlights:

- Indentation uses *two space characters* and no tabs.
- Variables and class names use CamelCasing (details above).

Please do your best to follow coding standards when submitting patches.

### Object Orientation

We have chosen to give the library a strongly object-oriented flavor.
However, the stream wrapper integration provides a procudural interface
to some of the services.

### Design Patterns and Coding Practices

Where applicable, we use established coding patterns and practices. Some
are PHP specific (like stream wrappers), while most enjoy broader
industry support.

There are a few things a developer should be aware of:

**Accessors and Mutators**: The naming convention for methods on an
object are as follows:

- A function that accesses an object's data is a *noun*. Thus, the color
  of a fictional `Pillow` object may be accessed using
  `Pillow::color()`.
- A function that performs an action is verbal, and this includes
  mutator functions (so-called "setters"). Thus,
  `Pillow::changeColor()`, `Pillow::setColor()`, and `Pillow::fight()` may
  all be appropriate mutator names. 
- Unless a contract (interface or superclass) requires it, we do not ever
  define "getters".

**Constructor Functions**: PHP does not support method overloading
(that is, declaring multiple methods with the same name, but different
signatures). While some languages (viz. Java, C++, C#) allow more than
one constructor, PHP limits you to just one constructor.

One strategy for working around this is to create constructors that take
vague or generic parameters, and then perform various inspection tasks
to figure out what the parameters are:

~~~{.php}
<?php
class Pillow {

  function __construct($name, $a2 = NULL, $a3 = NULL) {

    // ....
    if (is_string($a2)) {
      // Do one thing...
    }
    elseif (is_object($a2)) {
      // Do another thing.
    }
  }
}
?>
~~~

The above quickly degenerates into code that is both slow
(because of the inspection tasks) and difficult to read and use.

Another option, following Objective-C and Vala, is to create constructor
functions. These are static functions (in PHP, at least) that can build
instances. Constructor functions have signatures like
`Pillow::newFromJSON()` and `Pillow::newFromXML()`.

*This library uses constructor functions.* Generally, a very basic
constructor is provided for cases where it is needed, but more complex
cases are handled with specialized constructor functions.

**Namespaces**: The library has been divided up into namespaces
according to the following principles:

- Each broad service category should have a namespace. Currently, the
  service categories are *Services* and *Storage*.
  * Services handle computing tasks on behalf of the client.
  * Storage handles data storage and retrieval
- Individual services and storage services may have their own namespace
  if the number of supporting classes requires this.
- The *Transport* namespace deals with lower-level details that are
  shared across all services.

Otherwise, we have attempted to keep the namespace relatively shallow.

### Balancing Performance and Elegance

Any network-based library must struggle with the inefficiencies of
working over a network. This library is no exception. We've done our
best to straddle the line between keeping load times down and making the
code simple and elegant. Doubtless we have sometimes failed. Please feel
free to suggest improvements on either side of the scales.

## Documentation Style

This project is documented with PHPDoc.
