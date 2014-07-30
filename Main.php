<?php

/**
 * Holds the main initialization and computation functions
 *
 * @package    PHPConsistent
 * @author     Wim Godden <wim@wimgodden.be>
 * @copyright  2009-2014 Wim Godden <wim@wimgodden.be>
 * @license    https://www.gnu.org/licenses/lgpl.html  The LGPL 3 License
 * @link       http://phpconsistent.cu.be/
 * @version    0.1
 *
 */
class PHPConsistent_Main
{
    const LOG_TO_NULL = 0;
    const LOG_TO_FILE = 1;
    const LOG_TO_FIREPHP = 2;

    /**
     * Temporary trace file for xDebug
     * @var string
     */
    protected $_traceFile = '';

    /**
     * Depth of checks
     * @var int
     */
    protected $_depth = 10;

    /**
     * Ignore null values as parameters
     * @var boolean
     */
    protected $_ignorenull = false;

    /**
     * Log method to use. See above constants LOG_TO_*
     * @var int
     */
    protected $_log;

    /**
     * Log location (like filename)
     * @var string
     */
    protected $_logLocation;

    /**
     * Array of filenames not to process calls FROM - will be matched *name*
     * @var array
     */
    protected $_ignoredFileNames;

    /**
     * Array of classes to ignore method calls TO - will be matched *name*
     * @var array
     */
    protected $_ignoredClassNames;

    /**
     * Array of functions to ignore - will be matched *name*
     * @var array
     */
    protected $_ignoredFunctions;

    /**
     * Initialize PHPConsistent, return PHPConsistent object
     *
     * @param string $traceFile
     * @param number $depth
     * @param string $ignorenull
     * @param int $log
     * @param string $log_location
     * @param array $ignoredFileNames
     * @param array $ignoredClassNames
     * @param array $ignoredFunctions
     * @return PHPConsistent_Main
     */
    public function __construct($traceFile = null, $depth = 2, $ignorenull = false, $log = self::LOG_TO_NULL, $logLocation = null, $ignoredFileNames = array(), $ignoredClassNames = array(), $ignoredFunctions = array())
    {
        $this->_traceFile = $traceFile;
        $this->_depth = $depth;
        $this->_ignorenull = $ignorenull;
        $this->_log = $log;
        $this->_logLocation = $logLocation;
        $this->_ignoredFileNames = $ignoredFileNames;
        $this->_ignoredClassNames = $ignoredClassNames;
        $this->_ignoredFunctions = $ignoredFunctions;
    }

    /**
     * Start PHPConsistent run
     */
    public function start()
    {
        if (extension_loaded('xdebug') === false) {
            return false;
        }

        if (is_null($this->_traceFile) === true) {
            $this->_traceFile = tempnam(sys_get_temp_dir(), 'PHPConsistent_');
        }

        ini_set('xdebug.auto_trace', 1);
        ini_set('xdebug.trace_format', 1);
        ini_set('xdebug.collect_return', 1);
        ini_set('xdebug.collect_params', 1);
        ini_set('xdebug.trace_options', 0);
        xdebug_start_trace($this->_traceFile);

        switch ($this->_log) {
            case self::LOG_TO_FIREPHP:
                ob_start();
                break;
        }

        register_shutdown_function(array($this, 'stop'));
        register_shutdown_function(array($this, 'analyze'));

        return true;
    }

    /**
     * Stop PHPConsistent run
     */
    public function stop()
    {
        if (extension_loaded('xdebug') === false) {
            return false;
        }

        xdebug_stop_trace();

        return true;
    }

    /**
     * Analyze PHPConsistent data from trace file
     */
    public function analyze()
    {
        if (!file_exists($this->_traceFile)) {
            return false;
        }
        $this->processFunctionCalls();
        unlink($this->_traceFile);
        if (file_exists($this->_traceFile . '.xt')) {
            unlink($this->_traceFile . '.xt');
        }
    }

