
# PHP Extension Dependency Detector

This script analyzes PHP source code to determine which extensions are required for a project. It uses `tokenizer` to parse the source code and `Reflection` to figure out which extensions provide functions and classes.

## Features

- Checks functions and classes
- Recursively walks directory trees
- Identifies builtin extensions:
	- You typically do not need to list these in the `require` section of a `composer.json`
	- Note: a builtin extension means the extension is *required* in any PHP build (i.e. always going to be built-in). You may have other extensions that are built into your specific version of PHP that are optional; these optional, built-in extensions are _not_ identified.

## Usage

~~~
usage: depends.php [options] <file-or-directory> [file-or-directory...]

Options:
  --suffix=<suffix1[,suffix2,...]>     Only processes files having suffix(es)
~~~

### Example

~~~
$ php depends.php --suffix .php ~/code/open-source/drupal/core
Core (builtin)
ctype
curl
date (builtin)
dom
filter
hash
iconv
json
libxml
pcre (builtin)
PDO
posix
readline
Reflection (builtin)
session
SimpleXML
SPL (builtin)
standard (builtin)
xml
zlib
~~~

## Limitations

The script does not check global constants at this time. If a project uses constants defined by an extension and nothing else from the extension (i.e. functions or classes), then the extension will not be captured. Note that class constants don't need to be handled specifically since the extension will be captured via the class component of a class constant expression.

