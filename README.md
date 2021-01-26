# Sandstorm.LightweightElasticsearch

...an experiment for a more lightweight elasticsearch integration for Neos CMS. This is built because I wanted
to try out some different design decisions in parts of the Neos <-> Elasticsearch integration.

This is a wrapper around Flowpack.Elasticsearch.ContentRepositoryAdaptor, which replaces its API.

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

## Querying





- only batch indexing
- only document nodes end up in the index

./flow nodeindex:build

https://www.elastic.co/guide/en/elasticsearch/reference/current/filter-search-results.html
Use a boolean query with a filter clause. Search requests apply boolean filters to both search hits and aggregations.

Filter context is in effect whenever a query clause is passed to a filter parameter, such as the filter or must_not
parameters in the bool query, the filter parameter in the constant_score query, or the filter aggregation.
https://www.elastic.co/guide/en/elasticsearch/reference/current/query-filter-context.html

multiple indices

sw_data_source = "neos"

```js
mainQ = {
    "query": {
        "bool": {
            "should": [
                neosQ,
                otherIndexQ
            ]
        }
    }
}

neosQ = {
    "bool": {
        "filter": [
            {
                "term": {
                    "index_discriminator": "neos"
                }
            },
            /*{... further filters, like "in part of the page tree" ...}*/
        ],
        "must": [
            {
                // or "multi_match"
                "simple_query_string": {
                    ...
                }
            }
        ]
    }
}
```
