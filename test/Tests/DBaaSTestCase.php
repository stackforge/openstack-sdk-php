<?php
/** 
 * @file
 * Test case for DBaaS suite.
 */
namespace HPCloud\Tests\Services\DBaaS;

require_once 'src/HPCloud/Bootstrap.php';
require_once 'test/TestCase.php';

use \HPCloud\Services\DBaaS;
use \HPCloud\Services\DBaaS\Instance;
use \HPCloud\Services\DBaaS\Snapshot;

/**
 * @group dbaas
 */
abstract class DBaaSTestCase extends \HPCloud\Tests\TestCase {
  const SNAPSHOT_SUFFIX = '-SNAPSHOT';

  public function dbaas() {
    $ident = $this->identity();
    $dbaas = DBaaS::newFromIdentity($ident);
    return $dbaas;
  }
  public function inst() {
    $dbaas = $this->dbaas();
    return $dbaas->instance();
  }

  public function destroyDatabase() {
    $inst = $this->inst();
    $list = $inst->listInstances();

    //fwrite(STDOUT, print_r($list, TRUE));
    if (!empty($list)) {
      $dbName = self::conf('hpcloud.dbaas.database');
      foreach ($list as $item) {
        if ($item->name() == $dbName) {
          // fprintf(STDOUT, "Deleting %s (%s)\n", $item->name(), $item->id());
          $inst->delete($item->id());
        }
      }
    }
  }

  public function destroySnapshots() {
    $dbaas = $this->dbaas();
    $snap = $dbaas->snapshot();

    $list = $snap->listSnapshots();
    if (!empty($list)) {
      foreach ($list as $item) {
        //if (strrpos($item->name(), self::SNAPSHOT_SUFFIX) !== FALSE) {
          fprintf(STDOUT, "Deleteing snapshot %s\n", $item->id());
          $snap->delete($item->id());
        //}
      }
    }
  }

  public function waitUntilRunning($inst, &$details, $verbose = FALSE, $max = 120, $sleep = 5) {
    if ($details->isRunning()) {
      return TRUE;
    }

    $username = $details->username();
    $password = $details->password();

    for ($i = 0; $i < $max; ++$i) {

      if ($verbose) fwrite(STDOUT, '⌛');
      //fprintf(STDOUT, "Status: %s\n", $details->status());

      sleep($sleep);
      $details = $inst->describe($details->id());
      $details->setPassword($password)
        ->setUsername($username);

      if ($details->isRunning()) {
        return TRUE;
      }
    }

    throw new \Exception(sprintf("Instance did not start after %d attempts (%d seconds)", $max, $max * $sleep));

  }
}

