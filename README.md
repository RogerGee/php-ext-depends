# PHP Extension Dependency Detector

This script analyzes PHP source code to determine which extensions are required for a project. It uses `tokenizer` to parse the source code and `Reflection` to figure out which extensions provide functions and classes.

## Features

- Checks functions and classes
- Recursively walks directory trees
- Identifies builtin extensions (which typically do not need to be identified in the `require` section of a `composer.json`)

## Example

~~~
$ php depends.php ~/projects/drupal/core
Core (builtin)
ctype
curl
date (builtin)
dom
filter
gd
hash
iconv
intl
json
libxml
mbstring
pcre (builtin)
PDO
Phar
readline
Reflection (builtin)
session
SimpleXML
SPL (builtin)
standard (builtin)
tokenizer
xml
zip
zlib
~~~

## Limitations

Currently, the script does not check global constants. If a project uses constants defined by an extension and nothing else from the extension (i.e. functions or classes), then the extension will not be captured. Note that class constants don't need to be handled since the class is a part of a class constant expression.

