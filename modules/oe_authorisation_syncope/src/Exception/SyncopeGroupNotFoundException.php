<?php

declare(strict_types = 1);

namespace Drupal\oe_authorisation_syncope\Exception;

/**
 * Exception class to throw when we have issues with the mapping of the roles.
 */
class SyncopeGroupNotFoundException extends SyncopeGroupException {}
