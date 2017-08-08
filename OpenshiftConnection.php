<?php

/**
 * @file
 * Provides a OpenShift HTTP API connection service.
 */

/**
 * Guzzle usage, for now.
 *
 * @TODO Extend Drupal\HttpClient instead of Guzzle\Http\Client.
 */
use Guzzle\Http\Client;
use Guzzle\Http\Exception\RequestException;

/**
 * Class OpenshiftConnection.
 */
class OpenshiftConnection extends Client {

  /**
   * The access token expiration timeout for accessing OpenShift API.
   *
   * @var DateTime
   */
  private $accessExpiration;

  /**
   * The access token for accessing OpenShift API.
   *
   * @var string
   */
  private $accessToken;

  /**
   * The base64 encoded JSON Web Token secret.
   *
   * @var string
   *
   * @TODO Experiment with allowing this to be set only from $conf.
   * @TODO Allow this to be generated from regular basic auth credentials.
   */
  private $apiSecret;

  /**
   * OpenshiftConnection constructor.
   *
   * @throws RuntimeException
   *   Thrown if cURL is not installed.
   */
  public function __construct() {
    $this->apiSecret = variable_get('openshift_api_secret', '');
    $base_url = variable_get('openshift_api_origin', 'https://openshift.redhat.com:8443');
    parent::setDefaultOption('headers/Authorization', $this->apiSecret);
    try {
      parent::__construct($base_url);
    }
    catch (RuntimeException $exception) {
      throw $exception;
    }
  }

  /**
   * Retrieves an access token for the current connection.
   *
   * @return string|null
   *   The current connection's access token.
   */
  private function getAccessToken() {
    // Resets.
    if ($this->accessToken && $this->accessExpiration && REQUEST_TIME < $this->accessExpiration->getTimestamp()) {
      return $this->accessToken;
    }
    $cache = cache_get('openshift_api');
    if (isset($cache->data['accessToken']) && REQUEST_TIME < $cache->data['accessExpirationTime']) {
      $this->setAccessToken($cache->data['accessToken']);
      return $this->accessToken;
    }
    if (!$this->reset()) {
      return NULL;
    }
    $result = array(
      'accessExpirationTime' => $this->accessExpiration->getTimestamp(),
      'accessToken' => $this->accessToken,
    );
    // Allow the cache to clear at any time by not setting an expire time.
    cache_set('openshift_api', $result, 'cache', CACHE_TEMPORARY);
    return $this->accessToken;
  }

  /**
   * Sets the access token.
   *
   * @param string $access_token
   *   The new access token.
   */
  private function setAccessToken($access_token) {
    parent::setDefaultOption('headers/Authorization', $access_token);
    $this->accessToken = $access_token;
  }

  /**
   * Resets the current OpenShift API Connection.
   *
   * @return bool
   *   True if the connection is reset successfully.
   */
  public function reset() {
    $r_headers = array(
      'Authorization' => 'Basic ' . $this->apiSecret,
      'Content-Type' => 'application/json',
      'Cache-Control' => 'no-cache',
    );
    $r_body = <<< JSON
{
    "grant_type": "client_credentials"
}
JSON;
    try {
      $request = $this->client
        ->post($this->originUrl . 'v2/token', $r_headers);
      $response = $request->setBody($r_body, 'application/json')
        ->send();
    }
    catch (RequestException $exception) {
      $response = $exception->getResponse();
      $this->handleException($exception, 'resetting');
    }
    if ($response->isSuccessful()) {
      $data = json_decode($response->getBody());
      $this->setAccessToken(isset($data->access_token) ? $data->access_token : NULL);
      $expiration = isset($data->expiresIn) ? $data->expiresIn : 86400;
      $this->accessExpiration = new DateTime("now + $expiration seconds");
      parent::setDefaultOption('headers/Authorization', $data->access_token);
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Queries the OpenShift REST API.
   *
   * @param string $query
   *   A REST query in the format 'request/path/data?options'.
   *
   * @return mixed
   *   The decoded JSON REST response.
   *
   * @example
   *   $openshift = OpenshiftConnection::create();
   *   $result = openshift->query('duns-search/ip/216.55.149.9?view=standard');
   */
  public function query($query) {
    global $base_url;
    $r_headers = array(
      'Authorization' => 'Bearer ' . $this->getAccessToken(),
      'Origin' => $base_url,
    );
    $request = $this->client->get($this->originUrl . "v1/$query", $r_headers);
    try {
      $body = json_decode($request->send()->getBody());
    }
    catch (RequestException $exception) {
      $this->handleException($exception, 'querying');
      $body = json_decode($exception->getResponse()->getBody());
    }
    return $body;
  }

}
