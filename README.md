This script compiles SCSS files and then stores the resulting CSS in disk
and browser caches for maximum performance. It allows you to use SASS & SCSS
files in your PHP application without a noticeable performance penalty.

Installation
------------
1. Install [Composer](http://getcomposer.org/) in your site's document root. 
   From the command line, you can do this with 
   ```curl -sS https://getcomposer.org/installer | php```, or see the 
   instructions on the [Composer website](http://getcomposer.org/).

2. Copy ```composer.json```, ```scss.php``` and ```.htaccess``` to that same 
   directory.

3. Run, again from that same directory, ```php composer.phar install```

4. Done! You can now include your SCSS files as follows:
   ```html 
    <link rel='stylesheet' href='<your stylesheet>.scss' />
    ```

Alternate installation
----------------------
1. Download [PHPSASS](https://github.com/richthegeek/phpsass), and install 
   it in your site root in a directory called 'vendor/richthegeek/phpsass/',
   in such a way that 'vendor/richthegeek/phpsass/SassParser.php' exists.

2. Copy the ```scss.php``` and ```.htaccess``` files to your site root

3. Done! You can now include your SCSS files as follows:
   ```html 
    <link rel='stylesheet' href='<your stylesheet>.scss' />
    ```

This script will compile the SCSS file once, and then cache the resulting
output to disk. If the underlying SCSS file changes, the cached disk copy
is invalidated and a new version is compiled.

Any compiled SCSS file that has been delivered to the user will be cached
for 30 days, or until the next time you change your SCSS file -- whichever
comes first. The stylesheet is served only once for each user.
However, the user will get a new version of your stylesheet immediately if
you edit one of the underlying SCSS files, or if you change one of the
options that are fed to the PHPSASS parser.

For performance reasons, this script will only look at the 'file modified'
times of the requested SCSS file itself, and of all the SCSS files that you
'@import' from that file (only works in PHP5.3 and later), to determine if 
it needs to compile the SCSS, or if it just needs to serve a previously 
compiled version. If you make a change to a file that is imported from an 
imported file, then this script will not detect that change. In such cases, 
make sure to change the 'file modified' time of the root file (e.g. by 
adding and removing a space and then re-saving the file). That, or switch 
off caching during development (set 'ENABLE_CACHING' to FALSE).

If (for whatever reason) you want to force a recompile of the SCSS code and
throwout everything that's been cached server-side, you can call this script
with an additional '?reset=1', '?force=1' or '?clear=1', for example like in 
http://example.com/example.scss?reset=1