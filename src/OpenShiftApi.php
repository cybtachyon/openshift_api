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
 * Class OpenShiftApi.
 */
class OpenShiftApi {

  /**
   * OpenShift JWT required keys.
   *
   * @var array
   */
  public static $jwtKeys = array(
    0 => array(
      'header' => array(
        'alg',
        'typ',
      ),
    ),
    1 => array(
      'payload' => array(
        'iss',
        'kubernetes.io/serviceaccount/namespace',
        'kubernetes.io/serviceaccount/secret.name',
        'kubernetes.io/serviceaccount/service-account.name',
        'kubernetes.io/serviceaccount/service-account.uid',
        'sub',
      ),
    ),
  );

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
   * OpenShiftConnection constructor.
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
    $base_url = trim($base_url, '/');

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

  /**
   * Methods relating to OpenShift build configs.
   *
   * @ingroup build_config
   * @{
   */

  /**
   * Creates a new OpenShift build config.
   *
   * @param string $pid
   *   The OpenShift project ID.
   * @param array $config
   *   The build config description array to create.
   * @param bool $use_defaults
   *   Set to FALSE to avoid using common default settings.
   *
   * @return bool
   *   TRUE if the request succeeded.
   *
   * @throws RuntimeException
   *   Signifies an issue has occurred generating an HTTP Request.
   *
   * @code
   * $config = array(
   *   'metadata' => array(
   *     'name' => 'my-buildconfig',
   *   ),
   *   'spec' => array(
   *     'source' => array(
   *       'type' => 'Git',
   *       'git' => array(
   *         'url' => 'https://github.com/openshift/ruby-hello-world',
   *         'ref' => 'master',
   *     ),
   *     'output' => array(
   *       'to' => array(
   *         'kind' => 'ImageStreamTag',
   *         'name' => 'my-builds:1',
   *       ),
   *     ),
   *   ),
   * );
   * $osa->createBuildConfig('p12345', $config);
   * @endcode
   *
   * @see https://docs.openshift.org/latest/rest_api/openshift_v1.html#v1-buildconfig
   * @see https://docs.openshift.org/latest/rest_api/openshift_v1.html#create-a-buildconfig-2
   */
  public function createBuildConfig($pid, array $config, $use_defaults = TRUE) {
    $headers = array(
      'Content-Type' => 'application/json',
    );
    $defaults = array(
      'kind' => 'BuildConfig',
      'apiVersion' => 'v1',
      'metadata' => array(),
      'spec' => array(),
    );
    $defaults_spec = array(
      'runPolicy' => 'Serial',
      'source' => array(),
      'strategy' => array(
        'type' => 'Docker',
      ),
    );
    if ($use_defaults) {
      $config += $defaults;
      $config['spec'] += $defaults_spec;
    }
    $request = $this->client->post("oapi/v1/namespaces/$pid/buildconfigs", $headers, drupal_json_encode($config));
    try {
      $request->send();
    }
    catch (RuntimeException $exception) {
      throw $exception;
    }
    return ($request->getResponse() !== NULL && $request->getResponse()->getStatusCode() === 201);
  }

  /**
   * Returns the build config for a specific OpenShift project ID.
   *
   * @param string $pid
   *   The OpenShift project ID to query build configs against.
   * @param string $build_id
   *   The OpenShift build ID string.
   *
   * @return bool
   *   Returns TRUE if the operation succeeded.
   *
   * @throws RuntimeException
   *   Signifies an issue has occurred generating an HTTP Request.
   */
  public function deleteBuildConfig($pid, $build_id) {
    $request = $this->client->delete("oapi/v1/namespaces/$pid/buildconfigs/$build_id");
    try {
      $request->send();
    }
    catch (RuntimeException $exception) {
      throw $exception;
    }
    return ($request->getResponse() !== NULL && $request->getResponse()->getStatusCode() === 200);
  }

