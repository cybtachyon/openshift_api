<?php

/**
 * @file
 * Provides a OpenShift HTTP API connection service.
 */

/**
 * Guzzle usage, for now.
 *
 * @TODO use Drupal\HttpClient instead of Guzzle\Http\Client.
 */
use Guzzle\Http\Client;
use Guzzle\Http\ClientInterface;

/**
 * Class OpenshiftApi.
 */
class OpenshiftApi {

  /**
   * The base64 encoded JSON Web Token secret.
   *
   * @var string
   *
   * @todo Allow this to be generated from cert queries via OpenShift API.
   * @todo Add support for OAuth Access Tokens.
   *
   * @see https://docs.openshift.org/latest/architecture/additional_concepts/authentication.html#api-authentication
   */
  private $apiSecret;

  /**
   * The HTTP Client to use, implementing Guzzle\Http\ClientInterface.
   *
   * @var Guzzle\Http\ClientInterface
   */
  private $client;

  /**
   * OpenshiftConnection constructor.
   *
   * @param Guzzle\Http\ClientInterface $client
   *   An HTTP Client for accessing Open Shift.
   *
   * @throws RuntimeException
   *   Throws an exception when encountering an error setting up dependencies.
   */
  public function __construct(ClientInterface $client = NULL) {
    $this->apiSecret = variable_get('openshift_api_secret', '');
    $base_url = variable_get('openshift_api_origin', 'https://openshift.redhat.com:8443');
    $base_url = trim($base_url, '/') . '/oapi/v1';

    try {
      $this->client = $client ?: new Client($base_url, array(
        'request.options' => array(
          'headers' => array('Authorization' => "Bearer {$this->apiSecret}"),
          'verify' => variable_get('openshift_api_verify_cert', TRUE),
        ),
      ));
    }
    catch (RuntimeException $exception) {
      throw $exception;
    }
  }

  /**
   * Returns the secret for accessing Open Shift via REST interface.
   *
   * @return string
   *   The JSON Web Token secret for accessing Open Shift.
   */
  public function getSecret() {
    return $this->apiSecret;
  }

}
