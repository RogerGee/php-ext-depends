<?php

/**
 * depends.php
 *
 * Figures out which extensions are required by a PHP project using tokenizer
 * and Reflection. You must enable the 'tokenizer' extension to run this script.
 *
 * You must run this script using PHP CLI configured with the same extensions as
 * your web server environment.
 *
 * usage: php depends.php <file-or-directory> [another-file-or-directory...]
 *
 * Check Core Extensions for class BuiltIns:
 * https://www.php.net/manual/en/extensions.membership.php
 *
 */

class BuiltIns {
    const EXT_CORE = 'Core';
    const EXT_DATE = 'date';
    const EXT_HASH = 'hash';
    const EXT_JSON = 'json';
    const EXT_PCRE = 'pcre';
    const EXT_RANDOM = 'random';
    const EXT_REFLECTION = 'Reflection';
    const EXT_SPL = 'SPL';
    const EXT_STANDARD = 'standard';

    public static function get() : array {
        $class = new ReflectionClass(get_class());
        $list = array_fill_keys(array_values($class->getConstants()),true);

        // Adjust core extension list based on PHP version.

        $ver = (int)(PHP_MAJOR_VERSION . PHP_MINOR_VERSION);

        if ($ver < 74) {
            unset($list['hash']);
        }

        if ($ver < 80) {
            unset($list['json']);
        }

        if ($ver < 82) {
            unset($list['random']);
        }

        return $list;
    }
}

class FileNotFoundException extends Exception {}
class TokenNotFoundException extends Exception {}

function touch_file(string $file,array $options) : array {
    if (is_file($file)) {
        if (isset($options['suffix'])) {
            while (!is_null(key($options['suffix']))) {
                $suffix = current($options['suffix']);
                if (substr($file,strlen($file)-strlen($suffix)) == $suffix) {
                    break;
                }
                next($options['suffix']);
            }
            $found = !is_null(key($options['suffix']));
            reset($options['suffix']);
            if (!$found) {
                return [];
            }
        }
        return calc_depends(file_get_contents($file));
    }

    if (is_dir($file)) {
        $exts = [];
        $dir = opendir($file);
        while (true) {
            $ent = readdir($dir);
            if ($ent === false) {
                break;
            }

            if ($ent == '.' || $ent == '..') {
                continue;
            }

            $exts += touch_file($file . DIRECTORY_SEPARATOR . $ent,$options);
        }

        return $exts;
    }

    if (!@lstat($file)) {
        throw new FileNotFoundException("'$file' does not exist");
    }

    return [];
}

function calc_depends(string $code) : array {
    $toks = token_get_all($code);

    $i = 0;
    $exts = [];
    while ($i < count($toks)) {
        $item = $toks[$i];
        if (is_array($item)) {
            $name = token_name($item[0]);
            if ($name == 'T_STRING') {
                if (try_function($i,$exts,$item[1],$toks)) {
                    continue;
                }
                if (try_class($i,$exts,$item[1],$toks)) {
                    continue;
                }
                if (try_constant($i,$exts,$item[1],$toks)) {
                    continue;
                }
            }
        }

        $i += 1;
    }

    return $exts;
}

function verify_tok($match,$tok) : bool {
    if (is_string($tok)) {
        $id = null;
        $value = $tok;
    }
    else {
        assert(isset($tok[0],$tok[1]));
        $id = $tok[0];
        $value = $tok[1];
    }

    if (is_array($match)) {
        assert(isset($match[0],$match[1]));
        return ($match[0] == $id) && ($match[1] == $value);
    }

    if (is_int($match)) {
        return ($id == $match);
    }

    return ($value == $match);
}

function seek_tok(int $start,array $toks,int $inc = 1,array $skip = [T_WHITESPACE]) {
    $i = $start;
    while (true) {
        if ($i < 0 || $i >= count($toks)) {
            throw new TokenNotFoundException;
        }

        $tok = $toks[$i];
        if (!in_array($tok[0],$skip)) {
            break;
        }
        $i += $inc;
    }

    return $tok;
}

function try_function(int &$i,array &$exts,string $name,array $toks) : bool {
    try {
        $tok = seek_tok($i + 1,$toks);
        if (!verify_tok('(',$tok)) {
            return false;
        }

        // Make sure name isn't part of function declaration or call expression.
        $tok = seek_tok($i - 1,$toks,-1);
        if (verify_tok([T_OBJECT_OPERATOR,'->'],$tok)
            || verify_tok(T_DOUBLE_COLON,$tok)
            || verify_tok(T_FUNCTION,$tok))
        {
            return false;
        }

        $func = new ReflectionFunction($name);

    } catch (Exception $ex) {
        return false;
    }

    $ext = $func->getExtension();
    if (is_null($ext)) {
        return false;
    }

    $i += 1;
    $exts[$ext->getName()] = true;

    return true;
}

function try_class(int &$i,array &$exts,string $name,array $toks) : bool {
    try {
        // Make sure name isn't part of function declaration or call expression.
        $tok = seek_tok($i - 1,$toks,-1);
        if (verify_tok([T_OBJECT_OPERATOR,'->'],$tok)
            || verify_tok(T_DOUBLE_COLON,$tok)
            || verify_tok(T_FUNCTION,$tok))
        {
            return false;
        }

        $cls = new ReflectionClass($name);

    } catch (Exception $ex) {
        return false;
    }

    $ext = $cls->getExtension();
    if (is_null($ext)) {
        return false;
    }

    $i += 1;
    $exts[$ext->getName()] = true;

    return true;
}

function try_constant(int &$i,array &$exts,string $name,array $toks) : bool {
    // TODO: Will have to enumerate from set of all extensions via
    // ReflectionExtension.
    return false;
}

function parse_options(&$optind,array $argv) : array {
    $OPTIONS = [
        'suffix:',
    ];

    $options = getopt("",$OPTIONS,$optind);
    if (isset($options['suffix'])) {
        $options['suffix'] = explode(',',$options['suffix']);
    }

    return $options;
}

function fail_with_usage() {
    $help =<<<EOF
usage: {$GLOBALS['pathinfo']['basename']} [options] <file-or-directory> [file-or-directory...]

Options:
  --suffix=<suffix1[,suffix2,...]>     Only processes files having suffix(es)
EOF;
    fwrite(STDERR,$help . PHP_EOL);
    exit(1);
}

function main() {
    global $argv;

    if (!isset($argv[1])) {
        fail_with_usage();
    }

    $exts = [];
    $options = parse_options($optind,$argv);
    $fileargs = array_slice($argv,$optind);
    if (empty($fileargs)) {
        fail_with_usage();
    }
    foreach ($fileargs as $arg) {
        $exts += touch_file($arg,$options);
    }
    $exts = array_keys($exts);
    natcasesort($exts);

    $builtIns = BuiltIns::get();
    array_walk($exts,function(&$name) use($builtIns) {
        if (isset($builtIns[$name])) {
            $name .= " (builtin)";
        }
    });
    if (!empty($exts)) {
        print implode(PHP_EOL,$exts) . PHP_EOL;
    }
    else {
        fwrite(
            STDERR,
            "{$GLOBALS['pathinfo']['basename']}: no results" . PHP_EOL
        );
    }
}

try {
    $GLOBALS['pathinfo'] = pathinfo($argv[0]);
    main();
} catch (Exception $ex) {
    fwrite(
        STDERR,
        "{$GLOBALS['pathinfo']['basename']}: " . get_class($ex) . ': ' . $ex->getMessage() . PHP_EOL
    );
    exit(1);
}
