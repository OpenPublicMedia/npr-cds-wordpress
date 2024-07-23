# Pulling Multiple Stories from the NPR CDS By Custom CDS Query

You can set up and save one or more CDS queries to pull content based on topic, program, and other filters of your choosing. Once saved, these queries will retrieve new stories every hour, or any time interval you set.

In the WordPress Dashboard go to **Settings > NPR CDS > Get Multi Settings**. This screen offers several **Query** fields where you can enter query parameters for the NPR CDS.

![NPR CDS plugin settings for getting multiple stories](assets/img/settings-multi-get.png.webp)

The NPR CDS accepts 2 types of parameters: `filters` and `sorting`. `Filters` determine what kind of content is pulled from the CDS, and `sorting` determines how that content is presented.

With `filters`, the IDs that the Story API used become `collectionIds`, and `requiredAssets` become `profileIds`. So, for example, a Story API query like this:

`https://api.npr.org/query?id=1014,2&requiredAssets=audio&startDate=2022-04-01&endDate=2022-06-05&dateType=story&output=NPRML&numResults=10`

Would become this:
* Filters: `collectionIds=1014,2&profileIds=renderable,story,buildout,publishable,has-audio&publishDateTime=2022-04-01T00:00:00...2022-06-06T00:00:00`
* Sorting: `limit=10&sort=publishDateTime:desc`

**NOTE**: `profileIds` can be used in a query as either an `AND` or an `OR` statement. If you provide a comma-separated list of `profileIds`, that will be regarded as an `OR` statement (e.g. `profileIds=story,buildout,has-audio` queries for articles that contain the `story`, `buildout`, or `has-audio` profiles).

Providing individual `profileIds` for each profile will be regarded as an `AND` statement (e.g. `profileIds=story&profileIds=buildout&profileIds=has-audio` queries for articles that contain all three of those profiles).

Further information on the [NPR CDS Github](https://npr.github.io/content-distribution-service/querying/).

You can enter multiple queries to pull content for different subjects, programs, etc., and set how often the queries will run. Once you save one or more queries, WordPress will continue to run them against the NPR CDS and return fresh stories as WordPress Posts or Drafts.

***NOTE**: If you previously used the NPR Story API plugin, any queries you created will be migrated over upon activating the CDS plugin.*

## How to Create a Query Parameters

***Coming soon***

<!-- You can easily create query strings for API content by visiting the [NPR Story API Query Generator](https://www.npr.org/api/queryGenerator.php). The Query Generator provides a graphical user interface to create queries by topic, blogs, program, series, stations, and other values in the NPR Story API. Note that the **Control** tab of the Query Generator adds filtering by date or date range, search terms, and content type. You can also specify the number of results to return for a given query.

After creating a query in the Query Generator, click the button to **Create API Call**:

![Creating a query in the NPR Story API Query Generator](assets/img/old/npr-api-query-generator.png)

After you click the button to create your API call, you'll find the full URL of the query in the **Generated API Call** window:

![a query URL in the NPR Story API Query Generator](assets/img/old/npr-api-query-url.png)

Now copy the API call string up to the last segment `&apiKey=demo`. Do not include this in copying the query string. _(That segment would only be useful if you were going to run the query in the Query Generator itself, which we're not doing here.)_

Now that you've created and copied a query string, return to your WordPress Dashboard and the **Settings > NPR API Get Multi** page. Paste the string into one of the Query String fields. Use the dropdown menu to set whether stories returned from the API should be saved as Drafts or Posts:

![a query URL entered in the Query String field in WordPress](assets/img/old/npr-api-multiple-get-settings.png)

When you click **Save Changes**, WordPress will begin pulling NPR Story API content. Note that it may take up to an hour before stories begin showing up as Posts or Drafts.

You can add more queries any time. If you run out of Query String fields, just increase the **Number of things to get** setting to add more fields. -->

## Where to Find the Pulled Stories

If in **Settings > NPR CDS > NPR Pull Post Type** you selected a post type for pulled content, you'll find pulled stories in the relevant posts screen for that post type. For example if you selected `npr_story_post` as the NPR Pull Post Type, WordPress will store pulled stories in the NPR Stories screen:

![NPR Stories screen in WordPress showing pulled stories](assets/img/npr-stories.png.webp)

Now you can edit any pulled story much like any other post, including adding tags, categories, and featured images (if supported).

![Editing an NPR story pulled from the API into WordPress](assets/img/edit-api-story.png.webp)

## Updating Pulled Stories

NPR often updates stories and pushes the updates to the NPR CDS. The same is true for other sources of content in the NPR CDS. You can easily update any stories you pulled from the CDS by visiting the **Posts** screen for your NPR Pull Post Type, and using the **Update NPR Story** Bulk Action:

![Bulk Action menu link for Updating NPR Stories](assets/img/bulk-actions-update-npr-story.png.webp)
