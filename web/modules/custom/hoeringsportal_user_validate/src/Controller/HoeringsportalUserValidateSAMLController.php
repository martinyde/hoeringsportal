<?php

namespace Drupal\hoeringsportal_user_validate\Controller;

use Drupal\Core\Url;
use Drupal\user\Entity\User;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\samlauth\SamlService;
use Drupal\samlauth\SamlUserService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;

/**
 * KobaBookingApiController.
 */
class HoeringsportalUserValidateSAMLController extends ControllerBase {

  /**
   * The saml service.
   *
   * @var \Drupal\samlauth\SamlService
   */
  protected $saml;

  /**
   * The saml user.
   *
   * @var \Drupal\samlauth\SamlUserService
   */
  protected $samlUser;

  /**
   * The saml.
   *
   * @var \OneLogin_Saml2_Auth
   */
  protected $auth;

  /**
   * Constructor for HoeringsportalUserValidateSAMLController.
   *
   * @param \Drupal\samlauth\SamlService $saml
   *   The saml service.
   * @param \Drupal\samlauth\SamlUserService $samlUser
   *   The saml user.
   * @param \OneLogin_Saml2_Auth $auth
   *   The saml.
   */
  public function __construct(SamlService $saml, SamlUserService $samlUser, \OneLogin_Saml2_Auth $auth) {
    $this->saml = $saml;
    $this->samlUser = $samlUser;
    $this->auth = $auth;
  }

  /**
   * Factory method for dependency injection container.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The container.
   *
   * @return static
   */
  public static function create(ContainerInterface $container) {
    $config = samlauth_get_config();
    $auth = new \OneLogin_Saml2_Auth($config);

    return new static(
      $container->get('samlauth.saml'),
      $container->get('samlauth.saml_user'),
      $auth
    );
  }

  /**
   * Save current path and redirect to SAML login.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   The response.
   */
  public function login(Request $request) {

    $uri = $request->get('ref');

    // Set newest booking information.
    $data = [
      'uri' => $uri,
      'expire' => \Drupal::time()->getRequestTime() + 300,
    ];

    // Store information in session.
    \Drupal::service('session')->set('hoeringsportal_user_validate', $data);

    return $this->redirect('samlauth.saml_controller_login');
  }

  /**
   * Login to nemid via SAML.
   *
   * Redirects to SAML logout that redirect to add booking.
   */
  public function acs() {
    $errors = $this->saml->acs();
    if (!empty($errors)) {
      $messenger = \Drupal::messenger();
      $messenger->addError($this->t('An error occured during login.'));
      return $this->redirect('samlauth.saml_controller_sls');
    }

    try {
      // Left here to show how to get the SAML data.
      $saml_data = $this->saml->getData();

      $date = DrupalDateTime::createFromTimestamp(\Drupal::time()->getRequestTime(), DateTimeItemInterface::STORAGE_TIMEZONE);

      // User is validated, so lets updated the user fields.
      $user = \Drupal::currentUser();
      $user = User::load($user->id());
      $user->set('field_valid', TRUE);
      $user->set('field_validation_date', $date->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT));
      $user->save();
    }
    catch (\Exception $e) {
      $messenger = \Drupal::messenger();
      $messenger->addError($e->getMessage());
      return $this->redirect('samlauth.saml_controller_sls');
    }

    // We need to log the user out of SAML login. But as this is not supported
    // by the ADFS (or not working). We use an iframe on the add booking page
    // that do the logout - HACK.
    $data = \Drupal::service('session')->get('hoeringsportal_user_validate');
    $uri = empty($data['uri']) ? Url::fromRoute('<front>')->toString() : $data['uri'];
    return new RedirectResponse($uri);
  }

}