    /**
     * Compares 2 types, including classes
     * First type is the actual type, retrieved from the Xdebug trace
     * Second type is the type (or types) specified in the docblock of the function/method
     *
     * @param string $callType Can be 'class ClassName' or just the type itself
     * @param string $docblockType The type to compare to
     * @return boolean True if matched, false if not
     */
    protected function compareTypes($callType, $docblockType)
    {
        if (trim($callType) == '???') {
            return true;
        }

        /**
         * Main loop, comparing for all types specified in docblock
         * Multiple types must be separated by a pipe (|)
         */
        $docblockTypes = explode('|', $docblockType);
        foreach ($docblockTypes as $docblockType) {
            $docblockType = trim($docblockType);
            if ($docblockType == 'mixed') { // If the allowed type is mixed, it doesn't matter what was sent, everything is correct
                return true;
            }

            preg_match_all('/\w+/', $callType, $callTypes); // Split up by words to get class names (if any)

            if ($callTypes[0][0] == 'class') { // If it's a class, we will get its parent classes and interface, since they are allowed types as well
                if ($docblockType == 'object') { // For Symfony 2, they seem to do this in a few places in their DI stuff
                    return true;
                }
                $className = $callTypes[0][1];
                $implements = class_implements($className);
                $parents = class_parents($className);
                $callTypes = array_merge(array($className), $implements, $parents);
            } else {
                $callTypes = array($callTypes[0][0]);
            }

            $foundMatch = false;
            foreach ($callTypes as $callType) {
                switch ($callType) {
                    case 'null':
                        if ($this->_ignorenull === true) {
                            return true;
                        }
                        break;
                    case $docblockType:
                        return true;
                        break;
                    case 'float':
                        if ($docblockType == 'double' || $docblockType == 'number') {
                            return true;
                        }
                        break;
                    case 'int': // This may seem weird, but we consider that since there's no data loss when passing an int as a float, it's ok
                    case 'long': // Long doesn't exist in PHP, but it seems Xdebug passes it as long in the trace file, which is why it's here
                        if ($docblockType == 'int' || $docblockType == 'integer' || $docblockType == 'long' || $docblockType == 'number' || $docblockType == 'float') {
                            return true;
                        }
                        break;
                    case 'bool':
                        if ($docblockType == 'boolean') {
                            return true;
                        }
                        break;
                }
            }
        }
        return false;
    }

