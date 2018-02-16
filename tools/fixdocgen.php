<?php
/*
  Hack to fix docgen output for namespaced PHP extensions, at least this one.
  Generalized it a bit so it might be useful for someone else but no promises.

  @todo fix docgen for namespaced extensions

  Usage:
  - Use docgen to create docbook skeletons
  - Execute this in the output directory

  Simple untested instructions :)

   $ svn checkout https://svn.php.net/repository/phpdoc/modules/doc-en phpdoc-en
   $ cd phpdoc-en/doc-base/scripts
   $ php docgen/docgen.php -e phpextensionname
   $ cp ~/whateverthisis/fix.php output/
   $ cd output
   $ php fix.php

   $ cd ../..
   $ php doc-base/configure.php
   $ phd -d doc-base/.manual.xml -P PHP -f xhtml
   $ firefox output/php-chunked-xhtml/index.html &
*/

// PHP documentation uses - in some places and _ in others... _usually_ it's - :)
$myextension = 'mysql-xdevapi';

if (!file_exists('book.xml')) {
    echo "Error: I am poorly written and must be executed in the directory with book.xml so will exit now\n";
    exit;
}

// Fix entity names
// Each class file has an incorrect entity name so this fixes that
$it = new RecursiveDirectoryIterator('.');
foreach(new RecursiveIteratorIterator($it) as $file) {
    if ($file->getExtension() === 'xml') {
        $contents = file_get_contents($file);
        preg_match('@&reference\.'. $myextension .'\.entities\.'. $myextension .'-(.*)@', $contents, $matches);
        if (!empty($matches[1])) {
            $contents = str_replace(
                '&reference.'. $myextension .'.entities.'. $myextension .'-',
                '&reference.'. $myextension .'.'. $myextension .'.entities.',
                $contents);
            file_put_contents($file, $contents);
        }
    }
}

// Add classes to book.xml
$book = file_get_contents('book.xml');

foreach (glob("mysql-xdevapi.*.xml") as $file) {
    /* Example:
       File: mysql-xdevapi.columnresult.xml
       Base: mysql-xdevapi.columnresult
       Entity in book.xml: &reference.mysql-xdevapi.mysql-xdevapi.columnresult;
    */
    $entity_base = str_replace('.xml', '', $file);
    $entities[] = '&reference.mysql-xdevapi.'. $entity_base . ';';
}

// Just in case, borderline useful as it only checks the last entity base
if (false !== strpos($book, $entity_base)) {
    echo "Warning: I think book.xml has already been updated as it contains the string '$entity_base' but maybe not.\n";
    echo "Warning: You better check it out. Skipping the update book.xml task.\n";
} else {
    // Throw all the entities above </book> tag
    $newbook = str_replace('</book>', ' ' . implode("\n ", $entities) . "\n\n" . '</book>', $book);
    file_put_contents('book.xml', $newbook);
}


// Fix xinclude problem for classes with only a constructor
// @todo confirm this works
foreach (glob('mysql_xdevapi/*', GLOB_ONLYDIR) as $dir) {
    if (count(glob("$dir/*.xml")) === 1 && file_exists("$dir/construct.xml")) {
        $classfile = str_replace('mysql_xdevapi/', 'mysql-xdevapi.', $dir);
        $classfile = $classfile . '.xml';
        if (!file_exists($classfile)) {
            echo "Error: $classfile should exist, what happened?\n";
            exit;
        }
        $contents = file_get_contents($classfile);
        $contents = str_replace('descendant::db:methodsynopsis', 'descendant::db:constructorsynopsis', $contents);
        $contents = str_replace('<classsynopsisinfo role="comment">&Methods;</classsynopsisinfo>', '<classsynopsisinfo role="comment">Constructor</classsynopsisinfo>', $contents);
        file_put_contents($classfile, $contents);
    }
}

// Fix Exception
// This is really hackish and specific to this extension
if (file_exists("$myextension.exception.xml")) {
    $contents = file_get_contents("$myextension.exception.xml");
    if (false === strpos($contents, "<!-- &reference.{$myextension}")) {

        // Comment out the entity reference
        $contents = str_replace("&reference.{$myextension}.{$myextension}.entities.exception;", "<!-- &reference.{$myextension}.{$myextension}.entities.exception; -->", $contents);

        // Comment out the xincludes
        // Begin comment
        $contents = str_replace(
            '<classsynopsisinfo role="comment">&InheritedProperties;</classsynopsisinfo>',
            '<!-- <classsynopsisinfo role="comment">&InheritedProperties;</classsynopsisinfo>',
            $contents);
        // End comment
        $contents = str_replace(
            '<xi:include xpointer="xmlns(db=http://docbook.org/ns/docbook) xpointer(id(\'class.runtimeexception\')/db:refentry/db:refsect1[@role=\'description\']/descendant::db:methodsynopsis[not(@role=\'procedural\')])" />',
            '<xi:include xpointer="xmlns(db=http://docbook.org/ns/docbook) xpointer(id(\'class.runtimeexception\')/db:refentry/db:refsect1[@role=\'description\']/descendant::db:methodsynopsis[not(@role=\'procedural\')])" /> -->',
            $contents);

        file_put_contents("$myextension.exception.xml", $contents);
    }

}


/*
  Notes:

  1. Entities were all wrong

    - The code renames them, e.g.,
        '&reference.foo.entities.foo-classname',
        '&reference.foo.foo.entities.classname',

  2. book.xml did not link to the classes

    - The code adds them e.g.,
        &reference.mysql-xdevapi.mysql-xdevapi.basesession;

  3. XInclude problems

     - Initially the following failed: driver, executionstatus, expression, fieldmetadata, warning, xsession

     - These all only contained a constructor and no other methods.

     - The fix was to change methodsynopsis to constructorsynopsis in class file for the xinclude, for example:

       Change this: <xi:include xpointer="xmlns(db=http://do ... descendant::db:methodsynopsis
       To this:     <xi:include xpointer="xmlns(db=http://do ... descendant::db:constructorsynopsis

  4. Generic class + Reflection fail

    - Commented out all xincludes from mysql-xdevapi.exception.xml and also the entity reference
      because docgen did not generate exception/, likely due to the generic name?

     - Workaround is to remove the xincludes and entity reference, and the proper fix is to either:
       - fix docgen
       - fix reflection data in the extension itself (maybe it's wrong)
       - or manually write this documentation
 */
