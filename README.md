

docker run --name neos7-es -p 9200:9200 -p 9300:9300 -e "discovery.type=single-node" docker.elastic.co/elasticsearch/elasticsearch:7.10.2


- only batch indexing
- only document nodes end up in the index