    /**
     * Process the function calls from the Xdebug trace file
     */
    public function processFunctionCalls()
    {
        $returnStack = array();

        if (!file_exists($this->_traceFile . '.xt') || ($handle = fopen($this->_traceFile . '.xt', 'r')) === false) {
            return;
        }

        // See http://xdebug.org/docs/all_settings#trace_format
        // Note : classes are specified as 'class <classname>'
        while ($dataLine = fgetcsv($handle, null, "\t")) {
            if (is_numeric($dataLine[0])) {
                $minimumLevel = $dataLine[0];
                break;
            }
        }

        while ($dataLine = fgetcsv($handle, null, "\t")) {
            // For convenience and readability
            $tracedataLevel = $dataLine[0];
            if (!is_numeric($tracedataLevel)) { // We've reached the end of the trace file
                break;
            }
            if ($tracedataLevel > $minimumLevel + $this->_depth) { // We don't analyze this deep
                continue;
            }

            $tracedataIsReturn = $dataLine[2];
            if (isset($dataLine[5])) {
                $tracedataFunctionName = $dataLine[5];
                $splitClass = explode('->', $tracedataFunctionName);
                if (count($splitClass) > 1) { // It's a method
                    foreach ($this->_ignoredClassNames as $classname) {
                        if (strpos($splitClass[0], $classname) !== false) {
                            continue 2;
                        }
                    }
                    foreach ($this->_ignoredFunctions as $functionname) {
                        if (strpos($splitClass[1], $functionname) !== false) {
                            continue 2;
                        }
                    }
                } else {
                    foreach ($this->_ignoredFunctions as $functionname) {
                        if (strpos($splitClass[0], $functionname) !== false) {
                            continue 2;
                        }
                    }
                }
            } else {
                $tracedataFunctionName = '';
            }
            if (isset($dataLine[6])) {
                $tracedataUserDefined = $dataLine[6];
            } else {
                $tracedataUserDefined = '';
            }
            if (isset($dataLine[7])) {
                $tracedataIncludeFilename = $dataLine[7];
            } else {
                $tracedataIncludeFilename = '';
            }
            if (isset($dataLine[8])) {
                $tracedataFilename = $dataLine[8];
                foreach ($this->_ignoredFileNames as $filename) {
                    if (strpos($tracedataFilename, $filename) !== false) {
                        continue 2;
                    }
                }
            } else {
                $tracedataFilename = '';
            }
            if (isset($dataLine[9])) {
                $tracedataLinenumber = $dataLine[9];
            } else {
                $tracedataLinenumber = '';
            }

            if ($tracedataIsReturn == 0) { // It's a function/method call
                preg_match(
                '/(?P<classOrFunction>\w+){0,1}(?:\:\:|->){0,1}(?P<method>\w+){0,1}/',
                $tracedataFunctionName,
                $functionCall
                );

                $docBlock = false; // getDocComment will return false if no docblock is present
                if (!isset($functionCall['method']) && function_exists($functionCall['classOrFunction'])) { // It's a function
                    $calledFunction = $functionCall['classOrFunction'];
                    $func = new ReflectionFunction($functionCall['classOrFunction']);
                    $docBlock = $func->getDocComment();
                    $numberOfParameters = $func->getNumberOfParameters();
                    $functionParameters = $func->getParameters();
                } elseif (isset($functionCall['method']) && method_exists($functionCall['classOrFunction'], $functionCall['method'])) { // It's a method
                    $calledFunction = $functionCall['classOrFunction'] . '->' . $functionCall['method'];
                    $method = new ReflectionMethod($functionCall['classOrFunction'], $functionCall['method']);
                    $docBlock = $method->getDocComment();
                    $numberOfParameters = $method->getNumberOfParameters();
                    $functionParameters = $method->getParameters();
                } // else it's an internal function, which we're unable to verify at this point

                if ($docBlock !== false) {
                    $foundReturn = false;

                    preg_match_all('/\s*\*\s*@(?P<tag>phpconsistent-ignore)/', $docBlock, $noTypeCheck, PREG_SET_ORDER); // Check if @phpconsistent-ignore was in docblock
                    if (count($noTypeCheck) == 0) {

                        // Main docblock parameter and return type loop
                        preg_match_all('/\s*\*\s*@(?P<tag>param|return)\s+(?P<type>\S+)\s+(?P<paramName>\$?\w+)?/', $docBlock, $docBlockVars, PREG_SET_ORDER);
                        $documentedParameters = 0;
                        $docblockParams = array();
                        for ($cntDocBlockTag = 0; $cntDocBlockTag < count($docBlockVars); $cntDocBlockTag++) {
                            if ($docBlockVars[$cntDocBlockTag]['tag'] == "param") {
                                $documentedParameters++;
                                $docblockParams[] = $docBlockVars[$cntDocBlockTag]['paramName'];
                                if (isset($dataLine[11 + $cntDocBlockTag])) { // Was the call made with this parameter ?
                                    $foundMatch = $this->compareTypes($dataLine[11 + $cntDocBlockTag], $docBlockVars[$cntDocBlockTag]['type']);

                                    if ($foundMatch === false) {
                                        $this->addParamTypeFailure(
                                            $tracedataFilename,
                                            $tracedataLinenumber,
                                            $calledFunction,
                                            ($cntDocBlockTag + 1),
                                            $docBlockVars[$cntDocBlockTag]['paramName'],
                                            $docBlockVars[$cntDocBlockTag][2],
                                            $dataLine[11 + $cntDocBlockTag]
                                        );
                                    }
                                }

                            } else { // Put the expected return type on the stack for later use
                                $returnStack[] = array(
                                    'fileName'        => $tracedataFilename,
                                    'fileLine'        => $tracedataLinenumber,
                                    'calledFunction'  => $calledFunction,
                                    'parameterNumber' => null,
                                    'parameterName'   => null,
                                    'expectedType'    => $docBlockVars[$cntDocBlockTag]['type'],
                                    'calledType'      => null,
                                    'noTypeCheck'     => 0
                                );
                                $foundReturn = true;
                            }
                        }
                    }

                    if ($numberOfParameters != $documentedParameters) {
                        $this->addParamCountMismatchFailure($tracedataFilename, $tracedataLinenumber, $calledFunction, $documentedParameters, $numberOfParameters);
                    }

                    for ($cntDocBlockParam = 0; $cntDocBlockParam < count($docblockParams); $cntDocBlockParam++) {
                        if ($docblockParams[$cntDocBlockParam] != '$' . $functionParameters[$cntDocBlockParam]->getName()) {
                            $this->addParamNameMismatchFailure($tracedataFilename, $tracedataLinenumber, $calledFunction, $cntDocBlockParam, $docblockParams[$cntDocBlockParam], '$' . $functionParameters[$cntDocBlockParam]->getName());
                        }

                    }

                    if ($foundReturn === false) { // Put this function/method call on the stack
                        $returnStack[] = array(
                            'fileName'        => $tracedataFilename,
                            'fileLine'        => $tracedataLinenumber,
                            'calledFunction'  => $calledFunction,
                            'parameterNumber' => null,
                            'parameterName'   => null,
                            'expectedType'    => null,
                            'calledType'      => null,
                            'noTypeCheck'     => count($noTypeCheck)
                        );
                    }
                } else {
                    $returnStack[] = array(
                        'fileName'        => $tracedataFilename,
                        'fileLine'        => $tracedataLinenumber,
                        'calledFunction'  => $calledFunction,
                        'parameterNumber' => null,
                        'parameterName'   => null,
                        'expectedType'    => null,
                        'calledType'      => null,
                        'noTypeCheck'     => 1
                    );
                }

            } else { // It's a return
                // If this isn't set, we have a version of Xdebug that hasn't been patched for Xdebug bug #416
                // Requires Xdebug with code after https://github.com/xdebug/xdebug/pull/79
                if ($tracedataFunctionName != '') {

                    $returnPop = array_pop($returnStack);

                    if ($returnPop['noTypeCheck'] == 0 && $returnPop['expectedType'] != '') {
                        preg_match('/(?P<type>\w+)\s?(?P<class>\w+)?/', $tracedataFunctionName, $returnTypes);
                        if (preg_match('/^\'.*/', $tracedataFunctionName) > 0) { // Strings are not extracted with the above regular expression, but ALWAYS start with a quote in Xdebug trace output
                            $returnPop['calledType'] = 'string';
                        } elseif ($returnTypes['type'] == 'NULL') {
                            $returnPop['calledType'] = 'null';
                        } elseif ($returnTypes['type'] == 'class') {
                            $returnPop['calledType'] = 'class ' . $returnTypes['class'];
                        } elseif ($returnTypes['type'] == 'TRUE' || $returnTypes['type'] == 'FALSE') {
                            $returnPop['calledType'] = 'bool';
                        } elseif ($returnTypes['type'] == 'array') {
                            $returnPop['calledType'] = 'array';
                        } elseif (is_numeric($returnTypes['type'])) {
                            $returnPop['calledType'] = 'int';
                        } else {
                            $returnPop['calledType'] = 'unknown';
                        }
                        if (!$this->compareTypes($returnType, $returnPop['expectedType'])) {
                            $this->addParamTypeFailure($returnPop['fileName'], $returnPop['fileLine'], $returnPop['calledFunction'], null, null, $returnPop['expectedType'], $returnPop['calledType']);
                        }
                    }
                }
            }
        }
        fclose($handle);
    }

