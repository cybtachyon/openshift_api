<?php

namespace openshift_api\Tests;

use OpenShiftApi;
use PHPUnit\Framework\TestCase;

/**
 * Class OpenShiftApiTest.
 *
 * @package openshift_api\Tests
 */
class OpenShiftApiTest extends TestCase {

  /**
   * @covers ::__construct.
   */
  public function testOpenShiftApi() {
    $osc = new OpenShiftApi();

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
