<?php

// change vars here
$cf = 0;                // set to 1 if you are using cloudflare
$debug = 0;             // set to 1 if you wish to see execution time and cache actions

$start = microtime();   // start timing page exec

// if cloudflare is enabled
if ($cf) {
    if (isset($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        $_SERVER['REMOTE_ADDR'] = $_SERVER['HTTP_CF_CONNECTING_IP'];
    }
}

// from wp
define('WP_USE_THEMES', true);

// init predis
include("predis.php");
$redis = new Predis\Client('');

// init vars
$domain = $_SERVER['HTTP_HOST'];
$url = "http://".$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
$url = str_replace('?r=y', '', $url);
$url = str_replace('?c=y', '', $url);
$dkey = md5($domain);
$ukey = md5($url);

// check if page isn't a comment submission
(($_SERVER['HTTP_CACHE_CONTROL'] == 'max-age=0') ? $submit = 1 : $submit = 0);

// check if logged in to wp
$cookie = var_export($_COOKIE, true);
$loggedin = preg_match("/wordpress_logged_in/", $cookie);

// check if a cache of the page exists
if ($redis->hexists($dkey, $ukey) && !$loggedin && !$submit) {

    echo $redis->hget($dkey, $ukey);
    if (!$debug) exit(0);
    $msg = 'this is a cache';

// if a comment was submitted or clear page cache request was made delete cache of page
} else if ($submit || substr($_SERVER['REQUEST_URI'], -4) == '?r=y') {

    require('./wp-blog-header.php');
    $redis->hdel($dkey, $ukey);
    $msg = 'cache of page deleted';

// delete entire cache, works only if logged in
} else if ($loggedin && substr($_SERVER['REQUEST_URI'], -4) == '?c=y') {

    require('./wp-blog-header.php');
    if ($redis->exists($dkey)) {
        $redis->del($dkey);
        $msg = 'domain cache flushed';
    } else {
        $msg = 'no cache to flush';
    }

// if logged in don't cache anything
} else if ($loggedin) {

    require('./wp-blog-header.php');
    $msg = 'not cached';

// cache the page
} else {

    // turn on output buffering
    ob_start();

    require('./wp-blog-header.php');

    // get contents of output buffer
    $html = ob_get_contents();

    // clean output buffer
    ob_end_clean();
    echo $html;

    // store html contents to redis cache
    $redis->hset($dkey, $ukey, $html);
    $msg = 'cache is set';
}

$end = microtime(); // get end execution time

// show messages if debug is enabled
if ($debug) {
    echo $msg.': ';
    echo t_exec($start, $end);
}

// time diff
function t_exec($start, $end) {
    $t = (getmicrotime($end) - getmicrotime($start));
    return round($t,5);
}

// get time
function getmicrotime($t) {
    list($usec, $sec) = explode(" ",$t);
    return ((float)$usec + (float)$sec);
}

?>