  /**
   * Returns the build config for a specific OpenShift project ID.
   *
   * @param string $pid
   *   The OpenShift project ID to query build configs against.
   * @param string $build_id
   *   The OpenShift build ID string.
   *
   * @return array
   *   A build config description array.
   *
   * @throws RuntimeException
   *   Signifies an issue has occurred generating an HTTP Request.
   */
  public function getBuildConfig($pid, $build_id) {
    $request = $this->client->get("oapi/v1/namespaces/$pid/buildconfigs/$build_id");
    try {
      $response = $request->send();
    }
    catch (RuntimeException $exception) {
      if ($request->getResponse() !== NULL && $request->getResponse()->getStatusCode() === 404) {
        return array();
      }
      throw $exception;
    }
    if ($body = $response->getBody()) {
      return drupal_json_decode($body);
    }
    return array();
  }

  /**
   * Returns the build config for a specific OpenShift project ID.
   *
   * @param string $pid
   *   The OpenShift project ID to query build configs against.
   *
   * @return array
   *   A build config description array.
   *
   * @throws RuntimeException
   *   Signifies an issue has occurred generating an HTTP Request.
   */
  public function getBuildConfigs($pid) {
    $request = $this->client->get("oapi/v1/namespaces/$pid/builds");
    try {
      $response = $request->send();
    }
    catch (RuntimeException $exception) {
      throw $exception;
    }
    if ($body = $response->getBody()) {
      $data = drupal_json_decode($body);
      return isset($data['items']) ? $data['items'] : array();
    }
    return array();
  }

  /**
   * Sets a build config's updated configuration.
   *
   * @param string $pid
   *   The OpenShift project ID.
   * @param string $config_name
   *   The OpenShift build config name tag.
   * @param array $config
   *   The build config description array to update.
   *
   * @return bool
   *   TRUE if the build configuration was updated successfully.
   *
   * @throws RuntimeException
   *   Signifies an issue has occurred generating an HTTP Request.
   *
   * @code
   * $config = array(
   *   'spec' => array(
   *     'source' => array(
   *       'contextDir' => '',
   *       'git' => array(
   *         'url' => 'https://github.com/openshift/ruby-hello-world',
   *         'ref' => 'beta4',
   *     ),
   *   ),
   * );
   * $osa->createBuildConfig('p12345', $config);
   * @endcode
   *
   * @see https://docs.openshift.org/latest/rest_api/openshift_v1.html#v1-buildconfig
   * @see https://docs.openshift.org/latest/rest_api/openshift_v1.html#partially-update-the-specified-buildconfig
   */
  public function setBuildConfig($pid, $config_name, array $config) {
    $headers = array(
      'Content-Type' => 'application/strategic-merge-patch+json',
    );
    $request = $this->client->patch(
      "namespaces/$pid/buildconfigs/$config_name",
      "oapi/v1/namespaces/$pid/buildconfigs/$config_name",
      $headers,
      drupal_json_encode($config));
    try {
      $request->send();
    }
    catch (RuntimeException $exception) {
      throw $exception;
    }
    return ($request->getResponse() !== NULL && $request->getResponse()->getStatusCode() === 200);
  }

  /**
   * Instantiates a new build request for a build configuration.
   *
   * @param string $pid
   *   The OpenShift project ID.
   * @param string $config_name
   *   The OpenShift build config name tag.
   * @param array $config
   *   The build request array to build.
   *
   * @return array|mixed
   *   A BuildRequest response body array upon success, empty otherwise.
   *
   * @see https://docs.openshift.org/latest/rest_api/openshift_v1.html#create-instantiate-of-a-buildrequest
   * @see https://docs.openshift.org/latest/rest_api/openshift_v1.html#v1-buildrequest
   */
  public function instantiateBuild($pid, $config_name, array $config) {
    $headers = array(
      'Content-Type' => 'application/json',
    );
    $request = $this->client->post("oapi/v1/namespaces/$pid/buildconfigs/$config_name/instantiate", $headers, drupal_json_encode($config));
    try {
      $response = $request->send();
    }
    catch (RuntimeException $exception) {
      if ($request->getResponse() !== NULL && $request->getResponse()->getStatusCode() !== 201) {
        return array();
      }
      throw $exception;
    }
    if ($body = $response->getBody()) {
      return drupal_json_decode($body);
    }
    return array();
  }

  /**
   * @}
   *
   * Methods related to Open Shift Image Streams.
   *
   * @ingroup image_stream
   */

