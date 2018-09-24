# OpenEuropa Authorisation

The OpenEuropa Authorisation Syncope module controls the integration of Drupal with [Apache Syncope](https://syncope.apache.org/) which becomes the source of truth regarding user roles and user role mapping.
 
 
Out of the box the modules provides:
- Site role synchronization with Syncope.
- Global role support for roles coming from Syncope.
- User role synchronizaton when users login.

It interacts with Syncope in the following situations:
- Site Role is created/update/deleted.
- User logs in.
- User is edited.

It uses the following configuration to interact with Syncope:
- Apache Syncope API endpoint
- Apache Syncope username
- Apache Syncope password
- Apache Syncope domain
- Apache Syncope site realm

## Architecture and concepts
The module relies on [Syncope PHP Client](https://github.com/openeuropa/syncope-php-client) to interface with Apache Syncope API.

The module intersects role creation/update/delete in Drupal and creates an associated entity in Syncope to represent the role (group). 
It extends the role entity in order to store the Syncope UUID of the group in the associated role. 
It extends the user entity to store the Syncope UUID assocatiated with the user in the site context so it can be maintained in sync.

### General site structure in Syncope
Web sites are represented in Syncope as realms organized hierachically:

- /
  - /siteA
  - /siteB
  - /siteC

Each website has a system account with enough permissions to manage objects in the associated realm. This is the user used when interacting with the library.

The following concepts are directly mapped between Drupal and Syncope and are maintained in sync.

- Each Drupal site has realm *in Syncope*
- Each Drupal site has a system user with permissions to manage the associated realm *in Syncope*
- Each role in Drupal maps to a group inside a realm *in Syncope*
- Each user in Drupal maps to an OeUSer object in a realm associated with eulogin id *in Syncope*.

### Site roles
Site roles are speficic per sites and can vary amongst all sites. Any time a site role is created it gets synchronized with Syncope and can be assigned to users in the context of the site, and directly from site admin UI.

### Global roles
Global roles are defined in the context of the base realm and represent a role the user assumes in all sites. It can be used for roles that are transversal to all sites like support engineer.
Global roles are managed in syncope directly and are only consumed by Drupal at login time.

### User role mapping
User role mapping management is done from the Drupal admin UI, but role mapping is saved behind the scenes in Syncope.
Global roles are not assignable using the Drupal UI as they are managed outside of the website context.

### Permissions
Permissions associated with roles are exclusively managed by Drupal and can vary from site to site.

