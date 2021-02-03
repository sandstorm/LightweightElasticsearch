# Sandstorm.LightweightElasticsearch

...an experiment for a more lightweight elasticsearch integration for Neos CMS. This is built because I wanted
to try out some different design decisions in parts of the Neos <-> Elasticsearch integration.

This is a wrapper around Flowpack.Elasticsearch.ContentRepositoryAdaptor, which replaces parts of its API.
A huge thanks goes to everybody maintaining Flowpack.Elasticsearch.ContentRepositoryAdaptor and Flowpack.Elasticsearch,
as we build upon this work and profit from it greatly.

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

## Search Component

As the search component usually needs to be heavily adjusted, we only include a snippet which can be copy/pasted
and adjusted into your project:

```neosfusion
prototype(My.Package:Search) < prototype(Neos.Fusion:Component) {
    // for possibilities on how to build the query, see the next section in the documentation
    _elasticsearchBaseQuery = ${Elasticsearch.createNeosFulltextQuery(site).fulltext(request.arguments.q)}
    @context.mainSearchRequest = ${Elasticsearch.createRequest(site).query(this._elasticsearchBaseQuery))}

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
            node = ${node}
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
@@ -1,7 +1,24 @@
 prototype(My.Package:Search) < prototype(Neos.Fusion:Component) {
-    // for possibilities on how to build the query, see the next section in the documentation
+    // this is the base query from the user which should *always* be applied.
     _elasticsearchBaseQuery = ${Elasticsearch.createNeosFulltextQuery(site).fulltext(request.arguments.q)}
-    @context.mainSearchRequest = ${Elasticsearch.createRequest(site).query(this._elasticsearchBaseQuery))}
+
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
 
     // Search Result Display is controlled through Flowpack.Listable
     searchResults = Flowpack.Listable:PaginatedCollection {
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
             node = ${node}
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
            node = ${node}
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

namespace Your\Package\Command;

use Neos\Flow\Cli\CommandController;
use Sandstorm\LightweightElasticsearch\CustomIndexing\CustomIndexer;

class CustomIndexCommandController extends CommandController
{

    public function indexCommand()
    {
        $indexer = CustomIndexer::create('faq');
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
                SimpleQueryStringBuilder::create($query ?? '')->fields([
                    'faqEntryTitle^5',
                ])
            );
    }

    public function allowsCallOfMethod($methodName)
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
             node = ${node}
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
<summary>See the faceted search example</summary>

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
            node = ${node}
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
