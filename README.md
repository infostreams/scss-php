This script compiles SCSS files and then stores the resulting CSS in disk
and browser caches for maximum performance. It allows you to use SASS & SCSS
files in your PHP application without a noticeable performance penalty.

Installation
------------
1. Install PHPSASS. This script assumes you have installed PHPSASS with
   Composer, and that the main library can be included from the path defined
   in PHPSASS - by default, "vendor/richthegeek/phpsass/SassParser.php"
   Adjust if necessary.

2. Copy the ```scss.php``` file to your site root

3. Create (or edit) a .htaccess file in that same directory

4. Add the following lines to your .htaccess file:
   ```
   RewriteEngine On
   RewriteRule ^(.*)\.scss$ scss.php?file=$1.scss [L,QSA]
   ```

5. Now include your SCSS files as follows:
   ```html 
    <link rel='stylesheet' href='<your stylesheet>.scss' />
    ```

6. That's it!

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