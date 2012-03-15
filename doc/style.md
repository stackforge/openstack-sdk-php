<?php
/**
 * @file
 * Documentation on style.
 */
/**
 * @page style Coding and Documentation Style Guide
 *
 * @section style_code Code Style
 *
 * The code in this package rigidly conforms to a given coding standard.
 * The standard we use is published <a href="">here</a> and is based
 * on the Drupal coding standards, the PEAR coding standards, and several
 * other popular standards.
 *
 * @section style_documentation Documentation Style
 *
 * This project is documented with Doxygen, and the configuration file used
 * is available in ./config.doxy in this project.
 *
 * The following conventions are used:
 *
 * - In documentation, namespaces are separated with double-colon (::) instead of
 *   backslash characters (\\). This is because backslash characters have
 *   special meaning to Doxygen, and cannot be used as namespace separators.
 * - We use \@retval instead of \@return. This special marker was added to
 *   Doxygen for languages like PHP where the return type is "optional". We
 *   try to always specify a return type, thus we use retval.
 */
