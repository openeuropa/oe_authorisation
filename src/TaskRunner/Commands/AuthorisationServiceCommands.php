<?php

declare(strict_types = 1);

namespace Drupal\oe_authorisation\TaskRunner\Commands;

use GuzzleHttp\Client;
use OpenEuropa\SyncopePhpClient\Api\AnyTypeClassesApi;
use OpenEuropa\SyncopePhpClient\Api\AnyTypesApi;
use OpenEuropa\SyncopePhpClient\Api\RealmsApi;
use OpenEuropa\SyncopePhpClient\Api\RolesApi;
use OpenEuropa\SyncopePhpClient\Api\UsersApi;
use OpenEuropa\SyncopePhpClient\ApiException;
use OpenEuropa\SyncopePhpClient\Configuration;
use OpenEuropa\SyncopePhpClient\Model\AnyTypeClassTO;
use OpenEuropa\SyncopePhpClient\Api\SchemasApi;
use OpenEuropa\SyncopePhpClient\Model\AnyTypeTO;
use OpenEuropa\SyncopePhpClient\Model\RealmTO;
use OpenEuropa\SyncopePhpClient\Model\RoleTO;
use OpenEuropa\SyncopePhpClient\Model\UserTO;
use OpenEuropa\TaskRunner\Commands\AbstractCommands;
use OpenEuropa\SyncopePhpClient\Model\SchemaTO;
use Robo\Exception\TaskException;
use Symfony\Component\Console\Input\InputOption;

/**
 * Class AuthorisationServiceCommands.
 */
class AuthorisationServiceCommands extends AbstractCommands {

  /**
   * The Syncope domain.
   *
   * @var string
   */
  protected $xSyncopeDomain = 'Master';

  /**
   * Sets up the Authorisation Service (Syncope).
   *
   * @command oe-authorisation-service:setup
   */
  public function runAuthorisationServiceSetup(): void {
    $username = $this->getConfig()->get('authorisation.server.username');
    $password = $this->getConfig()->get('authorisation.server.password');
    $endpoint = $this->getConfig()->get('authorisation.server.endpoint');

    $config = Configuration::getDefaultConfiguration()
      ->setUsername($username)
      ->setPassword($password)
      ->setHost($endpoint)
      ->setDebug(TRUE);

    // Creates schema field.
    $schemaApi = new SchemasApi(
      new Client(),
      $config
    );

    $schemaTO = new SchemaTO(['key' => 'eulogin_id']);
    $schemaTO->setClass('org.apache.syncope.common.lib.to.PlainSchemaTO');

    try {
      $schemaApi->createSchema('PLAIN', $this->xSyncopeDomain, $schemaTO);
    }
    catch (ApiException $e) {
      throw new TaskException('Exception when calling SchemasApi->createSchema: ', $e->getMessage());
    }

    // Creates new AnyType class BaseOeUser.
    $anyTypeClassApi = new AnyTypeClassesApi(
      new Client(),
      $config
    );
    $anyTypeClassTo = new AnyTypeClassTO(['key' => 'BaseOeUser', 'plainSchemas' => ['eulogin_id']]);
    try {
      $anyTypeClassApi->createAnyTypeClass($this->xSyncopeDomain, $anyTypeClassTo);
    }
    catch (ApiException $e) {
      throw new TaskException('Exception when calling anyTypeClassApi->createAnyTypeClass: ', $e->getMessage());
    }

    // Creates new AnyType OeUser.
    $anyTypeApi = new AnyTypesApi(
      new Client(),
      $config
    );

    $anyTypeTO = new AnyTypeTO(
      [
        'key' => 'OeUser',
        'kind' => 'ANY_OBJECT',
        'classes' => ['BaseOeUser'],
      ]);
    try {
      $anyTypeApi->createAnyType($this->xSyncopeDomain, $anyTypeTO);
    }
    catch (ApiException $e) {
      throw new TaskException('Exception when calling anyTypeApi->createAnyType: ', $e->getMessage());
    }

    // Provision role to search across the realms.
    $rolesApi = new RolesApi(
      new Client(),
      $config
    );

    $roleTo = new RoleTO([
      'key' => 'system-user-site',
      'realms' => ['/'],
      'entitlements' => [
        'OeUser_SEARCH',
        'OeUser_READ',
      ],
    ]);

    try {
      $rolesApi->createRole($this->xSyncopeDomain, $roleTo);
    }
    catch (ApiException $e) {
      throw new TaskException('Exception when calling rolesApi->createRole: ', $e->getMessage());
    }

  }

  /**
   * Sets up the Authorisation Service test data.
   *
   * @param array $options
   *   Command options.
   *
   * @command oe-authorisation-service:site-setup
   *
   * @option site_id  Site_id to be provisioned.
   */
  public function runAuthorisationServiceSiteSetup(array $options = ['site_id' => InputOption::VALUE_REQUIRED]): void {

    $siteId = $options['site_id'];

    $username = $this->getConfig()->get('authorisation.server.username');
    $password = $this->getConfig()->get('authorisation.server.password');
    $endpoint = $this->getConfig()->get('authorisation.server.endpoint');

    $config = Configuration::getDefaultConfiguration()
      ->setUsername($username)
      ->setPassword($password)
      ->setHost($endpoint)
      ->setDebug(TRUE);

    // Provisions realm.
    $realmsApi = new RealmsApi(
      new Client(),
      $config
    );

    $realmTo = new RealmTO([
      'name' => $siteId,
    ]);

    $realmsApi = new RealmsApi(
      new Client(),
      $config
    );

    try {
      $realmsApi->createRootedRealm('', $this->xSyncopeDomain, $realmTo);
    }
    catch (ApiException $e) {
      throw new TaskException('realmsApi->createRootedRealm: ', $e->getMessage());
    }

    // Provision role.
    $rolesApi = new RolesApi(
      new Client(),
      $config
    );

    $roleTo = new RoleTO([
      'key' => 'system-admin-' . $siteId,
      'realms' => ['/' . $siteId],
      'entitlements' => [
        'OeUser_SEARCH',
        'OeUser_DELETE',
        'OeUser_CREATE',
        'OeUser_UPDATE',
        'OeUser_READ',
        'GROUP_CREATE',
        'GROUP_UPDATE',
        'GROUP_DELETE',
        'GROUP_READ',
        'GROUP_SEARCH',
      ],
    ]);

    try {
      $rolesApi->createRole($this->xSyncopeDomain, $roleTo);
    }
    catch (ApiException $e) {
      throw new TaskException('Exception when calling rolesApi->createRole: ', $e->getMessage());
    }

    // Provisions system site account.
    $usersApi = new UsersApi(
      new Client(),
      $config
    );

    $userTo = new UserTO([
      'username' => 'system-account-' . $siteId,
      'realm' => '/' . $siteId,
      'password' => 'password',
      'roles' => ['system-admin-' . $siteId, 'system-user-site']
    ]);

    $userTo->setClass('org.apache.syncope.common.lib.to.UserTO');

    try {
      $usersApi->createUser($this->xSyncopeDomain, $userTo, 'return-content', FALSE, NULL);
    }
    catch (ApiException $e) {
      throw new TaskException('Exception when calling usersApi->createUser: ', $e->getMessage());
    }
  }

}