  /**
   * Creates a new OpenShift image stream.
   *
   * @param string $pid
   *   The OpenShift project ID.
   * @param array $config
   *   The build config description array to create.
   * @param bool $use_defaults
   *   Set to FALSE to avoid using common default settings.
   *
   * @return bool
   *   TRUE if the image stream was created successfully.
   *
   * @throws RuntimeException
   *   Signifies an issue has occurred generating an HTTP Request.
   *
   * @code
   * $config = array(
   *   'metadata' => array(
   *     'name' => 'my-builds',
   *   ),
   * );
   * $osa->createBuildConfig('p12345', $config);
   * @endcode
   *
   * @see https://docs.openshift.org/latest/rest_api/openshift_v1.html#v1-imagestream
   * @see https://docs.openshift.org/latest/rest_api/openshift_v1.html#create-an-imagestream
   */
  public function createImageStream($pid, array $config, $use_defaults = TRUE) {
    $headers = array(
      'Content-Type' => 'application/json',
    );
    $defaults = array(
      'kind' => 'ImageStream',
      'apiVersion' => 'v1',
      'metadata' => array(
        'creationTimestamp' => REQUEST_TIME,
        'labels' => array(
          'build' => $config['metadata']['name'],
        ),
      ),
      'spec' => array(),
      'status' => array(
        'dockerImageRepository' => '',
      ),
    );
    if ($use_defaults) {
      $config += $defaults;
    }
    $request = $this->client->post("oapi/v1/namespaces/$pid/imagestreams", $headers, drupal_json_encode($config));
    try {
      $request->send();
    }
    catch (RuntimeException $exception) {
      throw $exception;
    }
    return ($request->getResponse() !== NULL && $request->getResponse()->getStatusCode() === 201);
  }

  /**
   * Gets an OpenShift Image Stream by project and name.
   *
   * @param string $pid
   *   The OpenShift project ID.
   * @param string $stream_id
   *   The OpenShift image stream ID.
   *
   * @return array
   *   An array of OpenShift image streams.
   *
   * @throws RuntimeException
   *   Signifies an issue has occurred generating an HTTP Request.
   *
   * @see https://docs.openshift.org/latest/rest_api/openshift_v1.html#list-or-watch-objects-of-kind-imagestream
   */
  public function getImageStream($pid, $stream_id) {
    $request = $this->client->get("oapi/v1/namespaces/$pid/imagestreams/$stream_id");
    try {
      $response = $request->send();
    }
    catch (RuntimeException $exception) {
      if ($request->getResponse() !== NULL && $request->getResponse()->getStatusCode() === 404) {
        return array();
      }
      throw $exception;
    }
    if ($body = $response->getBody()) {
      return drupal_json_decode($body);
    }
    return array();
  }

  /**
   * Gets a list of OpenShift Image Streams by project.
   *
   * @param string $pid
   *   The OpenShift project ID.
   *
   * @return array
   *   An array of OpenShift image streams.
   *
   * @throws RuntimeException
   *   Signifies an issue has occurred generating an HTTP Request.
   *
   * @see https://docs.openshift.org/latest/rest_api/openshift_v1.html#list-or-watch-objects-of-kind-imagestream
   */
  public function getImageStreams($pid) {
    $request = $this->client->get("oapi/v1/namespaces/$pid/imagestreams");
    try {
      $response = $request->send();
    }
    catch (RuntimeException $exception) {
      throw $exception;
    }
    if ($body = $response->getBody()) {
      $data = drupal_json_decode($body);
      return isset($data['items']) ? $data['items'] : array();
    }
    return array();
  }

  /**
   * @}
   *
   * Methods related to Open Shift Source Secrets.
   *
   * @ingroup secrets
   */

  /**
   * Creates a new OpenShift source secret.
   *
   * @param string $pid
   *   The OpenShift project ID.
   * @param array $data
   *   The secret description used to create the secret.
   * @param bool $use_defaults
   *   Set to TRUE to use the secret description defaults.
   *
   * @return bool
   *   Returns TRUE if the secret creation succeeded.
   *
   * @throws RuntimeException
   *   Signifies an issue has occurred generating an HTTP Request.
   */
  public function createSourceSecret($pid, array $data, $use_defaults = TRUE) {
    $headers = array(
      'Content-Type' => 'application/json',
    );
    $defaults = array(
      'kind' => 'Secret',
      'apiVersion' => 'v1',
      'metadata' => array(
        'name' => 'scmsecret',
        'creationTimestamp' => self::getTimestamp(),
      ),
      'data' => array(),
      'type' => 'Opaque',
    );
    if ($use_defaults) {
      $data += $defaults;
    }
    $request = $this->client->post("api/v1/namespaces/$pid/secrets", $headers, drupal_json_encode($data));
    try {
      $request->send();
    }
    catch (RuntimeException $exception) {
      throw $exception;
    }
    return ($request->getResponse() !== NULL && $request->getResponse()->getStatusCode() === 201);
  }

