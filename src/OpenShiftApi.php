<?php

use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Client;

/**
 * An OpenShift HTTP API.
 */
class OpenShiftApi {

  /**
   * A guzzle http client instance.
   *
   * @var \GuzzleHttp\Client
   *   A guzzle http client instance.
   */
  protected $client;

  /**
   * OpenShift API token.
   *
   * @var string
   *   An OpenShift API token.
   */
  protected $token;

  /**
   * OpenShiftApi constructor.
   */
  public function __construct() {
    $this->host = variable_get('openshift_api_host', '');
    $this->token = variable_get('openshift_api_secret', '');
    $this->client = new Client(array(
      'base_uri' => $this->host . '/oapi/v1/',
      'headers' => array(
        'Authorization' => 'Bearer ' . $this->token,
        'Content-type' => 'application/json',
      ),
    ));
  }

  /**
   * Sends a patch to client.
   *
   * @param string $path
   *   An API path string.
   * @param array $data
   *   An array of data to send as the patch.
   * @param array $options
   *   An array of config options for the client.
   *
   * @return Response|\GuzzleHttp\Exception\RequestException
   *   Returns a Response or RequestException.
   */
  public function patch($path, array $data = array(), array $options = array()) {
    return $this->send('patch', $path, $data, $options);
  }

  /**
   * Sends a post to client.
   *
   * @param string $path
   *   An API path string.
   * @param array $data
   *   An array of data to send as the post.
   * @param array $options
   *   An array of config options for the client.
   *
   * @return Response|\GuzzleHttp\Exception\RequestException
   *   Returns a Response or RequestException.
   */
  public function post($path, array $data = array(), array $options = array()) {
    return $this->send('post', $path, $data, $options);
  }

  /**
   * Sends the client request.
   *
   * @param string $method
   *   The http request method.
   * @param string $path
   *   An API path string.
   * @param array $data
   *   An array of data to send as the post.
   * @param array $options
   *   An array of config options for the client.
   *
   * @return Response|\GuzzleHttp\Exception\RequestException
   *   Returns a Response or RequestException.
   */
  protected function send($method, $path, array $data = array(), array $options = array()) {
    if (!method_exists($this, $method)) {
      $e = new \BadMethodCallException("OpenShiftApi::$method() is not implemented.");
      watchdog_exception('openshift_api', $e);
      throw $e;
    }

    try {
      $options = array_replace_recursive($this->client->getConfig(), $options);
      $options['json'] = $data;
      $response = $this->client->{$method}($path, $options);
    }
    catch (RequestException $e) {
      $response = RequestException::wrapException($e->getRequest(), $e);
      watchdog_exception('openshift_api', $e);
    }
    return $response;
  }

}
