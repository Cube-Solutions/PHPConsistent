# PHPConsistent

Disclaimer : this is the first release. I know the code can be massively improved, but that's what pull requests are for ;-) - Any feedback welcome !
 
PHPConsistent is a dynamic and static code analysis tool that verifies the consistency between the function/method calls your code makes and the in-line documentation (docblock) of the called functions/methods.
The goal is to improve code quality of your code and the libraries you use by :
* Verifying your code is making calls using the right parameters and parameter types
* Verifying if the in-line documentation (docblock) of the called functions/methods is accurate

It will compare :
* Parameter types specified in the docblock <-> types of parameters passed upon calling the function/method
* Number of parameters specified in the docblock <-> number of parameters actually present in the function/method definition
* Names of parameters specified in the docblock <-> names of parameters actually present in the function/method definition

It will output to :
* File
* FirePHP (a plugin for Firebug)
* PHPUnit, when run as a TestListener in PHPUnit

## Sample output
```
Invalid type calling SomeClass->GiveMeAnArray : parameter 3 ($somearray) should be of type array but got boolean instead : library/App.php (line 5)
Parameter names in function definition and docblock don't match when calling JustAnotherFunction : parameter 2 ($inputFilename) should be called $inputFile according to docblock : application/Bootstrap.php (line 214)
Parameter count in function definition and docblock don't match when calling OneMoreFunction : function has 6 but should be 5 according to docblock : application/Bootstrap.php (line 215)
```

## Requirements

* PHP 5.3 or higher
* Xdebug 2.2.4 or higher
* FirePHP Core 0.4.0 or higher (if you want to use FirePHP reporting)
* PHPUnit 3.8 or higher (if you want to run it from your unit tests)


## Performance

Since PHPConsistent needs Xdebug to produce a complete trace of the code, it creates quite a big file. It then analyzes that big file.
In other words : it slows down your code by a factor of 5-20, so under no circumstances should it be used in production.  


## Installing
Via Composer
```
{
    "require": {
        "cubesolutions/phpconsistent": "dev-master"
    }
}
```


##Using PHPConsistent in your bootstrap file
More documentation will be added here, but really all that's needed is to include_once('Main.php') and then :
```php
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
```


Example, ignore null values, reporting 10 levels deep via FirePHP and ignoring all calls from Zend Framework files :
```php
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
```


##PHPConsistent using an automatic prepend

If you don't want to touch any existing bootstrap code, but still want to use PHPConsistent, you can use the auto_prepend_file parameter in php.ini (not advisable), your Apache virtualhost block or .htaccess file.

To do this, you set up a file outside your project folder (preferably somewhere centralized, so you can use PHPConsistent for multiple projects, yet have it installed only once). This file should load PHPConsistent as explained above. You then point the auto_prepend_file parameter to the file you created. Make sure Apache has sufficient privileges to this file.


##Important note - when FirePHP doesn't seem to be working

If your code uses ob_flush(), note that PHPConsistent uses ob_start() to enable output buffering when using the FirePHP reporting feature. Using ob_flush() could send the headers to the browser, meaning PHPConsistent can't send the headers to FirePHP anymore. If you have display_errors turned on, an error will be shown about headers having been sent already. If display_errors is turned off, nothing will show up and FirePHP will not report anything.


##Reporting

1. To file :
 - Set the 'log' configuration parameter to PHPConsistent_Main::LOG_TO_FILE
 - Set the 'log_location' configuration parameter to the path of the file you want to append to
2. To FirePHP :
 - Install Firebug and FirePHP in your Firefox
 - Set the 'log' configuration parameter to PHPConsistent_Main::LOG_TO_FIREPHP
3. From/to PHPUnit :
 - Setup PHPUnit for your unit tests
 - Add to your phpunit.xml :
```
   <listeners>
   
     <listener class="PHPConsistentTestListener" file="/optional/path/to/PHPConsistentTestListener.php">
     
       <arguments>
       
         <array>
         
           <element key="depth">
           
             <integer>10</integer>
             
           </element>
           
           <element key="ignorenull">
           
             <boolean>false</boolean>
             
           </element>
           
         </array>
         
       </arguments>
       
     </listener>
     
   </listeners>
```