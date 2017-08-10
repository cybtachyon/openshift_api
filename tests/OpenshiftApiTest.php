<?php

namespace openshift_api\Tests;

use OpenshiftApi;
use PHPUnit\Framework\TestCase;

/**
 * Class OpenshiftApiTest.
 *
 * @package openshift_api\Tests
 */
class OpenshiftApiTest extends TestCase {

  /**
   * Tests the OpenshiftApi::__construct() method.
   */
  public function testOpenshiftApi() {
    $osc = new OpenshiftApi();

    $actual_response = $osc->getBuildConfigs('testopenshiftdrupal');
    $example_json = <<<JSON
{
    "kind": "BuildList",
    "apiVersion": "v1",
    "metadata": {
        "selfLink": "/oapi/v1/namespaces/testopenshiftdrupal/builds",
        "resourceVersion": "7139194"
    },
    "items": []
}
JSON;

    $this->assertNotEmpty($actual_response, 'OpenShift connection not made.');
    $this->assertEquals($example_json, $actual_response, 'OpenShift response not as expected.');
  }

}
