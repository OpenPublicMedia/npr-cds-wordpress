# Installation

1. Download the plugin files from this repository
2. Unzip and upload the unzipped plugin folder to the `/wp-content/plugins/` directory of your WordPress install OR go to **Plugins > Add New** from the WordPress dashboard and upload the plugin zip file
3. Activate the plugin through the **Plugins** screen in WordPress
4. Use the **Settings > NPR CDS** screen to configure the plugin. Begin by entering your bearer token, org ID, and document prefix, then select your Push and Pull URLs.

If you don't have a bearer token or Org ID you can request them by [registering for an NPR account](https://studio.npr.org).

The Push URL for the NPR Production API is `https://content.api.npr.org`.

The Push URL for testing purposes is `https://stage-content.api.npr.org`.

The Pull URL for production should be fine for testing as well: `https://content.api.npr.org`