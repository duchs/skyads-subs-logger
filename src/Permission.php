<?php
/**
 * Created by PhpStorm.
 * User: ducho
 * Date: 3/26/18
 * Time: 10:54 AM
 */

namespace Skyads;

class Permission
{
    /**
     * Change mod path recursive
     *
     * @param $path
     * @param $fileMode
     *
     * @return bool
     */
    public static function chmodRecursive($path, $fileMode)
    {
        if (!is_dir($path)) {
//            var_dump($path);
//            var_dump($fileMode);exit;
            return chmod($path, $fileMode);
        }

        $dh = opendir($path);
        while (($file = readdir($dh)) !== false) {
            if ($file != '.' && $file != '..') {
                $fullPath = $path.'/'.$file;
                if (is_link($fullPath) || !self::chmodRecursive($fullPath, $fileMode) || (!is_dir($fullPath) && !chmod($fullPath, $fileMode))) {
                    return false;
                }
            }
        }
        closedir($dh);
        if (chmod($path, $fileMode)) {
            return true;
        }

        return false;
    }

    /**
     * Change owner path recursive
     *
     * @param $path
     * @param $owner
     *
     * @return bool
     */
    public static function chownRecursive($path, $owner)
    {
        if (!is_dir($path)) {
            return chown($path, $owner);
        }

        $dh = opendir($path);
        while (($file = readdir($dh)) !== false) {
            if ($file != '.' && $file != '..') {
                $fullPath = $path.'/'.$file;
                if (is_link($fullPath) || (!is_dir($fullPath) && !chown($fullPath, $owner)) || !self::chownRecursive($fullPath, $owner)) {
                    return false;
                }
            }
        }
        closedir($dh);
        if (chown($path, $owner)) {
            return true;
        }

        return false;
    }

    /**
     * Change group path recursive
     *
     * @param $path
     * @param $group
     *
     * @return bool
     */
    public static function chgrpRecursive($path, $group)
    {
        if (!is_dir($path)) {
            return chgrp($path, $group);
        }

        $dh = opendir($path);
        while (($file = readdir($dh)) !== false) {
            if ($file != '.' && $file != '..') {
                $fullPath = $path.'/'.$file;
                if (is_link($fullPath) || (!is_dir($fullPath) && !chgrp($fullPath, $group)) || !self::chgrpRecursive($fullPath, $group)) {
                    return false;
                }
            }
        }

        closedir($dh);
        if (chgrp($path, $group)) {
            return true;
        }

        return false;
    }
}