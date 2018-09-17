<?php

declare(strict_types = 1);

namespace Drupal\oe_authorisation_syncope\Syncope;

/**
 * Represents a Syncope user.
 */
class SyncopeUser {

  /**
   * The UUID of the user as found in Syncope.
   *
   * @var string
   */
  protected $uuid;

  /**
   * The name of the user.
   *
   * @var string
   */
  protected $name;

  /**
   * The user groups (UUIDs)
   *
   * @var array
   */
  protected $groups = [];

  /**
   * SyncopeUser constructor.
   *
   * @param string $uuid
   *   The user UUID.
   * @param string $name
   *   The name of the user in Syncope.
   * @param array $groups
   *   The groups the user has in Syncope.
   */
  public function __construct(string $uuid, string $name, array $groups) {
    $this->uuid = $uuid;
    $this->name = $name;
    $this->groups = $groups;
  }

  /**
   * Returns the UUID of the user.
   *
   * @return string
   *   UUID of the user.
   */
  public function getUuid(): string {
    return $this->uuid;
  }

  /**
   * Returns the name of the user.
   *
   * @return string
   *   Name of the user.
   */
  public function getName(): string {
    return $this->name;
  }

  /**
   * Returns an array of available groups.
   *
   * @return array
   *   Array of available groups.
   */
  public function getGroups(): array {
    return $this->groups;
  }

}
