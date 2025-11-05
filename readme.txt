=== NPR Content Distribution Service ===
Contributors: jwcounts, tamw-wnet, bdivver
Original developers: NPRDS, INN Labs
Donate link: https://www.npr.org/support
Tags: npr, news, public radio, api
Requires at least: 4.0
Tested up to: 6.8.3
Requires PHP: 8.0
Version: 1.4
Stable tag: 1.4
Author: Open Public Media
Author URI: https://github.com/OpenPublicMedia/
License: GPLv2
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A collection of tools for reusing content from NPR.org, now maintained and updated by NPR member station developers.

== Description ==

The [NPR Content Distribution System (CDS)](https://npr.github.io/content-distribution-service/) Plugin provides push and pull functionality with the NPR CDS along with a user-friendly administrative interface.

NPR's CDS is a content API, which essentially provides a structured way for other computer applications to get NPR stories in a predictable, flexible and powerful way. The content that is available includes audio from most NPR programs dating back to 1995 as well as text, images and other web-only content from NPR and NPR member stations. This archive consists of over 250,000 stories that are grouped into more than 5,000 different aggregations.

This plugin also allows you to push your content to the NPR CDS, so that it can be republished by NPR or other NPR member stations.

Access to the NPR CDS requires a bearer token, provided by NPR. If you are an NPR member station or are working with an NPR member station and do not know your key, please [ask NPR station relations for help](https://studio.npr.org).

Usage of this plugin is governed by [NPR's Terms of Use](https://www.npr.org/about-npr/179876898/terms-of-use), and more specifically their [API Usage terms](https://www.npr.org/about-npr/179876898/terms-of-use#APIContent).

The WordPress plugin was originally developed as an Open Source plugin by NPR and is now supported by developers with NPR member stations working within the Open Public Media group. If you would like to suggest features or bug fixes, or better yet if you would like to contribute new features or bug fixes please visit our [GitHub repository](https://github.com/OpenPublicMedia/npr-cds-wordpress) and post an issue or contribute a pull request.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/plugin-name` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Use the **NPR CDS -> Main Settings** screen to configure the plugin. Begin by entering your CDS token, then add your Push URL and Org ID.


== Frequently Asked Questions ==

= Can anyone get an NPR CDS Token? =

If you are an NPR member station or are working with an NPR member station and do not know your key, please [ask NPR station relations for help](https://studio.npr.org).

= Can anyone push content into the NPR CDS using this plugin? =

Push requires an Organization ID in the NPR CDS, which is typically given out to only NPR stations and approved content providers. If that's you, you probably already have an Organization ID.

= Where can I find NPR's documentation on the NPR CDS? =

There is documentation in the NPR's [Github site](https://npr.github.io/content-distribution-service/).

== Screenshots ==

NPR CDS Plugin Settings screen

![NPR CDS Plugin Settings screen](docs/assets/img/npr-api-wp-plugin-settings.png.webp)

NPR CDS multiple get settings

![NPR CDS multiple get settings](docs/assets/img/npr-api-multiple-get-settings.png.webp)

Get NPR Stories link in the dashboard

![Get NPR Stories link in the dashboard](docs/assets/img/get-npr-stories-link.png.webp)

Getting an NPR Story by CDS ID

![Getting NPR Stories by CDS ID](docs/assets/img/get-npr-stories-link.png.webp)

NPR Stories having been retrieved

![NPR Stories having been retrieved](docs/assets/img/npr-stories.png.webp)


== Changelog ==
= V.1.4 =
* Consolidated all of the admin dashboard pages into a unified menu
* Added a dashboard to view stories uploaded to the CDS. Contains general publishing information, as well as info on why or why not the story is eligible for the NPR homepage
* Added a setting in `NPR CDS > Main Settings` to set the default state of the 'Include for NPR One/NPR Homepage' checkbox (defaults to unchecked)
* Fixing a typo in an option name and adding a filter for modifying bylines before CDS formatting (h/t to @kaymly for both)

= V.1.3.7 =
* Stories submitted to the CDS can now be featured on the NPR homepage. Most the requirements for feature were included in the plugin previously, but the features require a 16:9 image, which this update addresses. This update creates a new image size which is a strict 16:9 crop, and also formats and sends more image crops to the CDS, with the full sized image as a backstop.
* Implementing filters for push post type, custom title, custom body, and custom byline (h/t @justinferrell for the suggestion)

= V.1.3.6.1 =
* Added a check to prevent fatal error on collections with no rels (h/t @tamw-wnet for the fix)

= V.1.3.6 =
* Fixed bug that prevented category selection for posts imported via Get Multi queries from saving properly (h/t @xpn-bdivver for the fix)

= V.1.3.5 =
* Switched Yoast plugin detection to work better in multisite setups (h/t @tamw-wnet for the fix)

= V.1.3.4 =
* The global `import tags` setting was being overridden if a Get Multi query was created at position 0. `NPR_CDS_WP->update_posts_from_stories()` accepts 2 arguments (`publish` and `query number`), but both are optional and default to `true` and `0` respectively. So, if a post is imported outside of
 a Get Multi query, it would pull and apply the settings for `npr_cds_query_0` anyway, leading to undesired behavior. The default query number has been changed to `-1`. (h/t @kic00 for the catch)
* Change to how canonical URL meta tags are inserted if the Yoast SEO plugin is present (h/t @tamw-wnet for the catch and fix)

= V.1.3.3 =
* Publish dates and last modified dates for the CDS were both being generated from `$post->post_modified_gmt`, which is incorrect. Also, both dates are being generated from the non-GMT dates, as the `mysql2date()` function takes the localized timezone into account (h/t @jsonmorris for the catch)
* Fixed a bug that was causing the `Send to NPR One` and `Feature in NPR One` checkboxes to not save correctly
* Fixed a bug that was causing the `Feature in NPR One` expiration date to not be properly saved, which was causing it to default to publish time + 7 days regardless

= V.1.3.2 =
* Imported images now have titles, captions, and alt text, if available
* Fixed a bug if video embeds have empty enclosures

= V.1.3.1 =
* Fixed a bug where the service ID was not being set properly on uploaded articles

= V.1.3 =
* "Org ID" renamed to "Service ID" in various places to try to better reflect the guidance from NPR
* Service IDs in `settings.php` can now be a comma-separated list, if all posts will be co-owned
* Added `npr_cds_push_service_ids_filter` so ownership can be modified on an ad-hoc basis, if needed
* Fixed a bug where, under certain conditions, checking audio enclosures for the premium value can trigger an exception (h/t @areynold)
* Laying groundwork for potential WP-JSON endpoint
* Minor formatting and bug fixes

= V.1.2.11 =
* Setting the `npr_has_layout` flag for imported articles to help with backwards compatibility (h/t @tamw-wnet)
* Fixed a potential fatal error in `NPR_CDS_WP` when passing promo card rels

= V1.2.10 =
* Fixed a bug where audio files were getting attached to stories without the proper profiles, leading to push errors

= V1.2.9 =
* `npr_cds_show_message()` only echoes in admin dashboard
* Promo cards without valid `webPages` array are ignored

= V1.2.8.1 =
* Fixed error logging bug which was causing blank errors on failed uploads

= V1.2.8 =
* Changed the activation function to fully remove all of the old NPR Story API options after migrating them
* Changed the deactivation function to save all of the previous settings into a site option and delete the individual options
* Added functions to allow for restoring settings from a previous install or deleting those stored settings
* Updated a bunch of stray references to the old Story API
* Documentation updates

= V1.2.7 =
* `NPR_CDS_WP` has been updated to block/ignore stories and assets that have been marked as restricted in the CDS (meaning they are not eligible for syndication)

= V1.2.6 =
* `profileIds` can now be sent to `NPR_CDS_WP` as a comma-separated string (OR), or as multiple entries (AND)
* Fixed a bug in `NPR_CDS_WP` where `$this->request->params` was not being properly populated

= V1.2.5 =
* Updated the `NPR_CDS_WP->get_image_url()` function to handle image URLs from NPR's new CDN
* Fixed an issue where featured images were not downloading properly on imported NPR articles because a proper filename was not being provided
* When generating the article layout, if not using feature images, imported articles will have the primary image at the top of the layout (if one exists)
  * NPR frequently inserts the primary image as the first asset in the layout, while Grove stations typically don't. I don't know if this is intended or not, but this is an attempt to normalize the output across the board
* Thanks to [Iris Vandenham at Flower Web Design](https://www.flower-webdesign.com) for bringing these issues to our attention

= V1.2.4 =
* Fixed a bug that broke the Update link in the admin dashboards
* Fixed a potentially fatal error caused by malformed audio embeds in imported articles
* Fixed a bug where the cron schedules were not being set or updated after an initial error
* Fixed a potentially fatal error caused by importing articles with nonexistent teasers
* Thanks to @tamw-wnet for the assist on all above

= V1.2.3 =
* Adding a setting to skip ingesting promo cards from NPR (h/t @davidmpurdy)
* Updated formatting support for promo cards

= V1.2.2 =
* Fixed a bug in how the plugin was handling `$image->hrefTemplates`

= V1.2.1 =
* Reworked the `load_page_hook()` function in `get_stories.php` to account for NPR's updated ID structure now that they have also moved to Grove

= V1.2 =
* Fixed a bug that was preventing media credits from being appended to image captions
* Added a setting to append an attribution line at the end of imported articles (default 'no')
* Added a setting to import an article's tags from the CDS (default 'yes')
* Removed a filter from the documentation since it had already been removed from the code
* Consolidating get_stories_ui.php into the NPR_CDS class in get_stories.php
* Fixing a bug that was preventing NPR_CDS::load_page_hook() from firing on custom post types
* Removing old TODOs
* Added check to avoid downloading or inserting images that cannot be distributed via the CDS

= V1.1.3 =
* Increased the timeout for cURL requests when pushing stories

= V1.1.2 =
* Fixed a bug where unticking the 'Send to NPR CDS' checkbox in the editor would result in the article being pushed anyway

= V1.1.1 =
* Fixed a bug that was causing article metadata to be saved using an invalid key when ingesting articles

= V1.1 =
* Breaking the settings screens into 3 separate pages
* Adding the ability to select the pull post type for each query (leave blank to use the default)

= V1.0.8.1 =
* Fixing labels on NPR Story custom post type

= V1.0.8 =
* NPR CDS metabox now appears higher in the sidebar by default
* Added setting for whether or not you want the "Send to NPR CDS" checkbox checked by default

= V1.0.7 =
* Bug fixes related to bylines: plugin now saves bylines under the proper metadata key, no longer ignore non-reference bylines, and doesn't write empty bylines when the Co-Authors Plus plugin is present (h/t Andrew Reynolds at WAMU)
* 'Get NPR Stories' menu option now aligns to whichever `pull_post_type` is selected in settings (h/t Kaym Yusuf at WAMU)
* Fixed a couple of typos that were preventing the 'Theme Uses Featured Image' settings from being honored (h/t David Purdy)
* Adding documentation for all of the filters that are available in the plugin

= V1.0.6 =
* npr_cds_push(): lack of publishing rights exits the function instead of killing the process

= V1.0.5 =
* Default cron job timing is now 1 hour (was previously a minute because it wasn't properly being converted into seconds)
* Cron jobs are no longer scheduled if there are no queries configured

= V1.0.4 =
* Fixing a problem where Get Multi queries run via WP_Cron would silently fail because `npr_cds_push()` would check if the current user had publishing rights and execute `wp_die()`. WP_Cron doesn't run as a specific user, therefore it doesn't technically have publishing rights. `npr_cds_push()`
  now checks if the post was retrieved from the CDS first before checking publishing rights.

= V1.0.3 =
* Fixing warnings and a few fatal errors in `NPR_CDS_WP.php`

= V1.0.2 =
* Fixing improperly escaped HTML in post editor meta boxes

= V1.0.1 =
* Fixing a bunch of warnings in `classes/NPR_CDS_WP.php`
* Changing the behavior the `Get Multi` setting `Run the queries on saving changes` so that it runs once and then turns off. That way, it doesn't run every time you save changes on the page.
* Fixed issue where updates to bulk actions dropdowns were being duplicated

= V1.0 =
* Overhaul to enable pulling from NPR's Content Distribution Service, which is the next generation of the Story API.
* Previous version notes can be found in the [NPR Story API plugin repository](https://github.com/OpenPublicMedia/nprapi-wordpress)