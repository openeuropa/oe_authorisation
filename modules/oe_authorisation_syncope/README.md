# OpenEuropa Authorisation

The OpenEuropa Authorisation Syncope module controls the integration of Drupal with [Apache Syncope](https://syncope.apache.org/) which becomes the source of truth regarding user roles and user role mapping.
 
 
Out of the box the modules provides:
- Site-level role synchronization with Syncope.
- Global role support for cross-enterprise role assignment.
- User role synchronisation upon login.

It interacts with Syncope in the following situations:
- Site Role is created/update/deleted.
- User logs in.
- User is created/edited.

It uses the following configuration to interact with Syncope:
- Apache Syncope API endpoint
- Apache Syncope username
- Apache Syncope password
- Apache Syncope domain
- Apache Syncope site realm

## Architecture and concepts
The module relies on [Syncope PHP Client](https://github.com/openeuropa/syncope-php-client) to interface with the Apache Syncope API.

- The module hooks into the role creation/update/delete process in Drupal and creates an associated object in Syncope to represent the role (in Syncope this is called a `group`). 
- It extends the Drupal role entity in order to store the UUID of the corresponding Syncope group. 
- It extends the Drupal user entity in order to store the UUID of the corresponding Syncope user.

### General site structure in Syncope
Websites are represented in Syncope as realms organized hierarchically (starting from a root realm represented by `/`):

- /
  - /siteA
  - /siteB
  - /siteC

Each website has a system account with enough permissions to manage objects in the associated realm. This is the user used when interacting with the library.

The following concepts are directly mapped between Drupal and Syncope and are maintained in sync.

- Each Drupal site maps to a realm in Syncope
- Each Drupal site has a system user with permissions to manage the associated realm in Syncope
- Each role in Drupal maps to a group inside a site-level realm in Syncope
- Each user in Drupal maps to an OeUSer object inside a site-level realm in Syncope, which also stores an EULogin ID used for linking user objects across realms.

### Two types of roles

#### Site-level roles
Site level roles are mapped to Syncope within the realm associated with that site. The naming convention followed in Syncope for these is `[drupal_role_name]@[site_realm_name]`. 

#### Global roles
Global roles are mapped to Syncope within the root realm. The naming convention followed in Syncope for these is `[drupal_role_name]`.

### User role mapping
Site-level roles can be assigned to users both in Drupal and in Syncope. The single point of truth for this mapping, however, lies in Syncope. For this reason, when attempting to map roles in Drupal, the roles of that user are refreshed to reflect the current status in Syncope.

Global roles cannot be assigned from Drupal. They exist in each Drupal site connected to Syncope but they can only be assigned to a user from Syncope. 

Assigning global roles to a user  is intended to propagate to that user across all thr sites it uses. In order for this to happen, the role is assigned in Syncope to a global user object that has the intended EU Login ID (link between site-level and global-level user objects).

Upon logging in on any Drupal site, this global role is then assigned to the user based on this link.

### Permissions
Permissions associated with roles are exclusively managed by Drupal and can vary from site to site.

### Disabling syncope
The module can be enabled without the need to interact with Syncope by setting the following setting. This allows to test the associated website without the need to uninstall the module in cases where syncope server is not reachable.

```
$settings['syncope_client_disabled'] = TRUE;
```