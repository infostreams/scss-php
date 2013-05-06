<?php
/**
 * This script compiles SCSS files and then stores the resulting CSS in disk
 * and browser caches for maximum performance. It allows you to use SASS & SCSS
 * files in your PHP application without a noticeable performance penalty.
 *
 * INSTALLATION
 * 1. Install PHP Composer (from http://getcomposer.org/) in your site's
 *    document root. From the command line, you can do this with
 *       "curl -sS https://getcomposer.org/installer | php", or see the
 *    instructions on the Composer website -- http://getcomposer.org/.
 *
 * 2. Copy "composer.json", "scss.php" and ".htaccess" to that same
 *    directory.
 *
 * 3. Run, again from that same directory, "php composer.phar install"
 *
 * 4. Done! You can now include your SCSS files as follows:
 *     <link rel='stylesheet' href='<your stylesheet>.scss' />
 *
 * This script will compile the SCSS file once, and then cache the resulting
 * output to disk. If the underlying SCSS file changes, the cached disk copy
 * is invalidated and a new version is compiled.
 *
 * Any compiled SCSS file that has been delivered to the user will be cached
 * for 30 days, or until the next time you change your SCSS file -- whichever
 * comes first. The stylesheet is served only once for each user.
 * However, the user will get a new version of your stylesheet immediately if
 * you edit one of the underlying SCSS files, or if you change one of the
 * options that are fed to the PHPSASS parser.
 *
 * For performance reasons, this script will only look at the 'file modified'
 * times of the requested SCSS file itself, and of all the SCSS files that you
 * '@import' from that file (only works in PHP5.3 and later), to determine if 
 * it needs to compile the SCSS, or if it just needs to serve a previously 
 * compiled version. If you make a change to a file that is imported from an 
 * imported file, then this script will not detect that change. In such cases, 
 * make sure to change the 'file modified' time of the root file (e.g. by 
 * adding and removing a space and then re-saving the file). That, or switch 
 * off caching during development (set 'ENABLE_CACHING' to FALSE).
 *
 * If (for whatever reason) you want to force a recompile of the SCSS code and
 * throwout everything that's been cached server-side, you can call this script
 * with an additional '?reset=1', '?force=1' or '?clear=1', for example like in 
 * http://example.com/example.scss?reset=1
 *
 * @author Edward Akerboom - github@infostreams.net
 * @version 1.0
 * @since 2013-05-06
 */

define("ENABLE_CACHING", TRUE);
define("CACHE_DIR", sys_get_temp_dir());

define("PHPSASS", 'vendor/richthegeek/phpsass/SassParser.php');

$day = 24 * 60 * 60;
define("MAX_AGE", 30 * $day);

$options = array(
    'style' => 'expanded', // compact, expanded, or compressed
    'cache' => FALSE,
    'syntax' => 'scss',
    'debug' => FALSE,
    'debug_info' => FALSE,
    'functions' => array(
        'rand' => 'rand',
        'pow' => 'pow',
        'sqrt' => 'sqrt',
        'sin' => 'sin',
        'cos' => 'cos',
    ),
);

header('Content-type: text/css');

try {
    $file = $_GET["file"];

    // Initialize the compiler and parse the root file
    require_once (PHPSASS);
    $parser = new SassParser($options);
    $tree = $parser->parse($file);

    // Make sure $css is defined even when caching is off
    $css = null;

    if (ENABLE_CACHING) {
        // now get the 'last modified' timestamp for each file that is included from that root file
        // -> that way, we know when to re-calculate the output CSS
        $modified = filemtime($file);
        foreach ($tree->children as $child) {
            if ($child instanceof SassImportNode) {
                // this is an 'import' statement
                //
                // get a list of imported files, from the (unfortunately 'private') property "files"
                $r = new ReflectionClass($child);
                $prop = $r->getProperty("files");

                if (method_exists($prop, "setAccessible")) {
                    // only works in PHP5.3+
                    $prop->setAccessible(true);
                    $files = $prop->getValue($child);

                    foreach ($files as $f) {
                        // for each of the imported files, determine when it was last changed
                        $paths = SassFile::get_file($f, $parser);
                        foreach ($paths as $p) {
                            $modified .= ",$p=" . filemtime($p);
                        }
                    }
                }
            }
        }

        // now we generate a fingerprint ('ETag') for the content. The fingerprint will change
        // if one of the options changes, or if one of the imported files changes
        $etag = sha1($modified . "::" . serialize($options));

        // Now check if the browser has indicated that a file with this fingerprint is already in its cache
        if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && $_SERVER['HTTP_IF_NONE_MATCH'] == $etag) {
            // yes -> stop here, and say that nothing has changed.
            header('HTTP/1.1 304 Not Modified');
            exit();
        }

        // Ok, user does not yet have a copy
        //
        // try to find a compiled copy in our disk cache
        $filename_pattern = rtrim(CACHE_DIR, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . "phpsass-cache-";
        $cached_filename = $filename_pattern . "$etag.css";
        if (is_dir(CACHE_DIR)) {
            // cache dir exists
            $force = isset($_GET["clear"]) || isset($_GET["reset"]) || isset($_GET["force"]);
            if (mt_rand(0, 1000)<100 || $force) {
                // clean up older files every once in a while
                //
                // Make sure to run this code relatively often, because the user
                // gets to this point in the code only if he/she doesn't have a
                // version of the compiled CSS in the browser cache yet
                $files = glob($filename_pattern . "*.css");
                $latest_allowed = time() - MAX_AGE;
                foreach ($files as $f) {
                    if (is_writeable($f) && ($force || (filemtime($f)<$latest_allowed))) {
                        @unlink($f);
                    }
                }
            }

            // check if we have a cached copy of the file
            if (file_exists($cached_filename)) {
                // yup - use it
                $css = file_get_contents($cached_filename);
            }
        }
    }

    if (is_null($css)) {
        // we didn't obtain the CSS yet
        // -> run the compiler
        $css = $tree->render();

        // try to write the resulting CSS to the disk cache
        if (ENABLE_CACHING && is_dir(CACHE_DIR) && is_writable(CACHE_DIR)) {
            @file_put_contents($cached_filename, $css);
        }
    }

    // finally, send the file and say that the browser can keep it for a while
    if (ENABLE_CACHING) {
        header("Expires: " . gmdate('D, d M Y H:i:s', time()+MAX_AGE));
        header("Pragma: cache");
        header("Cache-Control: max-age=" . MAX_AGE);
        header("ETag: $etag");
    }
    echo $css;
}
catch (Exception $e) {
    print "body::before {
      display: block;
      padding: 5px;
      white-space: pre;
      font-family: monospace;
      font-size: 8pt;
      line-height: 17px;
      overflow: hidden;
      content: '" . $e->getMessage() . "';
}";
}
