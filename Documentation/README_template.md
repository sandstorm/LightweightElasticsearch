# Sandstorm.LightweightElasticsearch

...this is an attempt for a more lightweight elasticsearch integration for Neos CMS. This is built because I wanted
to try out some different design decisions in parts of the Neos <-> Elasticsearch integration.

This is a wrapper around Flowpack.Elasticsearch.ContentRepositoryAdaptor, which replaces parts of its API.
A huge thanks goes to everybody maintaining Flowpack.Elasticsearch.ContentRepositoryAdaptor and Flowpack.Elasticsearch,
as we build upon this work and profit from it greatly.

<!-- TOC -->

- [Sandstorm.LightweightElasticsearch](#sandstormlightweightelasticsearch)
    - [Goals and Limitations](#goals-and-limitations)
    - [Starting Elasticsearch for development](#starting-elasticsearch-for-development)
    - [Indexing](#indexing)
        - [Configurations per property index field](#configurations-per-property-index-field)
        - [Exclude NodeTypes from indexing](#exclude-nodetypes-from-indexing)
        - [Indexing configuration per data type](#indexing-configuration-per-data-type)
        - [Indexing configuration per property](#indexing-configuration-per-property)
        - [Skip indexing and mapping of a property](#skip-indexing-and-mapping-of-a-property)
        - [Fulltext Indexing](#fulltext-indexing)
        - [Working with Dates](#working-with-dates)
        - [Working with Assets / Attachments](#working-with-assets--attachments)
    - [Search Component](#search-component)
    - [Query API](#query-api)
    - [Aggregations and Faceting](#aggregations-and-faceting)
    - [Result Highlighting](#result-highlighting)
    - [Indexing other data](#indexing-other-data)
    - [Querying other data](#querying-other-data)
    - [Debugging Elasticsearch queries](#debugging-elasticsearch-queries)
    - [Developing](#developing)
        - [Changing this readme](#changing-this-readme)
    - [License](#license)

<!-- /TOC -->

## Goals and Limitations

The project has the following goals and limitations:

- **Only for fulltext search**

  This means only document nodes or anything which can potentially appear in fulltext search results is put into
  the Elasticsearch index (everything marked in the NodeTypes as `search.fulltext.isRoot = TRUE`).
  That means (by default) no content nodes or ContentCollections are stored inside the index.

- **Easier Fulltext indexing implementation**

  Fulltext collection is done in PHP instead of inside Elasticsearch with Painless.

- **Query Results not specific to Neos**

  You can easily write queries which target anything stored in Elasticsearch; and not just Neos Nodes.
  We provide examples and utilities how other data sources can be indexed in Elasticsearch.

- **More flexible and simple Query API**

  The Query API is aligned to the Elasticsearch API; and it is possible to write arbitrary Elasticsearch Search
  queries. We do not support the `Neos\Flow\Persistence\QueryResultInterface`, and thus no `<f:widget.paginate>`
  to keep things simple.

- **Only supports Batch Indexing**

  We currently only support batch indexing, as this removes many errors in the Neos UI if there are problems
  with the Elasticsearch indexing.

  This is an "artificial limitation" which could be removed; but we do not provide support for this removal
  right now.

- **Only support for a single Elasticsearch version**

  We only support Elasticsearch 7 right now.

- **Only index live the workspace**

  We only index the live workspace, as this is the 99% case to be supported.

- **Faceting using multiple Elasticsearch requests / One Aggregation per Request**

  Building a huge Elasticsearch request for all facets and queries at the same time is possible, but
  hard to debug and understand.

  That's why we keep it simple here; and if you use aggregations, there will be one query per aggregation
  which is done.

## Starting Elasticsearch for development

```bash
docker run --rm --name neos7-es -p 9200:9200 -p 9300:9300 -e "discovery.type=single-node" docker.elastic.co/elasticsearch/elasticsearch:7.10.2
```

## Indexing

> **Tip: Indexing behaves to the user in the same way as defined in [Flowpack.ElasticSearch.ContentRepositoryAdaptor](https://github.com/Flowpack/Flowpack.ElasticSearch.ContentRepositoryAdaptor).
> The only difference is the internal implementation:** Instead of indexing every node (content and document) individually and letting Elasticsearch
> do the merging, we merge the content to the parent document in PHP, as this is easier to handle.
> 
> **The full configuration for indexing is exactly the same as in [Flowpack.ElasticSearch.ContentRepositoryAdaptor](https://github.com/Flowpack/Flowpack.ElasticSearch.ContentRepositoryAdaptor)**.
> It is included below for your convenience.

The following commands are needed for indexing:

```bash
./flow nodeindex:build
./flow nodeindex:cleanup
```

**NOTE:** Only nodes which are marked as `search.fulltext.isRoot` in the corresponding `NodeTypes.yaml`
will become part of the search index, and all their children Content nodes' texts will be indexed as part of this.

**Under the Covers**

- The different indexing strategy is implemented using a custom `DocumentNodeIndexer`, which then calls a custom
  `DocumentIndexerDriver`.

As an example, you can then query the Elasticsearch index using:

```bash
curl -X GET "localhost:9200/neoscr/_search?pretty" -H 'Content-Type: application/json' -d'
{
    "query": {
        "match_all": {}
    }
}
'
```

### Configurations per property (index field)

You can change the analyzers on a per-field level; or e.g. reconfigure the _all field with the following snippet
in the NodeTypes.yaml. Generally this works by defining the global mapping at `[nodeType].search.elasticSearchMapping`:

```yaml
'Neos.Neos:Node':
  search:
    elasticSearchMapping:
      myProperty:
        analyzer: custom_french_analyzer
```

### Exclude NodeTypes from indexing

By default the indexing processes all NodeTypes, but you can change this in your *Settings.yaml*:

```yaml
Neos:
  ContentRepository:
    Search:
      defaultConfigurationPerNodeType:
        '*':
          indexed: true
        'Neos.Neos:FallbackNode':
          indexed: false
        'Neos.Neos:Shortcut':
          indexed: false
        'Neos.Neos:ContentCollection':
          indexed: false
```

You need to explicitly configure the individual NodeTypes (this feature does not check the Super Type configuration).
But you  can use a special notation to configure a full namespace, `Acme.AcmeCom:*` will be applied for all node
types in the `Acme.AcmeCom` namespace. The most specific configuration is used in this order:

- NodeType name (`Neos.Neos:Shortcut`)
- Full namespace notation (`Neos.Neos:*`)
- Catch all (`*`)

### Indexing configuration per data type

**The default configuration supports most use cases and often may not need to be touched, as this package comes
with sane defaults for all Neos data types.**

Indexing of properties is configured at two places. The defaults per-data-type are configured
inside `Neos.ContentRepository.Search.defaultConfigurationPerType` of `Settings.yaml`.
Furthermore, this can be overridden using the `properties.[....].search` path inside
`NodeTypes.yaml`.

This configuration contains two parts:

* Underneath `elasticSearchMapping`, the Elasticsearch property mapping can be defined.
* Underneath `indexing`, an Eel expression which processes the value before indexing has to be
  specified. It has access to the current `value` and the current `node`.

Example (from the default configuration):

```yaml
 # Settings.yaml
Neos:
  ContentRepository:
    Search:
      defaultConfigurationPerType:

        # strings should just be indexed with their simple value.
        string:
          elasticSearchMapping:
            type: string
          indexing: '${value}'
```

### Indexing configuration per property

```yaml
 # NodeTypes.yaml
'Neos.Neos:Timable':
  properties:
    '_hiddenBeforeDateTime':
      search:

        # A date should be mapped differently, and in this case we want to use a date format which
        # Elasticsearch understands
        elasticSearchMapping:
          type: DateTime
          format: 'date_time_no_millis'
        indexing: '${(node.hiddenBeforeDateTime ? Date.format(node.hiddenBeforeDateTime, "Y-m-d\TH:i:sP") : null)}'
```

If your nodetypes schema defines custom properties of type DateTime, you have got to provide similar configuration for
them as well in your `NodeTypes.yaml`, or else they will not be indexed correctly.

There are a few indexing helpers inside the `Indexing` namespace which are usable inside the
`indexing` expression. In most cases, you don't need to touch this, but they were needed to build up
the standard indexing configuration:

* `Indexing.buildAllPathPrefixes`: for a path such as `foo/bar/baz`, builds up a list of path
  prefixes, e.g. `['foo', 'foo/bar', 'foo/bar/baz']`.
* `Indexing.extractNodeTypeNamesAndSupertypes(NodeType)`: extracts a list of node type names for
  the passed node type and all of its supertypes
* `Indexing.convertArrayOfNodesToArrayOfNodeIdentifiers(array $nodes)`: convert the given nodes to
  their node identifiers.

### Skip indexing and mapping of a property

If you don't want a property to be indexed, set `indexing: false`. In this case no mapping is configured for this field.
This can be used to also solve a type conflict of two node properties with same name and different type.

### Fulltext Indexing

In order to enable fulltext indexing, every `Document` node must be configured as *fulltext root*. Thus,
the following is configured in the default configuration:

```yaml
'Neos.Neos:Document':
  search:
    fulltext:
      isRoot: true
```

A *fulltext root* contains all the *content* of its non-document children, such that when one searches
inside these texts, the document itself is returned as result.

In order to specify how the fulltext of a property in a node should be extracted, this is configured
in `NodeTypes.yaml` at `properties.[propertyName].search.fulltextExtractor`.

An example:

```yaml
'Neos.Neos.NodeTypes:Text':
  properties:
    'text':
      search:
        fulltextExtractor: '${Indexing.extractHtmlTags(value)}'

'My.Blog:Post':
  properties:
    title:
      search:
        fulltextExtractor: '${Indexing.extractInto("h1", value)}'
```


### Working with Dates

As a default, Elasticsearch indexes dates in the UTC Timezone. In order to have it index using the timezone
currently configured in PHP, the configuration for any property in a node which represents a date should look like this:

```yaml
'My.Blog:Post':
  properties:
    date:
      search:
        elasticSearchMapping:
          type: 'date'
          format: 'date_time_no_millis'
        indexing: '${(value ? Date.format(value, "Y-m-d\TH:i:sP") : null)}'
```

This is important so that Date- and Time-based searches work as expected, both when using formatted DateTime strings and
when using relative DateTime calculations (eg.: `now`, `now+1d`).

If you want to filter items by date, e.g. to show items with date later than today, you can create a query like this:

```
${...greaterThan('date', Date.format(Date.Now(), "Y-m-d\TH:i:sP"))...}
```

For more information on Elasticsearch's Date Formats,
[click here](http://www.elastic.co/guide/en/elasticsearch/reference/current/mapping-date-format.html).


### Working with Assets / Attachments

If you want to index attachments, you need to install the [Elasticsearch Ingest-Attachment Plugin](https://www.elastic.co/guide/en/elasticsearch/plugins/master/ingest-attachment.html).
Then, you can add the following to your `Settings.yaml`:

```yaml
Neos:
  ContentRepository:
    Search:
      defaultConfigurationPerType:
        'Neos\Media\Domain\Model\Asset':
          elasticSearchMapping:
            type: text
          indexing: ${Indexing.Indexing.extractAssetContent(value)}
```

or add the attachments content to a fulletxt field in your NodeType configuration:

```yaml
  properties:
    file:
      type: 'Neos\Media\Domain\Model\Asset'
      ui:
      search:
        fulltextExtractor: ${Indexing.extractInto('text', Indexing.extractAssetContent(value))}
```

By default `Indexing.extractAssetContent(value)` returns the asset content. You can use the second parameter to return asset meta data. The field parameter can be set to one of the following: `content, title, name, author, keywords, date, content_type, content_length, language`.

With that, you can for example add the keywords of a file to a higher boosted field:

```yaml
  properties:
    file:
      type: 'Neos\Media\Domain\Model\Asset'
      ui:
      search:
        fulltextExtractor: ${Indexing.extractInto('h2', Indexing.extractAssetContent(value, 'keywords'))}
```


## Search Component

As the search component usually needs to be heavily adjusted, we only include a snippet which can be copy/pasted
and adjusted into your project:

```neosfusion
###01_BasicSearchTemplate.fusion###
```

## Query API

Simple Example as Eel expression:

```
    Elasticsearch.createRequest(site)
    .query(
        Elasticsearch.createNeosFulltextQuery(site)
        .fulltext(request.arguments.q)
    )
```

- If you want to search Neos nodes, we need to pass in a *context node* as first argument to `Elasticsearch.createRequest()`
    - This way, the correct index (in the current language) is automatically searched
    - You are able to call `searchResultDocument.loadNode()` on  the individual document
- `Elasticsearch.createNeosFulltextQuery` *also needs a context node*, which specifies the part of the node tree which
  we want to search.

There exists a query API for more complex cases, i.e. you can do the following:

```
    Elasticsearch.createRequest(site)
    .query(
        Elasticsearch.createNeosFulltextQuery(site)
        .fulltext(request.arguments.q)
        // only the results to documents where myKey = myValue
        .filter(Elasticsearch.createTermQuery("myKey", "myValue"))
    )
```

More complex queries for searching through multiple indices can look like this:

```
    Elasticsearch.createRequest(site, ['index2', 'index3')
    .query(
        Elasticsearch.createBooleanQuery()
            .should(
                Elasticsearch.createNeosFulltextQuery(site)
                .fulltext(request.arguments.q)
                .filter(Elasticsearch.createTermQuery("index_discriminator", "neos_nodes"))
            )
            .should(
                // add query for index2 here
            )
    )
```

**We recommend to build more complex queries through Custom Eel helpers; directly calling the Query Builders of this package**

## Aggregations and Faceting

Implementing Faceted Search is more difficult than it looks at first sight - so let's first build a mental model
of how the queries need to work.

Faceting usually looks like:

```
[ Global Search Input Field ] <-- global info

Categories
- News
- FAQ Entries
- ...

Products
- Product 1
- Product 2 (chosen)

[Result Listing]
```

The global search input field is the easiest - as it influences both the facets (Categories and Products) above,
and the Result Listing.

For a facet, things are a bit more difficult. To *calculate* the facet values (i.e. what is shown underneath the "Categories"
headline), an *aggregation query* needs to be executed. In this query, we need to take into account the Global Search field,
and the choices of all other facets (but not our own one).

For the result listing, we need to take into account the global search, and the choices of all facets.

To model that in Elasticsearch, we recommend to use multiple queries: One for each facet; and one for rendering
the result listing.

Here follows the list of modifications done to the template above:

```diff
###02_FacetedSearchTemplate.fusion.diff###
```

You can also copy/paste the full file:

<details>
<summary>See the faceted search example</summary>

```
###02_FacetedSearchTemplate.fusion###
```

</details>

## Result Highlighting

Result highlighting is implemented using the [highlight API](https://www.elastic.co/guide/en/elasticsearch/reference/current/highlighting.html)
of Elasticsearch.

To enable it, you need to change thef following parts:

- To use a default highlighting, add the `.highlight(Elasticsearch.createNeosFulltextHighlight())`
  part to your main Elasticsearch query.
- Additionally, you can call the getter `searchResultDocument.processedHighlights` for each
  result, which contains the highlighted extracts, which you can simply join together like this:

  `Array.join(searchResultDocument.processedHighlights, '…')`

A full example can be found below:

```diff
###02a_FacetedHighlightedSearchTemplate.fusion.diff###
```

You can also copy/paste the full file:

<details>
<summary>See the faceted + highlighted search example</summary>

```
###02a_FacetedHighlightedSearchTemplate.fusion###
```

</details>


## Indexing other data

We suggest to set `index_discriminator` to different values for different data sources, to be able to
identify different sources properly.

You can use the `CustomIndexer` as a basis for indexing as follows:

```php
$indexer = CustomIndexer::create('faq');
$indexer->createIndexWithMapping(['properties' => [
    'faqEntryTitle' => [
        'type' => 'text'
    ]
]]);
$indexer->index([
    'faqEntryTitle' => 'FAQ Dresden'
]);
// index other documents here
$indexer->finalizeAndSwitchAlias();

// Optionally for cleanup
$indexer->removeObsoleteIndices();
```

For your convenience, a full CommandController can be copied/pasted below:

<details>
<summary>Command Controller for custom indexing</summary>

```
###03_CommandController.php###
```

</details>

See the next section for querying other data sources

## Querying other data

Three parts need to be adjusted for querying other data sources:

- adjust `Elasticsearch.createRequest()` and `Elasticsearch.createAggregationRequest()` calls
- build up and use the fulltext query for your custom data
- customize result rendering.

We'll now go through these steps one by one.

**Adjust `Elasticsearch.createRequest()` and `Elasticsearch.createAggregationRequest()`**

Here, you need to include the other index as second parameter; so for example `Elasticsearch.createRequest(site, ['faq'])`
is a valid invocation.


**Build up the fulltext query**

We suggest that you build custom Eel helpers for doing the fulltext search in your custom data,
e.g. by filtering on `index_discriminator` and using a `simple_query_string` query as follows:

```php
return BooleanQueryBuilder::create()
    ->filter(TermQueryBuilder::create('index_discriminator', 'faq'))
    ->must(
        SimpleQueryStringBuilder::create($query ?? '')->fields([
            'faqEntryTitle^5',
        ])
    );
```

As an example, you can also check out the full Eel helper:

<details>
<summary>Eel helper for fulltext querying custom data</summary>

```
###03_FulltextEelHelper.php###
```

</details>

**Remember to register the Eel helper in `Settings.yaml` as usual:

```yaml
Neos:
  Fusion:
    defaultContext:
      MyQueries: My\Package\Eel\MyQueries
```


**Use the fulltext query**

To use both the Neos and your custom fulltext query, these two queries should be combined using a `should` clause
in the `Terms` query; so this is like an "or" query combination:

```
Elasticsearch.createBooleanQuery()
    .should(Elasticsearch.createNeosFulltextQuery(site).fulltext(request.arguments.q))
    .should(MyQueries.faqQuery(request.arguments.q))}
```


**Adjust Result Rendering**

By adding a conditional branch to `prototype(Sandstorm.LightweightElasticsearch:SearchResultCase)`, you can have a custom
result rendering:

```neosfusion
prototype(Sandstorm.LightweightElasticsearch:SearchResultCase) {
    faqEntries {
        condition = ${searchResultDocument.property('index_discriminator') == 'faq'}
        renderer = afx`
            {searchResultDocument.properties.faqEntryTitle}
        `
    }
}
```


**Putting it all together**

See the following diff, or the full source code below:


```diff
###03_ExternalDataTemplate.fusion.diff###
```

You can also copy/paste the full file:

<details>
<summary>See the faceted search example</summary>

```
###03_ExternalDataTemplate.fusion###
```

</details>


## Debugging Elasticsearch queries

1. add `.log("!!!FOO")` to your Eel ElasticSearch query
2. Check the System_Development log file for the full query and save it to a file (req.json)
3. We suggest to use https://httpie.io/ (which can be installed using `brew install httpie`) to
   do a request:

   ```
   http 127.0.0.1:9200/_cat/aliases
   cat req.json | http 127.0.0.1:9200/neoscr,foo/_search
   ```

## Developing

We gladly accept pull requests or contributors :-)

### Changing this readme

First change Documentation/README_template.php; then run `php Documentation/build_readme.php`.

## License

MIT
