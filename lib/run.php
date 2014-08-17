#!/usr/bin/php -q
<?php
/**
 * run script for block storage testing
 */
require_once('BlockStorageTest.php');
// parse cli arguments
$options = BlockStorageTest::getRunOptions();
// print_r($argv);
// print_r($options);
// exit;
$verbose = isset($options['verbose']) && $options['verbose'];

// invalid run argument
if ($invalid = BlockStorageTest::validateRunOptions($options)) {
  foreach($invalid as $arg => $err) BlockStorageTest::printMsg(sprintf('argument --%s is invalid - %s', $arg, $err), $verbose, __FILE__, __LINE__, TRUE);
  exit(1);
}
// missing dependencies
else if ($dependencies = BlockStorageTest::validateDependencies($options)) {
  foreach($dependencies as $dependency) BlockStorageTest::printMsg(sprintf('missing dependency %s', $dependency), $verbose, __FILE__, __LINE__, TRUE);
  exit(1);
}
// fio version and settings
else if (!BlockStorageTest::validateFio($options)) {
  BlockStorageTest::printMsg(sprintf('fio version 2.* is required'), $verbose, __FILE__, __LINE__, TRUE);
  exit(1);
}
BlockStorageTest::printMsg(sprintf('Starting block storage tests [%s] using targets [%s] and %ds timeout', implode(', ', $options['test']), implode(', ', $options['target']), $options['timeout']), $verbose, __FILE__, __LINE__);
ini_set('max_execution_time', $options['timeout']);

$exitCode = 0;
$controllers = array();
foreach($options['test'] as $test) {
  if ($controller =& BlockStorageTest::getTestController($test, $options)) {
    BlockStorageTest::printMsg(sprintf('Starting %s block storage test', strtoupper($test)), $verbose, __FILE__, __LINE__);
    
    // purge targets
    if (isset($options['nopurge']) && $options['nopurge']) BlockStorageTest::printMsg(sprintf('Target purge skipped due to use of --nopurge'), $verbose, __FILE__, __LINE__);
    else if (!$controller->purge()) {
      $exitCode = 1;
      BlockStorageTest::printMsg(sprintf('Testing aborted because test targets could not be purged and --nopurge argument was not specified'), $verbose, __FILE__, __LINE__, TRUE);
      break;
    }
    else BlockStorageTest::printMsg(sprintf('Target purge successful - continuing testing'), $verbose, __FILE__, __LINE__);
    
    // workload independent pre-conditioning
    if (isset($options['noprecondition']) && $options['noprecondition']) BlockStorageTest::printMsg(sprintf('Workload independent precondition skipped due to use of --noprecondition'), $verbose, __FILE__, __LINE__);
    else if (!$controller->wipc()) {
      $exitCode = 1;
      BlockStorageTest::printMsg(sprintf('Testing aborted because workload independent preconditioning failed and --noprecondition argument was not specified'), $verbose, __FILE__, __LINE__, TRUE);
      break;
    }
    else BlockStorageTest::printMsg(sprintf('Workload independent preconditioning successful - continuing testing'), $verbose, __FILE__, __LINE__);
    
    // workload dependent pre-conditioning & testing
    $status = $controller->wdpc();
    if ($status !== NULL) {
      BlockStorageTest::printMsg(sprintf('Workload dependent preconditioning for test %s successful%s. wdpcComplete=%d; wdpcIntervals=%d. Generating test artifacts...', $test, $status ? '' : ' - but steady state was not achieved', $controller->wdpcComplete, $controller->wdpcIntervals), $verbose, __FILE__, __LINE__);
      // generate fio JSON output
      $controller->generateJson();
      $controllers[$test] =& $controller;
    }
    else BlockStorageTest::printMsg(sprintf('Workload dependent preconditioning for test %s failed', strtoupper($test)), $verbose, __FILE__, __LINE__, TRUE);
  }
  else BlockStorageTest::printMsg(sprintf('Unable to get %s test controller', $test), $verbose, __FILE__, __LINE__, TRUE);
}
// generate report
if (!$exitCode && count($controllers)) BlockStorageTest::generateReports($controllers);

exit($exitCode);
?>