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
   * Timestamp when the Syncope user was last updated.
   *
   * @var int
   */
  protected $updated;

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
   * Returns the updated timestamp.
   *
   * @return int
   *   The timestamp.
   */
  public function getUpdated(): int {
    return $this->updated;
  }

  /**
   * Sets the updated timestamp.
   *
   * @param int $updated
   *   The timestamp.
   */
  public function setUpdated(int $updated): void {
    $this->updated = $updated;
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

  /**
   * Checks if the user is a root level user.
   *
   * @return bool
   *   Whether the user is root level.
   */
  public function isRootUser(): bool {
    if (strpos($this->getName(), '@') === FALSE) {
      return TRUE;
    }

    return FALSE;
  }

}
