<?php

class PHPConsistentTestListener extends PHPUnit_Framework_TestListener
{
    /**
     * PHPConsistent object
     * @var PHPConsistent_Main
     */
    private $_phpc;

    public function __construct($args = array())
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
    }


    public function startTest(PHPUnit_Framework_Test $test)
    {
        $this->_phpc->start();
    }

    public function endTest(PHPUnit_Framework_Test $test, $time)
    {
        $this->_phpc->stop();
        $failures = $this->_phpc->analyze();

        foreach ($failures as $failure) {
            $this->addFailure($test, new PHPUnit_Framework_AssertionFailedError($failure['data'], 1), $time);
        }
    }
}