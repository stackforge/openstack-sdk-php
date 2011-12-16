The bin/ directory is for executable code used to create or manage the code here.

* bin/ is not considered a release directory. It will not be packaged into a release.
* scripts/ is a release directory. The contents of scripts/ will be included in a release.

Code that is actually part of the package (such as shell wrapper scripts) should be put into the scripts/ directory per the PEAR recommendations.