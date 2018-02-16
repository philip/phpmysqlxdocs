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
 			$contents = str_replace('&reference.'. $myextension .'.entities.'. $myextension .'-', '&reference.'. $myextension .'.'. $myextension .'.entities.', $contents);
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
if (false === strpos($book, $entity_base)) {
	echo "Warning: I think book.xml has already been updated as it contains the string '$entity_base' but maybe not.\n";
	echo "Warning: You better check it out. Exiting without updating book.xml.\n";
	exit;
}

// Throw all the entities above </book> tag
$newbook = str_replace('</book>', implode("\n ", $entities) . "\n\n" . '</book>', $book);
file_put_contents('book.xml', $newbook);


/* 
  TODO: these were manually updated and in the future fix.php (well, docgen) should fix these too:

  1. XInclude problems
  
     - Initially the following failed: driver, executionstatus, expression, fieldmetadata, warning, xsession
     
     - These all only contained a constructor and no other methods.
     
     - The fix was to change methodsynopsis to constructorsynopsis in class file for the xinclude, for example:
     
       Change this: <xi:include xpointer="xmlns(db=http://do ... descendant::db:methodsynopsis
       To this:     <xi:include xpointer="xmlns(db=http://do ... descendant::db:constructorsynopsis
       
  2. Generic class + Reflection fail
  
     - I basically ignored this for now but commented out all xincludes from mysql-xdevapi.exception.xml as
       docgen did not generate exception/, likely due to the generic name?
       
     - Workaround is to remove the xincludes, and the proper fix is to either: 
       - fix docgen
       - fix reflection data in the extension itself (maybe it's wrong)
       - or manually write this documentation 
 */