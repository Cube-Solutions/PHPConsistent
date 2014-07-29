PHPConsistent
=============

Using PHPConsistent in your bootstrap file
------------------------------------------
$phpconsistent = new PHPConsistent_Main(
    null,    // Full path of the trace file - PHPConsistent normally takes care of this
    10,      // Maximum code depth to search - default = 10 calls deep
    false,    // Ignore null values as parameters - default = false
    <Reporting Type>,     // See Reporting section below
    <Reporting Location>, // See Reporting section below
    array(                // Array of file name strings to ignore (useful if you want to ignore an entire framework)
    ),
    array(                // Array of class name strings to ignore methods calls to
    ),
    array(                // Array of function strings to ignore calls to
    )
);
$phpconsistent->start();


Example, ignore null values, reporting 10 levels deep via FirePHP and ignoring all calls from Zend Framework files :
$phpconsistent = new PHPConsistent_Main(
    null,
    20,
    true,
    PHPConsistent_Main::LOG_TO_FIREPHP,
    null,
    array('Zend'),
    array(),
    array()
);
$phpconsistent->start();


PHPConsistent using an automatic prepend
----------------------------------------
If you don't want to touch any existing bootstrap code, but still want to use PHPConsistent, you can use the auto_prepend_file parameter in php.ini (not advisable), your Apache virtualhost block or .htaccess file.

To do this, you set up a file outside your project folder (preferably somewhere centralized, so you can use PHPConsistent for multiple projects, yet have it installed only once). This file should load PHPConsistent as explained above. You then point the auto_prepend_file parameter to the file you created. Make sure Apache has sufficient privileges to this file.


Important note - when FirePHP doesn't seem to be working
--------------------------------------------------------
If your code uses ob_flush(), note that PHPConsistent uses ob_start() to enable output buffering when using the FirePHP reporting feature. Using ob_flush() could send the headers to the browser, meaning PHPConsistent can't send the headers to FirePHP anymore. If you have display_errors turned on, an error will be shown about headers having been sent already. If display_errors is turned off, nothing will show up and FirePHP will not report anything.


Reporting
---------
1. To file :
 - Set the 'log' configuration parameter to PHPConsistent_Main::LOG_TO_FILE
 - Set the 'log_location' configuration parameter to the path of the file you want to append to
2. To FirePHP :
 - Install Firebug and FirePHP in your Firefox
 - Set the 'log' configuration parameter to PHPConsistent_Main::LOG_TO_FIREPHP
