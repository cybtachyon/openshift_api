<?php

namespace openshift_api\Tests;

use OpenshiftConnection;
use \PHPUnit\Framework\TestCase;

/**
 * Class OpenshiftConnectionTest.
 *
 * @package openshift_api\Tests
 */
class OpenshiftConnectionTest extends TestCase {

  /**
   * Tests the OpenshiftConnection::__construct() method.
   */
  public function testOpenshiftConnection() {
    $osc = new OpenshiftConnection();

    $actual_response = $osc->get('namespaces/testopenshiftconnection/builds');
    $example_json = <<<JSON
{
    "kind": "BuildList",
    "apiVersion": "v1",
    "metadata": {
        "selfLink": "/oapi/v1/namespaces/testopenshiftconnection/builds",
        "resourceVersion": "7139194"
    },
    "items": []
}
JSON;

    $this->assertNotEmpty($actual_response, 'Openshift Connection not made.');
    $this->assertObjectNotHasAttribute('error', $actual_response);
    $this->assertObjectHasAttribute('items', $actual_response, '');
    $this->assertEquals($example_json, $actual_response);
  }

}
