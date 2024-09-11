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