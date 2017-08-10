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
   * Openshift JWT required keys.
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
