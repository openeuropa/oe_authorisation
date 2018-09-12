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
   * Whether it's the root realm.
   *
   * @var bool
   */
  protected $isRoot;

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
    $this->isRoot = $parent == '/';
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
  public function getName(): string {
    return $this->name;
  }

  /**
   * @return string
   */
  public function getPath(): string {
    return $this->path;
  }

  /**
   * @return string
   */
  public function getParent(): string {
    return $this->parent;
  }

  /**
   * @return bool
   */
  public function isRoot(): bool {
    return $this->isRoot;
  }

}
