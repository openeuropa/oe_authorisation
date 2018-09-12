<?php

declare(strict_types = 1);

namespace Drupal\oe_authorisation_syncope\Syncope;

/**
 * Represents a Syncope group.
 */
class SyncopeGroup {

  /**
   * The UUID of the group as found in Syncope.
   *
   * @var string
   */
  protected $uuid;

  /**
   * The name of the group as found in Syncope.
   *
   * @var string
   */
  protected $syncopeName;

  /**
   * The name of the group as mapped in Drupal (role).
   *
   * @var string
   */
  protected $drupalName;

  /**
   * SyncopeGroup constructor.
   *
   * @param string $uuid
   *   The group UUID.
   * @param string $syncopeName
   *   The name of the group in Syncope.
   * @param string $drupalName
   *   The name of the group in Drupal.
   */
  public function __construct(string $uuid, string $syncopeName, string $drupalName) {
    $this->uuid = $uuid;
    $this->syncopeName = $syncopeName;
    $this->drupalName = $drupalName;
  }

  /**
   * @return string
   */
  public function getUuid(): string {
    return $this->uuid;
  }

  /**
   * @return string
   */
  public function getSyncopeName(): string {
    return $this->syncopeName;
  }

  /**
   * @return string
   */
  public function getDrupalName(): string {
    return $this->drupalName;
  }

}
