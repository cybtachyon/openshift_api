<?php

namespace openshift_api\Tests;

use OpenShiftApi;
use PHPUnit\Framework\TestCase;

/**
 * Class OpenShiftApiTest.
 *
 * @package openshift_api\Tests
 * @coversDefaultClass OpenShiftApi
 */
class OpenShiftApiTest extends TestCase {

  /**
   * @covers ::__construct
   */
  public function testOpenShiftApiConstructor() {
    $osc = new OpenShiftApi();

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

    $this->assertNotEmpty($actual_response, 'OpenShiftApi connection not made.');
    $this->assertObjectNotHasAttribute('error', $actual_response);
    $this->assertObjectHasAttribute('items', $actual_response, '');
    $this->assertEquals($example_json, $actual_response);
  }

}