  /**
   * Deletes the specified OpenShift source secret.
   *
   * @param string $pid
   *   The OpenShift project ID from which to remove the secret.
   * @param string $secret_name
   *   The OpenShift secret name to remove.
   *
   * @return bool
   *   Returns TRUE if the operation succeeded.
   *
   * @throws RuntimeException
   *   Signifies an issue has occurred generating an HTTP Request.
   */
  public function deleteSourceSecret($pid, $secret_name) {
    $request = $this->client->delete("api/v1/namespaces/$pid/secrets/$secret_name");
    try {
      $request->send();
    }
    catch (RuntimeException $exception) {
      throw $exception;
    }
    return ($request->getResponse() !== NULL && $request->getResponse()->getStatusCode() === 200);
  }

  /**
   * Gets an OpenShift Secret by project and name.
   *
   * @param string $pid
   *   The OpenShift project ID.
   * @param string $secret_id
   *   The OpenShift secret name.
   *
   * @return array
   *   An OpenShift secret description.
   *
   * @throws RuntimeException
   *   Signifies an issue has occurred generating an HTTP Request.
   *
   * @see https://docs.openshift.org/latest/rest_api/openshift_v1.html#list-or-watch-objects-of-kind-imagestream
   */
  public function getSourceSecret($pid, $secret_id) {
    $request = $this->client->get("api/v1/namespaces/$pid/secrets/$secret_id");
    try {
      $response = $request->send();
    }
    catch (RuntimeException $exception) {
      if ($request->getResponse() !== NULL && $request->getResponse()->getStatusCode() === 404) {
        return array();
      }
      throw $exception;
    }
    if ($body = $response->getBody()) {
      return drupal_json_decode($body);
    }
    return array();
  }

  /**
   * @}
   *
   * @ingroup utility
   */

  /**
   * Returns an OpenShift compatbile timestamp.
   *
   * @param int $ts
   *   An optional integer timestamp to convert.
   *
   * @return string
   *   OpenShift compatible timestamp.
   */
  public static function getTimestamp($ts = NULL) {
    $ts = $ts ?: REQUEST_TIME;
    // Timezone appears to not be supported at this time.
    return date('Y-m-d', $ts)
      . 'T' . date('H:i:s', $ts)
      . 'Z';
  }

  /**
   * Validates a JSON Web Token as much as possible without the key.
   *
   * @param string $jwt
   *   The JSON Web Token string to validate.
   *
   * @return array
   *   An array of validation errors, or an empty array if passing.
   */
  public static function validateJwt($jwt) {
    $errors = array();
    if (!empty($jwt) && !preg_match('/^[a-zA-Z0-9\/\r\n+]*={0,2}$/', $jwt)) {
      $jwt_parts = explode('.', $jwt);
      if (count($jwt_parts) < 3) {
        $errors[] = t('The secret is not a JSON Web Token - make sure all three parts are included.');
        return $errors;
      }
      /** @var array $jwt_part */
      foreach (self::$jwtKeys as $index => $jwt_part) {
        $part_names = array_keys($jwt_part);
        $part_name = reset($part_names);
        $part_decoded = base64_decode($jwt_parts[$index], TRUE);
        if (trim(@base64_encode($part_decoded), '=') !== $jwt_parts[$index]) {
          $errors[] = t("The JWT %part does not appear to be base64 encoded, perhaps use '%encode'?.",
            array(
              '%part' => $part_name,
              '%encode' => base64_encode($jwt_parts[$index]),
            ));
          continue;
        }
        $part_data = drupal_json_decode($part_decoded);
        foreach (reset($jwt_part) as $part_key) {
          if (!isset($part_data[$part_key])) {
            $errors[] = t('The JWT Header is missing the %key property.',
              array('%key' => $part_key));
          }
        }
      }
    }
    return $errors;
  }

}
