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
