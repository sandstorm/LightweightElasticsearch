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

```
prototype(My.Package:Search) < prototype(Neos.Fusion:Component) {
    // for possibilities on how to build the query, see the next section in the documentation
    @context.mainSearchRequest = ${Elasticsearch.createRequest(site).query(Elasticsearch.createNeosFulltextQuery(site).fulltext(request.arguments.q))}
    
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

We suggest that you start with the following template (which we'll explain one by one):

```fusion
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

## Indexing other data

We suggest to set `index_discriminator` to different values for different data sources, to be able to
identify different sources properly.

TODO: example how an indexer might look like.

## License

MIT
