<?php

declare(strict_types = 1);

namespace Drupal\oe_authorisation_syncope\Syncope;

/**
 * Represents a Syncope realm.
 */
class SyncopeRealm {

  /**
   * The UUID of the realm.
   *
   * @var string
   */
  protected $uuid;

  /**
   * The name of the realm.
   *
   * @var string
   */
  protected $name;

  /**
   * The path to the realm.
   *
   * @var string
   */
  protected $path;

  /**
   * The UUID of the parent realm.
   *
   * @var string
   */
  protected $parent;

  /**
   * SyncopeRealm constructor.
   *
   * @param string $uuid
   *   The UUID.
   * @param string $name
   *   The name.
   * @param string $path
   *   The full path to the realm.
   * @param string $parent
   *   The parent realm UUID.
   */
  public function __construct(string $uuid, string $name, string $path, string $parent) {
    $this->uuid = $uuid;
    $this->name = $name;
    $this->path = $path;
    $this->path = $path;
    $this->parent = $parent;
  }

  /**
   * Returns the UUID of the realm.
   *
   * @return string
   *   UUID of the realm.
   */
  public function getUuid(): string {
    return $this->uuid;
  }

  /**
   * Returns the name of the realm.
   *
   * @return string
   *   Name of the realm.
   */
  public function getName(): string {
    return $this->name;
  }

  /**
   * Returns the path of the realm.
   *
   * @return string
   *   Path of the realm.
   */
  public function getPath(): string {
    return $this->path;
  }

  /**
   * Returns the parent realm.
   *
   * @return string
   *   Parent realm.
   */
  public function getParent(): string {
    return $this->parent;
  }

  /**
   * Checks whether the realm is a root level realm or not.
   *
   * @return bool
   *   TRUE if the realm has no parents or FALSE otherwise.
   */
  public function isRoot(): bool {
    return $this->parent == '/';
  }

}
