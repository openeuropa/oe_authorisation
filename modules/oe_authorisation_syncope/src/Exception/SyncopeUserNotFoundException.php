<?php

declare(strict_types = 1);

namespace Drupal\oe_authorisation_syncope\Exception;

/**
 * Exception class to throw when a user is not found in Syncope.
 */
class SyncopeUserNotFoundException extends SyncopeUserException {}
