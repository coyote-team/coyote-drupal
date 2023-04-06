# Coyote Image Description
The Coyote Image Description module affords integration with the [Coyote Image Description](https://coyote.pics)
application and API.

When this module is active, the alternative text for images within Drupal nodes will be managed through Coyote.

For a full description of the module, visit the [project page](https://www.drupal.org/project/coyote).

## Configuration
Once the module is installed, configure it at `/admin/config/coyote-img-desc-configuration`. This requires:
* A Coyote API token with at least `author` permission levels;
* a Coyote instance endpoint, `https://live.coyote.pics` for production use; and
* a Coyote Organization from which to process resources and descriptions.

### Resource group management
To handle synchronous representation updates on the Coyote application, the selected Coyote Organization requires a
valid resource group configuration. The module will attempt to automatically provision this, providing a resource
group ID.
If this fails, a notice such as `"Resource group 'Drupal' could not be created"` may appear. In this case, a resource
group with the same name but incorrect URL may already exist, or the API token may not have the required permissions.

## Batch importing of resources
After setting up the module, the configuration tab "Batch Processing" may be used to process all available nodes for
images. These are submitted in batches to the Coyote server. Depending on the installation size, this process may
take up significant time. It can be run as often as necessary.

## Maintainers

* Job van Achterberg - [JobPac](https://www.drupal.org/u/jobpac)