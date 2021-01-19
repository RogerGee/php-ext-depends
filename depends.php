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
 */

class BuiltIns {
    const EXT_CODE = 'Core';
    const EXT_DATE = 'date';
    const EXT_PCRE = 'pcre';
    const EXT_REFLECTION = 'Reflection';
    const EXT_SPL = 'SPL';
    const EXT_STANDARD = 'standard';

    public static function get() : array {
        $class = new ReflectionClass(get_class());
        return array_fill_keys(array_values($class->getConstants()),true);
    }
}

function touch_file(string $file) : array {
    if (is_file($file)) {
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

            $exts += touch_file($file . DIRECTORY_SEPARATOR . $ent);
        }

        return $exts;
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

function find_tok($match,int $index,array $toks) {
    if ($index < 0 || $index >= count($toks)) {
        return false;
    }

    $tok = $toks[$index];
    if (is_string($tok)) {
        $id = null;
        $value = $tok;
    }
    else {
        $id = $tok[0];
        $value = $tok[1];
    }

    $result = false;
    if (is_array($match)) {
        assert(isset($match[0],$match[1]));
        $result = (token_name($match[0]) == $id) && ($match[1] == $value);
    }
    else {
        $result = ($value == $match);
    }

    return $result;
}

function try_function(int &$i,array &$exts,string $name,array $toks) : bool {
    if (!find_tok('(',$i + 1,$toks)) {
        return false;
    }

    try {
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

function main() {
    global $argv;

    if (!isset($argv[1])) {
        fwrite(STDERR,"usage: php depends.php <file-or-directory> [file-or-directory...]");
    }

    $exts = [];
    foreach (array_slice($argv,1) as $arg) {
        $exts += touch_file($arg);
    }
    $exts = array_keys($exts);
    natcasesort($exts);

    $builtIns = BuiltIns::get();
    array_walk($exts,function(&$name) use($builtIns) {
        if (isset($builtIns[$name])) {
            $name .= " (builtin)";
        }
    });
    print implode(PHP_EOL,$exts) . PHP_EOL;
}

main();
