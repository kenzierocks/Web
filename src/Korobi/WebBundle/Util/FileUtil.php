<?php

namespace Korobi\WebBundle\Util;

use Korobi\WebBundle\Exception\SecurityException;

class FileUtil {

    public static function removeRecursively($path) {
        $count = 0;

        if($path == '/' || !is_writable($path)) {
            throw new SecurityException();
        }

        foreach(array_diff(scandir($path), ['.', '..']) as $subpath) {
            $subpath = $path . DIRECTORY_SEPARATOR . $subpath;
            if(is_dir($subpath)) {
                $count += static::removeRecursively($subpath);
            }
        }

        foreach(array_diff(scandir($path), ['.', '..']) as $subpath) {
            unlink($path . DIRECTORY_SEPARATOR . $subpath);
            ++$count;
        }

        rmdir($path);
        return $count + 1;
    }

}
