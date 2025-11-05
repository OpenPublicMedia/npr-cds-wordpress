# Plugin Filters

Aside from the settings presented inside the WordPress dashboard, there are also multiple places that settings and options can be tweaked within the plugin. Below is a list of the filters and what they affect.

## `npr_cds_get_stories_capability`
* **Function**: Allows a site to set the minimum capabilities needed to pull stories into WordPress from the CDS
* **Arguments**
  * `$capability (string)`: default is `edit_posts`
* **Return**: `$capability`

## `npr_pre_update_post_metas`
* **Function**: Allows a site to modify the post meta values prior to inserting the CDS story into the database
* **Arguments**
  * `$metas (array)`: array of key/value pairs to be inserted as post metadata
  * `$post_id (int)`: Post ID from the database or NULL if it hasn't been inserted yet
  * `$story (stdClass)`: story object created during import
  * `$created (boolean)`: true if story exists in the database, false otherwise
  * `$qnum (int)`: the number of the current query
* **Return**: `$metas`

## `npr_pre_insert_post`
* **Function**: Allows a site to modify the `$args` passed to `wp_insert_post()` prior to post being inserted
* **Arguments**
    * `$args (array)`: parameters that will be passed to `wp_insert_post()` 
    * `$post_id (int)`: Post ID from the database or NULL if it hasn't been inserted yet
    * `$story (stdClass)`: story object created during import
    * `$created (boolean)`: true if story exists in the database, false otherwise
    * `$qnum (int)`: the number of the current query
* **Return**: `$args`

## `npr_pre_update_post`
* **Function**: Allows a site to modify the `$args` passed to `wp_insert_post()` prior to post being updated
* **Arguments**
    * `$args (array)`: parameters that will be passed to `wp_insert_post()`
    * `$post_id (int)`: Post ID from the database or NULL if it hasn't been inserted yet
    * `$story (stdClass)`: story object created during import
    * `$qnum (int)`: the number of the current query
* **Return**: `$args`

## `npr_resolve_category_term`
* **Function**: Allow a site to modify the terms looked-up before adding them to list of categories
* **Arguments**
    * `$term_name (string)`: name of term
    * `$post_id (int)`: Post ID from the database or NULL if it hasn't been inserted yet
    * `$story (stdClass)`: story object created during import
    * `$qnum (int)`: the number of the current query
* **Return**: `$term_name`

## `npr_pre_set_post_categories`
* **Function**: Allow a site to modify category IDs before assigning to the post
* **Arguments**
    * `$category_ids (array)`: array of Category IDs to assign to post identified by `$post_id`
    * `$post_id (int)`: Post ID from the database or NULL if it hasn't been inserted yet
    * `$story (stdClass)`: story object created during import
    * `$qnum (int)`: the number of the current query
* **Return**: `$term_name`

## `npr_pre_article_push`
* **Function**: Allow a site to modify the HTTP header/body before pushing to the CDS
* **Arguments**
  * `$options (array)`: HTTP request options, including the header and body
  * `$cds_id (string)`: the CDS ID of the story to be pushed
* **Return**: `$options`
* 
## `npr_pre_article_delete`
* **Function**: Allow a site to modify the HTTP header/body before deleting from the CDS
* **Arguments**
  * `$options (array)`: HTTP request options, including the header and body
* **Return**: `$options`

## `npr_cds_shortcode_filter`
* **Function**: Allow a site to modify or process any shortcodes in a pushed article before they are either processed or stripped
* **Arguments**
  * `$content (string)`: the article content
* **Return**: `$content`

## `npr_cds_push_service_ids_filter`
* **Function**: Allow a site to modify the ownership of a CDS document before pushing to the CDS 
* **Arguments**
  * `$service_id (string)`: a comma-separated list of service IDs, which are individually formatted as `s###`
  * `$post (WP_Post)`: the WordPress Post object that is being pushed to the CDS
* **Return**: `$service_id`

## `npr_cds_push_post_type_filter`
* **Function**: Allow a site to modify the post type being pushed to the CDS on a case-by-case basis. This allows for sites to push more than one post type while keeping one as their primary. If the metadata fields for the secondary post type differ from the primary, the site can use the mapping filters below in conjunction.
* **Arguments**
  * `$option (string)`: the name of the current push post type
  * `$post (WP_Post)`: the WordPress Post object that is being pushed to the CDS
* **Return**: `$option`

## `npr_cds_mapping_title_filter`
* **Function**: Allow a site to modify which metadata field is being used to supply the title for the post. This is intended for use in conjunction with the `npr_cds_push_post_type_filter` to support a secondary post type for pushing to the CDS.
* **Arguments**
  * `$option (string)`: the name of the current metadata field
  * `$post (WP_Post)`: the WordPress Post object that is being pushed to the CDS
* **Return**: `$option`

## `npr_cds_mapping_body_filter`
* **Function**: Allow a site to modify which metadata field is being used to supply the body for the post. This is intended for use in conjunction with the `npr_cds_push_post_type_filter` to support a secondary post type for pushing to the CDS.
* **Arguments**
  * `$option (string)`: the name of the current metadata field
  * `$post (WP_Post)`: the WordPress Post object that is being pushed to the CDS
* **Return**: `$option`

## `npr_cds_mapping_byline_filter`
* **Function**: Allow a site to modify which metadata field is being used to supply the byline for the post. This is intended for use in conjunction with the `npr_cds_push_post_type_filter` to support a secondary post type for pushing to the CDS.
* **Arguments**
  * `$option (string)`: the name of the current metadata field
  * `$post (WP_Post)`: the WordPress Post object that is being pushed to the CDS
* **Return**: `$option`

$bylines = apply_filters( 'npr_cds_bylines_filter', $bylines, $post->ID );

## `npr_cds_bylines_filter`
* **Function**: Allow sites one last stop for modifying their bylines before converting them to the CDS format
* **Arguments**
	* `$bylines (array)`: an array of user or display names for use in generating the bylines for the CDS
	* `$id`: the ID of the Post that is being pushed to the CDS
* **Return**: `$bylines`