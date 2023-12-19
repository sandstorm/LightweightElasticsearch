# Sandstorm.LightweightElasticsearch

|                       | LightweightElasticsearch 1.x                                                                           | LightweightElasticsearch 2.x    |
|-----------------------|--------------------------------------------------------------------------------------------------------|---------------------------------|
| Compatibility         | Neos 7, Neos 8                                                                                         | Neos 9 (new Content Repository) |
| Architecture          | Hack, based on Flowpack.Elasticsearch.ContentrepositoryAdaptor                                         | Clean Room Rewrite              |
| External Dependencies | Flowpack.Elasticsearch.ContentrepositoryAdaptor, Flowpack.Elasticsearch, Neos.ContentRepository.Search | *no external dependencies*      |

So you see LightweightElasticsearch 2.x is a major rewrite compared to 1.x; with a clean architecture
which hopefully will serve the community for a long time.

<!-- TOC -->
* [Sandstorm.LightweightElasticsearch](#sandstormlightweightelasticsearch)
  * [Goals and Limitations](#goals-and-limitations)
  * [Starting Elasticsearch for development](#starting-elasticsearch-for-development)
  * [Indexing](#indexing)
    * [Configurations per property (index field)](#configurations-per-property-index-field)
    * [Exclude NodeTypes from indexing](#exclude-nodetypes-from-indexing)
    * [Indexing configuration per data type](#indexing-configuration-per-data-type)
    * [Indexing configuration per property](#indexing-configuration-per-property)
    * [Skip indexing and mapping of a property](#skip-indexing-and-mapping-of-a-property)
    * [Fulltext Indexing](#fulltext-indexing)
    * [Working with Dates](#working-with-dates)
    * [Working with Assets / Attachments](#working-with-assets--attachments)
  * [Querying](#querying)
    * [Search Component](#search-component)
    * [Fusion Query API Basics](#fusion-query-api-basics)
    * [Aggregations and Faceting](#aggregations-and-faceting)
    * [Result Highlighting](#result-highlighting)
  * [Indexing other data](#indexing-other-data)
  * [Querying other data](#querying-other-data)
  * [Debugging Elasticsearch queries](#debugging-elasticsearch-queries)
  * [Developing](#developing)
    * [Changing this readme](#changing-this-readme)
  * [License](#license)
<!-- TOC -->

## Goals and Limitations

The project has the following goals and limitations:

- **Clean Architecture** (starting with LightweightElasticsearch 2.x): The old conglomerate of Flowpack.Elasticsearch, 
  Flowpack.Elasticsearch.ContentRepositoryAdaptor, Neos.ContentRepository.Search, and Sandstorm.LightweightElasticsearch
  was way too hard to understand and too hard to follow. With 2.0, we started from scratch with a completely re-done 
  architecture.

- **Only for fulltext search**

  This means only document nodes or anything which can potentially appear in fulltext search results is put into
  the Elasticsearch index (everything marked in the NodeTypes as `search.fulltext.isRoot = TRUE`).
  That means (by default) no content nodes or ContentCollections are stored inside the index.

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

- **Only support for new Elasticsearch / Opensearch versions**

  We only support Elasticsearch 7 and newer, and Opensearch starting from 2.9.0.

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

>
> **Tip: Indexing behaves to the user in **almost** the same way as defined in [Flowpack.ElasticSearch.ContentRepositoryAdaptor](https://github.com/Flowpack/Flowpack.ElasticSearch.ContentRepositoryAdaptor).
> 
> **The full configuration for indexing is **almost** the same as in [Flowpack.ElasticSearch.ContentRepositoryAdaptor](https://github.com/Flowpack/Flowpack.ElasticSearch.ContentRepositoryAdaptor)**,
> but all Settings have been centralized to Sandstorm.LightweightElasticsearch.

The following commands are needed for indexing:

```bash
./flow nodeindex:build
./flow nodeindex:cleanup
```

**NOTE:** Only nodes which are marked as `search.fulltext.isRoot` in the corresponding `NodeTypes.yaml`
will become part of the search index, and all their children Content nodes' texts will be indexed as part of this.

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

> NOTE: In Flowpack.Elasticsearch.ContentRepositoryAdaptor and Sandstorm.LightweightElasticsearch 1.x, this
> was done through `Settings.yaml` via `Neos.ContentRepository.Search.defaultConfigurationPerNodeType`.
> 
> In LightweightElasticsearch 2.x, this has changed to `NodeTypes.yaml` to key `search.isIndexed`.

By default the indexing processes all NodeTypes, but you can change this in your *NodeTypes.yaml*:

```yaml
'Your.NodeType':
  search:
    isIndexed: false
```

As usual, super type configuration is correctly taken into account.

### Indexing configuration per data type

> NOTE: In Flowpack.Elasticsearch.ContentRepositoryAdaptor and Sandstorm.LightweightElasticsearch 1.x, this
> was done through `Settings.yaml` via `Neos.ContentRepository.Search.defaultConfigurationPerType`.
>
> In LightweightElasticsearch 2.x, this has changed to key `Sandstorm.LightweightElasticsearch.defaultConfigurationPerType`.

**The default configuration supports most use cases and often may not need to be touched, as this package comes
with sane defaults for all Neos data types.**

Indexing of properties is configured at two places. The defaults per-data-type are configured
inside `Sandstorm.LightweightElasticsearch.defaultConfigurationPerType` of `Settings.yaml`.
Furthermore, this can be overridden using the `properties.[....].search` path inside
`NodeTypes.yaml`.

This configuration contains the following parts:

* Underneath `elasticSearchMapping`, the Elasticsearch property mapping can be defined.
* Underneath `indexing`, an Eel expression which processes the value before indexing has to be
  specified. It has access to the current `value` and the current `node`.
* Underneath `fulltextExtractor`, specify an Eel expression which extracts the values into different fulltext buckets.
  It has access to the current `value` and the current `node`.

Example (from the default configuration):

```yaml
 # Settings.yaml
Sandstorm:
  LightweightElasticsearch:
    defaultConfigurationPerType:

      # strings should just be indexed with their simple value.
      string:
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

* `Indexing.extractNodeTypeNamesAndSupertypes(NodeType)`: extracts a list of node type names for
  the passed node type and all of its supertypes
* `Indexing.convertArrayOfNodesToArrayOfNodeIdentifiers(array $nodes)`: convert the given nodes to
  their node identifiers.

### Skip indexing and mapping of a property

If you don't want a property to be indexed, set `search.indexing: false`. In this case no mapping is configured for this field.
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

If you want to index attachments, you need to install the [Elasticsearch Ingest-Attachment Plugin](https://www.elastic.co/guide/en/elasticsearch/plugins/master/ingest-attachment.html)
or [Opensearch Ingest-Attachment](https://forum.opensearch.org/t/ingest-attachment-cannot-be-installed/6494/11).

Then, you can add the following to your `Settings.yaml`:

```yaml
Sandstorm:
  LightweightElasticsearch:
    defaultConfigurationPerType:
      'Neos\Media\Domain\Model\Asset':
        elasticSearchMapping:
          type: text
        indexing: ${Indexing.Indexing.extractAssetContent(value)}
```

or add the attachments content to a fulltext field in your NodeType configuration:

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


## Querying

### Search Component

As the search component usually needs to be heavily adjusted, we only include a snippet which can be copy/pasted
and adjusted into your project:

```neosfusion
prototype(My.Package:Search) < prototype(Neos.Fusion:Component) {
    // for possibilities on how to build the query, see the next section in the documentation
    _elasticsearchBaseQuery = ${Elasticsearch.createNeosFulltextQuery(site).fulltext(request.arguments.q)}
    @context.mainSearchRequest = ${Elasticsearch.createRequest(site).query(this._elasticsearchBaseQuery)}

    // Search Result Display is controlled through Flowpack.Listable
    searchResults = Flowpack.Listable:PaginatedCollection {
        collection = ${mainSearchRequest}
        itemsPerPage = 12

        // we use cache mode "dynamic" for the full Search component; so we do not need an additional cache entry
        // for the PaginatedCollection.
        @cache.mode = "embed"
    }
    renderer = afx`
        <form action="." method="get">
            <input name="q" value={request.arguments.q}/>
            <button type="submit">Search</button>

            <div @if.isError={mainSearchRequest.execute().error}>
                There was an error executing the search request. Please try again in a few minutes.
            </div>
            <p>Showing {mainSearchRequest.execute().count()} of {mainSearchRequest.execute().total()} results</p>

            {props.searchResults}
        </form>
    `
    // If you want to see the full request going to Elasticsearch, you can include
    // the following snippet in the renderer above:
    // <Neos.Fusion:Debug v={Json.stringify(mainSearchRequest.requestForDebugging())} />

    // The parameter "q" should be included in this pagination
    prototype(Flowpack.Listable:PaginationParameters) {
        q = ${request.arguments.q}
    }

    // We configure the cache mode "dynamic" here.
    @cache {
        mode = 'dynamic'
        entryIdentifier {
            node = ${Neos.Caching.entryIdentifierForNode(node)}
            type = 'searchForm'
        }
        entryDiscriminator = ${request.arguments.q + '-' + request.arguments.currentPage}
        context {
            1 = 'node'
            2 = 'documentNode'
            3 = 'site'
        }
        entryTags {
            1 = ${Neos.Caching.nodeTag(node)}
        }
    }
}

// The result display is done here.
// In the context, you'll find an object `searchResultDocument` which is of type
// Sandstorm\LightweightElasticsearch\Query\Result\SearchResultDocument.
prototype(Sandstorm.LightweightElasticsearch:SearchResultCase) {
    neosNodes {
        // all Documents in the index which are Nodes have a property "index_discriminator" set to "neos_nodes";
        // This is in preparation for displaying other kinds of data.
        condition = ${searchResultDocument.property('index_discriminator') == 'neos_nodes'}
        renderer.@context.node = ${searchResultDocument.loadNode()}
        renderer = afx`
            <Neos.Neos:NodeLink node={node} />
        `
        // If you want to see the full Search Response hit, you can include the following
        // snippet in the renderer above:
        // <Neos.Fusion:Debug result={searchResultDocument.fullSearchHit} />
    }
}

```

### Fusion Query API Basics

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

### Aggregations and Faceting

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
@@ -1,8 +1,25 @@
 prototype(My.Package:Search) < prototype(Neos.Fusion:Component) {
-    // for possibilities on how to build the query, see the next section in the documentation
+    // this is the base query from the user which should *always* be applied.
     _elasticsearchBaseQuery = ${Elasticsearch.createNeosFulltextQuery(site).fulltext(request.arguments.q)}
-    @context.mainSearchRequest = ${Elasticsearch.createRequest(site).query(this._elasticsearchBaseQuery)}
 
+    // register a Terms aggregation with the URL parameter "nodeTypesFilter"
+    _nodeTypesAggregation = ${Elasticsearch.createTermsAggregation("neos_type", request.arguments.nodeTypesFilter)}
+
+    // This is the main elasticsearch query which determines the search results:
+    // - this._elasticsearchBaseQuery is applied
+    // - this._nodeTypesAggregation is applied as well (if the user chose a facet value)
+    // <-- if you add additional aggregations, you need to add them here to this list.
+    @context.mainSearchRequest = ${Elasticsearch.createRequest(site).query(Elasticsearch.createBooleanQuery().must(this._elasticsearchBaseQuery).filter(this._nodeTypesAggregation))}
+
+    // The Request is for displaying the Node Types aggregation (faceted search).
+    //
+    // For faceted search to work properly, we need to add all OTHER query parts as filter; so NOT ourselves.
+    // This means, for the `.aggregation()` part, we take the aggregation itself.
+    // For the `.filter()` part, we add:
+    // - this._elasticsearchBaseQuery to ensure the entered query string by the user is taken into account
+    // <-- if you add additional aggregations, you need to add them here to the list.
+    @context.nodeTypesFacet = ${Elasticsearch.createAggregationRequest(site).aggregation(this._nodeTypesAggregation).filter(this._elasticsearchBaseQuery).execute()}
+
     // Search Result Display is controlled through Flowpack.Listable
     searchResults = Flowpack.Listable:PaginatedCollection {
         collection = ${mainSearchRequest}
@@ -12,6 +29,23 @@
         // for the PaginatedCollection.
         @cache.mode = "embed"
     }
+
+    nodeTypesFacet = Neos.Fusion:Component {
+        // the nodeTypesFacet is a "Terms" aggregation...
+        // ...so we can access nodeTypesFacet.buckets.
+        // To build a link to the facet, we use Neos.Neos:NodeLink with two additions:
+        // - addQueryString must be set to TRUE, to keep the search query and potentially other facets.
+        // - to build the arguments, we need to set `nodeTypesFilter` to the current bucket key (or to null in case we want to clear the facet)
+        renderer = afx`
+            <ul>
+                <Neos.Fusion:Loop items={nodeTypesFacet.buckets} itemName="bucket">
+                    <li><Neos.Neos:NodeLink node={documentNode} addQueryString={true} arguments={{nodeTypesFilter: bucket.key}}>{bucket.key}</Neos.Neos:NodeLink> {bucket.doc_count} <span @if.isTrue={bucket.key == nodeTypesFacet.selectedValue}>(selected)</span></li>
+                </Neos.Fusion:Loop>
+            </ul>
+            <Neos.Neos:NodeLink @if.isTrue={nodeTypesFacet.selectedValue} node={documentNode} addQueryString={true} arguments={{nodeTypesFilter: null}}>CLEAR FACET</Neos.Neos:NodeLink>
+        `
+    }
+
     renderer = afx`
         <form action="." method="get">
             <input name="q" value={request.arguments.q}/>
@@ -22,6 +56,8 @@
             </div>
             <p>Showing {mainSearchRequest.execute().count()} of {mainSearchRequest.execute().total()} results</p>
 
+            {props.nodeTypesFacet}
+
             {props.searchResults}
         </form>
     `
@@ -32,6 +68,8 @@
     // The parameter "q" should be included in this pagination
     prototype(Flowpack.Listable:PaginationParameters) {
         q = ${request.arguments.q}
+        // <-- if you add additional aggregations, you need to add the parameter names here
+        nodeTypesFilter = ${request.arguments.nodeTypesFilter}
     }
 
     // We configure the cache mode "dynamic" here.
@@ -41,7 +79,8 @@
             node = ${Neos.Caching.entryIdentifierForNode(node)}
             type = 'searchForm'
         }
-        entryDiscriminator = ${request.arguments.q + '-' + request.arguments.currentPage}
+        // <-- if you add additional aggregations, you need to add the parameter names to the entryDiscriminator
+        entryDiscriminator = ${request.arguments.q + '-' + request.arguments.currentPage + '-' + request.arguments.nodeTypesFilter}
         context {
             1 = 'node'
             2 = 'documentNode'

```

You can also copy/paste the full file:

<details>
<summary>See the faceted search example</summary>

```
prototype(My.Package:Search) < prototype(Neos.Fusion:Component) {
    // this is the base query from the user which should *always* be applied.
    _elasticsearchBaseQuery = ${Elasticsearch.createNeosFulltextQuery(site).fulltext(request.arguments.q)}

    // register a Terms aggregation with the URL parameter "nodeTypesFilter"
    _nodeTypesAggregation = ${Elasticsearch.createTermsAggregation("neos_type", request.arguments.nodeTypesFilter)}

    // This is the main elasticsearch query which determines the search results:
    // - this._elasticsearchBaseQuery is applied
    // - this._nodeTypesAggregation is applied as well (if the user chose a facet value)
    // <-- if you add additional aggregations, you need to add them here to this list.
    @context.mainSearchRequest = ${Elasticsearch.createRequest(site).query(Elasticsearch.createBooleanQuery().must(this._elasticsearchBaseQuery).filter(this._nodeTypesAggregation))}

    // The Request is for displaying the Node Types aggregation (faceted search).
    //
    // For faceted search to work properly, we need to add all OTHER query parts as filter; so NOT ourselves.
    // This means, for the `.aggregation()` part, we take the aggregation itself.
    // For the `.filter()` part, we add:
    // - this._elasticsearchBaseQuery to ensure the entered query string by the user is taken into account
    // <-- if you add additional aggregations, you need to add them here to the list.
    @context.nodeTypesFacet = ${Elasticsearch.createAggregationRequest(site).aggregation(this._nodeTypesAggregation).filter(this._elasticsearchBaseQuery).execute()}

    // Search Result Display is controlled through Flowpack.Listable
    searchResults = Flowpack.Listable:PaginatedCollection {
        collection = ${mainSearchRequest}
        itemsPerPage = 12

        // we use cache mode "dynamic" for the full Search component; so we do not need an additional cache entry
        // for the PaginatedCollection.
        @cache.mode = "embed"
    }

    nodeTypesFacet = Neos.Fusion:Component {
        // the nodeTypesFacet is a "Terms" aggregation...
        // ...so we can access nodeTypesFacet.buckets.
        // To build a link to the facet, we use Neos.Neos:NodeLink with two additions:
        // - addQueryString must be set to TRUE, to keep the search query and potentially other facets.
        // - to build the arguments, we need to set `nodeTypesFilter` to the current bucket key (or to null in case we want to clear the facet)
        renderer = afx`
            <ul>
                <Neos.Fusion:Loop items={nodeTypesFacet.buckets} itemName="bucket">
                    <li><Neos.Neos:NodeLink node={documentNode} addQueryString={true} arguments={{nodeTypesFilter: bucket.key}}>{bucket.key}</Neos.Neos:NodeLink> {bucket.doc_count} <span @if.isTrue={bucket.key == nodeTypesFacet.selectedValue}>(selected)</span></li>
                </Neos.Fusion:Loop>
            </ul>
            <Neos.Neos:NodeLink @if.isTrue={nodeTypesFacet.selectedValue} node={documentNode} addQueryString={true} arguments={{nodeTypesFilter: null}}>CLEAR FACET</Neos.Neos:NodeLink>
        `
    }

    renderer = afx`
        <form action="." method="get">
            <input name="q" value={request.arguments.q}/>
            <button type="submit">Search</button>

            <div @if.isError={mainSearchRequest.execute().error}>
                There was an error executing the search request. Please try again in a few minutes.
            </div>
            <p>Showing {mainSearchRequest.execute().count()} of {mainSearchRequest.execute().total()} results</p>

            {props.nodeTypesFacet}

            {props.searchResults}
        </form>
    `
    // If you want to see the full request going to Elasticsearch, you can include
    // the following snippet in the renderer above:
    // <Neos.Fusion:Debug v={Json.stringify(mainSearchRequest.requestForDebugging())} />

    // The parameter "q" should be included in this pagination
    prototype(Flowpack.Listable:PaginationParameters) {
        q = ${request.arguments.q}
        // <-- if you add additional aggregations, you need to add the parameter names here
        nodeTypesFilter = ${request.arguments.nodeTypesFilter}
    }

    // We configure the cache mode "dynamic" here.
    @cache {
        mode = 'dynamic'
        entryIdentifier {
            node = ${Neos.Caching.entryIdentifierForNode(node)}
            type = 'searchForm'
        }
        // <-- if you add additional aggregations, you need to add the parameter names to the entryDiscriminator
        entryDiscriminator = ${request.arguments.q + '-' + request.arguments.currentPage + '-' + request.arguments.nodeTypesFilter}
        context {
            1 = 'node'
            2 = 'documentNode'
            3 = 'site'
        }
        entryTags {
            1 = ${Neos.Caching.nodeTag(node)}
        }
    }
}

// The result display is done here.
// In the context, you'll find an object `searchResultDocument` which is of type
// Sandstorm\LightweightElasticsearch\Query\Result\SearchResultDocument.
prototype(Sandstorm.LightweightElasticsearch:SearchResultCase) {
    neosNodes {
        // all Documents in the index which are Nodes have a property "index_discriminator" set to "neos_nodes";
        // This is in preparation for displaying other kinds of data.
        condition = ${searchResultDocument.property('index_discriminator') == 'neos_nodes'}
        renderer.@context.node = ${searchResultDocument.loadNode()}
        renderer = afx`
            <Neos.Neos:NodeLink node={node} />
        `
        // If you want to see the full Search Response hit, you can include the following
        // snippet in the renderer above:
        // <Neos.Fusion:Debug result={searchResultDocument.fullSearchHit} />
    }
}

```

</details>

### Result Highlighting

Result highlighting is implemented using the [highlight API](https://www.elastic.co/guide/en/elasticsearch/reference/current/highlighting.html)
of Elasticsearch.

To enable it, you need to change the following parts:

- To use a default highlighting, add the `.highlight(Elasticsearch.createNeosFulltextHighlight())`
  part to your main Elasticsearch query.
- Additionally, you can call the getter `searchResultDocument.processedHighlights` for each
  result, which contains the highlighted extracts, which you can simply join together like this:

  `Array.join(searchResultDocument.processedHighlights, '…')`

A full example can be found below:

```diff
@@ -9,7 +9,7 @@
     // - this._elasticsearchBaseQuery is applied
     // - this._nodeTypesAggregation is applied as well (if the user chose a facet value)
     // <-- if you add additional aggregations, you need to add them here to this list.
-    @context.mainSearchRequest = ${Elasticsearch.createRequest(site).query(Elasticsearch.createBooleanQuery().must(this._elasticsearchBaseQuery).filter(this._nodeTypesAggregation))}
+    @context.mainSearchRequest = ${Elasticsearch.createRequest(site).query(Elasticsearch.createBooleanQuery().must(this._elasticsearchBaseQuery).filter(this._nodeTypesAggregation)).highlight(Elasticsearch.createNeosFulltextHighlight())}
 
     // The Request is for displaying the Node Types aggregation (faceted search).
     //
@@ -102,7 +102,9 @@
         condition = ${searchResultDocument.property('index_discriminator') == 'neos_nodes'}
         renderer.@context.node = ${searchResultDocument.loadNode()}
         renderer = afx`
-            <Neos.Neos:NodeLink node={node} />
+            <Neos.Neos:NodeLink node={node}>
+                {Array.join(searchResultDocument.processedHighlights, '…')}
+            </Neos.Neos:NodeLink>
         `
         // If you want to see the full Search Response hit, you can include the following
         // snippet in the renderer above:

```

You can also copy/paste the full file:

<details>
<summary>See the faceted + highlighted search example</summary>

```
prototype(My.Package:Search) < prototype(Neos.Fusion:Component) {
    // this is the base query from the user which should *always* be applied.
    _elasticsearchBaseQuery = ${Elasticsearch.createNeosFulltextQuery(site).fulltext(request.arguments.q)}

    // register a Terms aggregation with the URL parameter "nodeTypesFilter"
    _nodeTypesAggregation = ${Elasticsearch.createTermsAggregation("neos_type", request.arguments.nodeTypesFilter)}

    // This is the main elasticsearch query which determines the search results:
    // - this._elasticsearchBaseQuery is applied
    // - this._nodeTypesAggregation is applied as well (if the user chose a facet value)
    // <-- if you add additional aggregations, you need to add them here to this list.
    @context.mainSearchRequest = ${Elasticsearch.createRequest(site).query(Elasticsearch.createBooleanQuery().must(this._elasticsearchBaseQuery).filter(this._nodeTypesAggregation)).highlight(Elasticsearch.createNeosFulltextHighlight())}

    // The Request is for displaying the Node Types aggregation (faceted search).
    //
    // For faceted search to work properly, we need to add all OTHER query parts as filter; so NOT ourselves.
    // This means, for the `.aggregation()` part, we take the aggregation itself.
    // For the `.filter()` part, we add:
    // - this._elasticsearchBaseQuery to ensure the entered query string by the user is taken into account
    // <-- if you add additional aggregations, you need to add them here to the list.
    @context.nodeTypesFacet = ${Elasticsearch.createAggregationRequest(site).aggregation(this._nodeTypesAggregation).filter(this._elasticsearchBaseQuery).execute()}

    // Search Result Display is controlled through Flowpack.Listable
    searchResults = Flowpack.Listable:PaginatedCollection {
        collection = ${mainSearchRequest}
        itemsPerPage = 12

        // we use cache mode "dynamic" for the full Search component; so we do not need an additional cache entry
        // for the PaginatedCollection.
        @cache.mode = "embed"
    }

    nodeTypesFacet = Neos.Fusion:Component {
        // the nodeTypesFacet is a "Terms" aggregation...
        // ...so we can access nodeTypesFacet.buckets.
        // To build a link to the facet, we use Neos.Neos:NodeLink with two additions:
        // - addQueryString must be set to TRUE, to keep the search query and potentially other facets.
        // - to build the arguments, we need to set `nodeTypesFilter` to the current bucket key (or to null in case we want to clear the facet)
        renderer = afx`
            <ul>
                <Neos.Fusion:Loop items={nodeTypesFacet.buckets} itemName="bucket">
                    <li><Neos.Neos:NodeLink node={documentNode} addQueryString={true} arguments={{nodeTypesFilter: bucket.key}}>{bucket.key}</Neos.Neos:NodeLink> {bucket.doc_count} <span @if.isTrue={bucket.key == nodeTypesFacet.selectedValue}>(selected)</span></li>
                </Neos.Fusion:Loop>
            </ul>
            <Neos.Neos:NodeLink @if.isTrue={nodeTypesFacet.selectedValue} node={documentNode} addQueryString={true} arguments={{nodeTypesFilter: null}}>CLEAR FACET</Neos.Neos:NodeLink>
        `
    }

    renderer = afx`
        <form action="." method="get">
            <input name="q" value={request.arguments.q}/>
            <button type="submit">Search</button>

            <div @if.isError={mainSearchRequest.execute().error}>
                There was an error executing the search request. Please try again in a few minutes.
            </div>
            <p>Showing {mainSearchRequest.execute().count()} of {mainSearchRequest.execute().total()} results</p>

            {props.nodeTypesFacet}

            {props.searchResults}
        </form>
    `
    // If you want to see the full request going to Elasticsearch, you can include
    // the following snippet in the renderer above:
    // <Neos.Fusion:Debug v={Json.stringify(mainSearchRequest.requestForDebugging())} />

    // The parameter "q" should be included in this pagination
    prototype(Flowpack.Listable:PaginationParameters) {
        q = ${request.arguments.q}
        // <-- if you add additional aggregations, you need to add the parameter names here
        nodeTypesFilter = ${request.arguments.nodeTypesFilter}
    }

    // We configure the cache mode "dynamic" here.
    @cache {
        mode = 'dynamic'
        entryIdentifier {
            node = ${Neos.Caching.entryIdentifierForNode(node)}
            type = 'searchForm'
        }
        // <-- if you add additional aggregations, you need to add the parameter names to the entryDiscriminator
        entryDiscriminator = ${request.arguments.q + '-' + request.arguments.currentPage + '-' + request.arguments.nodeTypesFilter}
        context {
            1 = 'node'
            2 = 'documentNode'
            3 = 'site'
        }
        entryTags {
            1 = ${Neos.Caching.nodeTag(node)}
        }
    }
}

// The result display is done here.
// In the context, you'll find an object `searchResultDocument` which is of type
// Sandstorm\LightweightElasticsearch\Query\Result\SearchResultDocument.
prototype(Sandstorm.LightweightElasticsearch:SearchResultCase) {
    neosNodes {
        // all Documents in the index which are Nodes have a property "index_discriminator" set to "neos_nodes";
        // This is in preparation for displaying other kinds of data.
        condition = ${searchResultDocument.property('index_discriminator') == 'neos_nodes'}
        renderer.@context.node = ${searchResultDocument.loadNode()}
        renderer = afx`
            <Neos.Neos:NodeLink node={node}>
                {Array.join(searchResultDocument.processedHighlights, '…')}
            </Neos.Neos:NodeLink>
        `
        // If you want to see the full Search Response hit, you can include the following
        // snippet in the renderer above:
        // <Neos.Fusion:Debug result={searchResultDocument.fullSearchHit} />
    }
}

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
<?php
declare(strict_types=1);

namespace Your\Package\Command;

use Neos\ContentRepository\Core\Factory\ContentRepositoryId;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Cli\CommandController;
use Neos\Flow\Log\Backend\ConsoleBackend;
use Neos\Flow\Log\Psr\Logger;
use Sandstorm\LightweightElasticsearch\Factory\ElasticsearchFactory;
use Sandstorm\LightweightElasticsearch\Indexing\CustomIndexer;
use Sandstorm\LightweightElasticsearch\SharedModel\AliasName;
use Sandstorm\LightweightElasticsearch\SharedModel\IndexDiscriminator;

class CustomIndexCommandController extends CommandController
{

    #[Flow\Inject]
    protected ElasticsearchFactory $elasticsearchFactory;

    public function indexCommand()
    {
        $logBackend = new ConsoleBackend();
        $logBackend->setSeverityThreshold(LOG_DEBUG);
        $logger = new Logger([$logBackend]);

        $elasticsearch = $this->elasticsearchFactory->build(
            ContentRepositoryId::fromString('default'),
            $logger
        );
        $indexer = $elasticsearch->customIndexer(
            AliasName::createForCustomIndex('custom_faq'),
            IndexDiscriminator::createForCustomIndex('custom_faq')
        );
        $indexer->createIndexWithMapping(['properties' => [
            'faqEntryTitle' => [
                'type' => 'text'
            ]
        ]]);
        $indexer->index([
            'faqEntryTitle' => 'FAQ Dresden'
        ]);
        $indexer->index([
            'faqEntryTitle' => 'FAQ Berlin'
        ]);
        $indexer->finalizeAndSwitchAlias();
    }

    public function cleanupCommand()
    {
        $indexer = CustomIndexer::create('faq');
        $removedIndices = $indexer->removeObsoleteIndices();
        foreach ($removedIndices as $index) {
            $this->outputLine('Removed ' . $index);
        }
    }
}

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
<?php
declare(strict_types=1);

namespace My\Package\Eel;

use Neos\Eel\ProtectedContextAwareInterface;
use Sandstorm\LightweightElasticsearch\Query\Query\BooleanQueryBuilder;
use Sandstorm\LightweightElasticsearch\Query\Query\SearchQueryBuilderInterface;
use Sandstorm\LightweightElasticsearch\Query\Query\SimpleQueryStringBuilder;
use Sandstorm\LightweightElasticsearch\Query\Query\TermQueryBuilder;

class MyQueries implements ProtectedContextAwareInterface
{

    public function faqQuery(string $query): SearchQueryBuilderInterface
    {
        return BooleanQueryBuilder::create()
            ->filter(TermQueryBuilder::create('index_discriminator', 'faq'))
            ->must(
                SimpleQueryStringBuilder::create($query)->fields([
                    'faqEntryTitle^5',
                ])
            );
    }

    public function allowsCallOfMethod($methodName): bool
    {
        return true;
    }
}

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
@@ -1,26 +1,15 @@
 prototype(My.Package:Search) < prototype(Neos.Fusion:Component) {
-    // this is the base query from the user which should *always* be applied.
-    _elasticsearchBaseQuery = ${Elasticsearch.createNeosFulltextQuery(site).fulltext(request.arguments.q)}
+    // for possibilities on how to build the query, see the next section in the documentation
+    _elasticsearchBaseQuery = ${Elasticsearch.createBooleanQuery().should(Elasticsearch.createNeosFulltextQuery(site).fulltext(request.arguments.q)).should(MyQueries.faqQuery(request.arguments.q))}
 
-    // register a Terms aggregation with the URL parameter "nodeTypesFilter"
+    // register a Terms aggregation with the URL parameter "nodeTypesFilter".
+    // we also need to pass in the request, so that the aggregation can extract the currently selected value.
     _nodeTypesAggregation = ${Elasticsearch.createTermsAggregation("neos_type", request.arguments.nodeTypesFilter)}
 
-    // This is the main elasticsearch query which determines the search results:
-    // - this._elasticsearchBaseQuery is applied
-    // - this._nodeTypesAggregation is applied as well (if the user chose a facet value)
-    // <-- if you add additional aggregations, you need to add them here to this list.
-    @context.mainSearchRequest = ${Elasticsearch.createRequest(site).query(Elasticsearch.createBooleanQuery().must(this._elasticsearchBaseQuery).filter(this._nodeTypesAggregation))}
-
-    // The Request is for displaying the Node Types aggregation (faceted search).
-    //
-    // For faceted search to work properly, we need to add all OTHER query parts as filter; so NOT ourselves.
-    // This means, for the `.aggregation()` part, we take the aggregation itself.
-    // For the `.filter()` part, we add:
-    // - this._elasticsearchBaseQuery to ensure the entered query string by the user is taken into account
-    // <-- if you add additional aggregations, you need to add them here to the list.
-    @context.nodeTypesFacet = ${Elasticsearch.createAggregationRequest(site).aggregation(this._nodeTypesAggregation).filter(this._elasticsearchBaseQuery).execute()}
-
-    // Search Result Display is controlled through Flowpack.Listable
+    // this is the main elasticsearch query which determines the search results - here, we also apply any restrictions imposed
+    // by the _nodeTypesAggregation
+    @context.mainSearchRequest = ${Elasticsearch.createRequest(site, ['faq']).query(Elasticsearch.createBooleanQuery().must(this._elasticsearchBaseQuery).filter(this._nodeTypesAggregation))}
+    @context.nodeTypesFacet = ${Elasticsearch.createAggregationRequest(site, ['faq']).aggregation(this._nodeTypesAggregation).filter(this._elasticsearchBaseQuery).execute()}
     searchResults = Flowpack.Listable:PaginatedCollection {
         collection = ${mainSearchRequest}
         itemsPerPage = 12
@@ -35,7 +24,7 @@
         // ...so we can access nodeTypesFacet.buckets.
         // To build a link to the facet, we use Neos.Neos:NodeLink with two additions:
         // - addQueryString must be set to TRUE, to keep the search query and potentially other facets.
-        // - to build the arguments, we need to set `nodeTypesFilter` to the current bucket key (or to null in case we want to clear the facet)
+        // - to build the arguments, each aggregation result type (e.g. TermsAggregationResult) has a specific method with the required arguments.
         renderer = afx`
             <ul>
                 <Neos.Fusion:Loop items={nodeTypesFacet.buckets} itemName="bucket">
@@ -50,7 +39,6 @@
         <form action="." method="get">
             <input name="q" value={request.arguments.q}/>
             <button type="submit">Search</button>
-
             <div @if.isError={mainSearchRequest.execute().error}>
                 There was an error executing the search request. Please try again in a few minutes.
             </div>
@@ -68,8 +56,7 @@
     // The parameter "q" should be included in this pagination
     prototype(Flowpack.Listable:PaginationParameters) {
         q = ${request.arguments.q}
-        // <-- if you add additional aggregations, you need to add the parameter names here
-        nodeTypesFilter = ${request.arguments.nodeTypesFilter}
+        nodeTypes = ${request.arguments.nodeTypesFilter}
     }
 
     // We configure the cache mode "dynamic" here.
@@ -79,7 +66,6 @@
             node = ${Neos.Caching.entryIdentifierForNode(node)}
             type = 'searchForm'
         }
-        // <-- if you add additional aggregations, you need to add the parameter names to the entryDiscriminator
         entryDiscriminator = ${request.arguments.q + '-' + request.arguments.currentPage + '-' + request.arguments.nodeTypesFilter}
         context {
             1 = 'node'
@@ -96,6 +82,12 @@
 // In the context, you'll find an object `searchResultDocument` which is of type
 // Sandstorm\LightweightElasticsearch\Query\Result\SearchResultDocument.
 prototype(Sandstorm.LightweightElasticsearch:SearchResultCase) {
+    faqEntries {
+        condition = ${searchResultDocument.property('index_discriminator') == 'faq'}
+        renderer = afx`
+            {searchResultDocument.properties.faqEntryTitle}
+        `
+    }
     neosNodes {
         // all Documents in the index which are Nodes have a property "index_discriminator" set to "neos_nodes";
         // This is in preparation for displaying other kinds of data.

```

You can also copy/paste the full file:

<details>
<summary>See the external query example</summary>

```
prototype(My.Package:Search) < prototype(Neos.Fusion:Component) {
    // for possibilities on how to build the query, see the next section in the documentation
    _elasticsearchBaseQuery = ${Elasticsearch.createBooleanQuery().should(Elasticsearch.createNeosFulltextQuery(site).fulltext(request.arguments.q)).should(MyQueries.faqQuery(request.arguments.q))}

    // register a Terms aggregation with the URL parameter "nodeTypesFilter".
    // we also need to pass in the request, so that the aggregation can extract the currently selected value.
    _nodeTypesAggregation = ${Elasticsearch.createTermsAggregation("neos_type", request.arguments.nodeTypesFilter)}

    // this is the main elasticsearch query which determines the search results - here, we also apply any restrictions imposed
    // by the _nodeTypesAggregation
    @context.mainSearchRequest = ${Elasticsearch.createRequest(site, ['faq']).query(Elasticsearch.createBooleanQuery().must(this._elasticsearchBaseQuery).filter(this._nodeTypesAggregation))}
    @context.nodeTypesFacet = ${Elasticsearch.createAggregationRequest(site, ['faq']).aggregation(this._nodeTypesAggregation).filter(this._elasticsearchBaseQuery).execute()}
    searchResults = Flowpack.Listable:PaginatedCollection {
        collection = ${mainSearchRequest}
        itemsPerPage = 12

        // we use cache mode "dynamic" for the full Search component; so we do not need an additional cache entry
        // for the PaginatedCollection.
        @cache.mode = "embed"
    }

    nodeTypesFacet = Neos.Fusion:Component {
        // the nodeTypesFacet is a "Terms" aggregation...
        // ...so we can access nodeTypesFacet.buckets.
        // To build a link to the facet, we use Neos.Neos:NodeLink with two additions:
        // - addQueryString must be set to TRUE, to keep the search query and potentially other facets.
        // - to build the arguments, each aggregation result type (e.g. TermsAggregationResult) has a specific method with the required arguments.
        renderer = afx`
            <ul>
                <Neos.Fusion:Loop items={nodeTypesFacet.buckets} itemName="bucket">
                    <li><Neos.Neos:NodeLink node={documentNode} addQueryString={true} arguments={{nodeTypesFilter: bucket.key}}>{bucket.key}</Neos.Neos:NodeLink> {bucket.doc_count} <span @if.isTrue={bucket.key == nodeTypesFacet.selectedValue}>(selected)</span></li>
                </Neos.Fusion:Loop>
            </ul>
            <Neos.Neos:NodeLink @if.isTrue={nodeTypesFacet.selectedValue} node={documentNode} addQueryString={true} arguments={{nodeTypesFilter: null}}>CLEAR FACET</Neos.Neos:NodeLink>
        `
    }

    renderer = afx`
        <form action="." method="get">
            <input name="q" value={request.arguments.q}/>
            <button type="submit">Search</button>
            <div @if.isError={mainSearchRequest.execute().error}>
                There was an error executing the search request. Please try again in a few minutes.
            </div>
            <p>Showing {mainSearchRequest.execute().count()} of {mainSearchRequest.execute().total()} results</p>

            {props.nodeTypesFacet}

            {props.searchResults}
        </form>
    `
    // If you want to see the full request going to Elasticsearch, you can include
    // the following snippet in the renderer above:
    // <Neos.Fusion:Debug v={Json.stringify(mainSearchRequest.requestForDebugging())} />

    // The parameter "q" should be included in this pagination
    prototype(Flowpack.Listable:PaginationParameters) {
        q = ${request.arguments.q}
        nodeTypes = ${request.arguments.nodeTypesFilter}
    }

    // We configure the cache mode "dynamic" here.
    @cache {
        mode = 'dynamic'
        entryIdentifier {
            node = ${Neos.Caching.entryIdentifierForNode(node)}
            type = 'searchForm'
        }
        entryDiscriminator = ${request.arguments.q + '-' + request.arguments.currentPage + '-' + request.arguments.nodeTypesFilter}
        context {
            1 = 'node'
            2 = 'documentNode'
            3 = 'site'
        }
        entryTags {
            1 = ${Neos.Caching.nodeTag(node)}
        }
    }
}

// The result display is done here.
// In the context, you'll find an object `searchResultDocument` which is of type
// Sandstorm\LightweightElasticsearch\Query\Result\SearchResultDocument.
prototype(Sandstorm.LightweightElasticsearch:SearchResultCase) {
    faqEntries {
        condition = ${searchResultDocument.property('index_discriminator') == 'faq'}
        renderer = afx`
            {searchResultDocument.properties.faqEntryTitle}
        `
    }
    neosNodes {
        // all Documents in the index which are Nodes have a property "index_discriminator" set to "neos_nodes";
        // This is in preparation for displaying other kinds of data.
        condition = ${searchResultDocument.property('index_discriminator') == 'neos_nodes'}
        renderer.@context.node = ${searchResultDocument.loadNode()}
        renderer = afx`
            <Neos.Neos:NodeLink node={node} />
        `
        // If you want to see the full Search Response hit, you can include the following
        // snippet in the renderer above:
        // <Neos.Fusion:Debug result={searchResultDocument.fullSearchHit} />
    }
}

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