    /**
     * Add a failure about incorrect parameter type
     * @param string $fileName
     * @param int    $fileLine
     * @param string $calledFunction
     * @param int    $parameterNumber
     * @param string $parameterName
     * @param string $expectedType
     * @param string $calledType
     */
    protected function addParamTypeFailure($fileName, $fileLine, $calledFunction, $parameterNumber, $parameterName, $expectedType, $calledType)
    {
        $data = 'Invalid type calling ' . $calledFunction . ' : parameter ' . $parameterNumber  . ' (' . $parameterName . ') should be of type ' . $expectedType . ' but got ' . $calledType . ' instead';
        $this->reportFailure($fileName, $fileLine, $data);
    }

    /**
     * Add a failure about mismatching parameter names
     * @param string $fileName
     * @param int    $fileLine
     * @param stirng $calledFunction
     * @param int    $parameterNumber
     * @param string $expectedName
     * @param string $calledName
     */
    protected function addParamNameMismatchFailure($fileName, $fileLine, $calledFunction, $parameterNumber, $expectedName, $calledName)
    {
        $data = 'Parameter names in function definition and docblock don\'t match when calling ' . $calledFunction . ' : parameter ' . $parameterNumber . ' (' . $calledName . ') should be called ' . $expectedName . ' according to docblock';
        $this->reportFailure($fileName, $fileLine, $data);
    }

    /**
     * Add a failure about mismatching parameter count
     * @param string $fileName
     * @param int    $fileLine
     * @param string $calledFunction
     * @param int    $expectedCount
     * @param int    $actualCount
     */
    protected function addParamCountMismatchFailure($fileName, $fileLine, $calledFunction, $expectedCount, $actualCount)
    {
        $data = 'Parameter count in function definition and docblock don\'t match when calling ' . $calledFunction . ' : function has ' . $actualCount . ' but should be ' . $expectedCount . ' according to docblock';
        $this->reportFailure($fileName, $fileLine, $data);
    }

    /**
     * Output to the chosen reporting system
     * @param string $fileName
     * @param int    $fileLine
     * @param string $data
     */
    protected function reportFailure($fileName, $fileLine, $data)
    {
        switch ($this->_log) {
            case self::LOG_TO_FILE:
                file_put_contents(
                    $this->_logLocation,
                    $data . ' - in ' . $fileName . ' (line ' . $fileLine . ')',
                    FILE_APPEND
                );
                break;
            case self::LOG_TO_FIREPHP:
                require_once 'FirePHPCore/FirePHP.class.php';
                $firephp = FirePHP::getInstance(true);
                $firephp->warn($fileName . ' (line ' . $fileLine . ')', $data);
                break;
        }
    }
}