<?php
/**
 * Created by PhpStorm.
 * User: kryoz
 * Date: 05.10.14
 * Time: 12:44
 */

namespace Homer;


class ConnectionCounter {
    private static $connCounter = 0;
    private static $queue = 0;

    public static function getCount()
    {
        return static::$connCounter;
    }

    public static function incConnection()
    {
        static::$connCounter++;
    }

    public static function decConnection()
    {
        static::$connCounter--;
    }

    public static function getQueueCount()
    {
        return static::$queue;
    }

    public static function incQueue()
    {
        static::$queue++;
    }

    public static function decQueue()
    {
        static::$queue--;
    }
} 