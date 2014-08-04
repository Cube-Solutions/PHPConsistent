<?php

require_once 'Main.php';

class PHPConsistentTestListener implements PHPUnit_Framework_TestListener
{
    /**
     * PHPConsistent object
     * @var PHPConsistent_Main
     */
    private $_phpc;
    
    /**
     * Failures to report
     * @var array
     */
    private $_failures = array();

    public function startTest(PHPUnit_Framework_Test $test)
    {
        $this->_phpc = new PHPConsistent_Main(
            null,
            (isset($args['depth']) ? $args['depth'] : 10),
            (isset($args['ignorenull']) ? $args['ignorenull'] : false),
            PHPConsistent_Main::LOG_TO_PHPUNIT,
            array(
                'PHPUnit'
            ),
            array(
                'PHPUnit_Framework_Assert'
            ),
            array()
        );
        $this->_phpc->start();
    }

    public function endTest(PHPUnit_Framework_Test $test, $time)
    {
        $this->_phpc->stop();
        $failures = $this->_phpc->analyze();
		if (count($failures) > 0) {
			foreach ($failures as $failure) {
			    $this->_failures[] = $failure;
			}
		}
    }

	public function addError(PHPUnit_Framework_Test $test, Exception $e, $time) {}
    public function addFailure(PHPUnit_Framework_Test $test, PHPUnit_Framework_AssertionFailedError $e, $time) {}
    public function addIncompleteTest(PHPUnit_Framework_Test $test, Exception $e, $time) {}
    public function addSkippedTest(PHPUnit_Framework_Test $test, Exception $e, $time) {}
    public function startTestSuite(PHPUnit_Framework_TestSuite $suite) {}
    public function endTestSuite(PHPUnit_Framework_TestSuite $suite) {}
    public function addRiskyTest(PHPUnit_Framework_Test $test, Exception $e, $time) {}
    public function __destruct()
    {
        // Process $this->_failures - code incomplete at this point
    }
}