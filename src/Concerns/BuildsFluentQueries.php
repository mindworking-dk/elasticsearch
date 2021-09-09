<?php

declare(strict_types=1);

namespace Huslab\Elasticsearch\Concerns;

use Huslab\Elasticsearch\Classes\Search;
use Huslab\Elasticsearch\Model;
use Huslab\Elasticsearch\Query;
use stdClass;

use function array_filter;
use function array_key_exists;
use function array_merge;
use function array_unique;
use function array_values;
use function count;
use function in_array;
use function is_array;
use function is_callable;

use const SORT_REGULAR;

trait BuildsFluentQueries
{
    /**
     * Ignored HTTP errors
     *
     * @var array
     */
    public $ignores = [];

    /**
     * Query body
     *
     * @var array
     */
    public $body = [];

    /**
     * Query bool must
     *
     * @var array
     */
    public $must = [];

    /**
     * Query bool must not
     *
     * @var array
     */
    public $must_not = [];

    /**
     * Index name
     * ==========
     * Name of the index to query. To search all data streams and indices in a
     * cluster, omit this parameter or use _all or *.
     * An index can be thought of as an optimized collection of documents and
     * each document is a collection of fields, which are the key-value pairs
     * that contain your data. By default, Elasticsearch indexes all data in
     * every field and each indexed field has a dedicated, optimized data
     * structure. For example, text fields are stored in inverted indices, and
     * numeric and geo fields are stored in BKD trees. The ability to use the
     * per-field data structures to assemble and return search results is what
     * makes Elasticsearch so fast.
     *
     * @var string|null
     * @see https://www.elastic.co/guide/en/elasticsearch/reference/7.10/search-search.html
     * @see https://www.elastic.co/guide/en/elasticsearch/reference/7.10/documents-indices.html
     */
    protected $index;

    /**
     * Mapping type
     * ============
     * Each document indexed is associated with a `_type` and an `_id`.
     * The `_type` field is indexed in order to make searching by type name fast
     * The value of the `_type` field is accessible in queries, aggregations,
     * scripts, and when sorting.
     * Note that mapping types are deprecated as of 6.0.0:
     * Indices created in Elasticsearch 7.0.0 or later no longer accept a
     * `_default_` mapping. Indices created in 6.x will continue to function as
     * before in Elasticsearch 6.x. Types are deprecated in APIs in 7.0, with
     * breaking changes to the index creation, put mapping, get mapping, put
     * template, get template and get field mappings APIs.
     *
     * @var string|null
     * @deprecated Mapping types are deprecated as of Elasticsearch 7.0.0
     * @see        https://www.elastic.co/guide/en/elasticsearch/reference/7.10/removal-of-types.html
     * @see        https://www.elastic.co/guide/en/elasticsearch/reference/7.10/mapping-type-field.html
     */
    protected $type;

    /**
     * Unique document ID
     * ==================
     * Each document has an `_id` that uniquely identifies it, which is indexed
     * so that documents can be looked up either with the GET API or the
     * `ids` query.
     * The `_id` can either be assigned at indexing time, or a unique `_id` can
     * be generated by Elasticsearch. This field is not configurable in
     * the mappings.
     *
     * The value of the `_id` field is accessible in queries such as `term`,
     * `terms`, `match`, and `query_string`.
     *
     * The `_id` field is restricted from use in aggregations, sorting, and
     * scripting. In case sorting or aggregating on the `_id` field is required,
     * it is advised to duplicate the content of the `_id` field into another
     * field that has `doc_values` enabled.
     *
     * @var string|null
     * @see https://www.elastic.co/guide/en/elasticsearch/reference/7.10/mapping-id-field.html
     */
    protected $id;

    /**
     * Scroll
     * ======
     * While a search request returns a single “page” of results, the scroll API
     * can be used to retrieve large numbers of results (or even all results)
     * from a single search request, in much the same way as you would use a
     * cursor on a traditional database.
     *
     * Scrolling is not intended for real time user requests, but rather for
     * processing large amounts of data, e.g. in order to reindex the contents
     * of one index into a new index with a different configuration.
     *
     * The results that are returned from a scroll request reflect the state of
     * the index at the time that the initial search request was made, like a
     * snapshot in time. Subsequent changes to documents (index, update or
     * delete) will only affect later search requests.
     *
     * In order to use scrolling, the initial search request should specify the
     * scroll parameter in the query string, which tells Elasticsearch how long
     * it should keep the “search context” alive (see Keeping the search context
     * alive), eg ?scroll=1m.
     *
     * @var string
     * @see https://www.elastic.co/guide/en/elasticsearch/reference/7.10/paginate-search-results.html#scroll-search-results
     */
    protected $scroll;

    /**
     * Scroll ID
     * =========
     * Identifier for the search and its search context.
     * You can use this scroll ID with the scroll API to retrieve the next batch
     * of search results for the request. See Scroll search results.
     * This parameter is only returned if the scroll query parameter is
     * specified in the request.
     *
     * @var string|null
     * @see https://www.elastic.co/guide/en/elasticsearch/reference/7.10/paginate-search-results.html#scroll-search-results
     */
    protected $scrollId = null;

    /**
     * Filter operators
     *
     * @var array
     */
    protected $operators = [
        Query::OPERATOR_EQUAL,
        Query::OPERATOR_NOT_EQUAL,
        Query::OPERATOR_GREATER_THAN,
        Query::OPERATOR_GREATER_THAN_OR_EQUAL,
        Query::OPERATOR_LOWER_THAN,
        Query::OPERATOR_LOWER_THAN_OR_EQUAL,
        Query::OPERATOR_LIKE,
        Query::OPERATOR_EXISTS,
    ];

    /**
     * Query bool filter
     *
     * @var array
     */
    protected $filter = [];

    /**
     * Query returned fields list
     *
     * @var array|null
     */
    protected $source;

    /**
     * Query sort fields
     *
     * @var array
     */
    protected $sort = [];

    /**
     * Search Type
     * ===========
     * There are different execution paths that can be done when executing a
     * distributed search. The distributed search operation needs to be
     * scattered to all the relevant shards and then all the results are
     * gathered back. When doing scatter/gather type execution, there are
     * several ways to do that, specifically with search engines.
     *
     * One of the questions when executing a distributed search is how much
     * results to retrieve from each shard. For example, if we have 10 shards,
     * the 1st shard might hold the most relevant results from 0 till 10, with
     * other shards results ranking below it. For this reason, when executing a
     * request, we will need to get results from 0 till 10 from all shards, sort
     * them, and then return the results if we want to ensure correct results.
     *
     * Another question, which relates to the search engine, is the fact that
     * each shard stands on its own. When a query is executed on a specific
     * shard, it does not take into account term frequencies and other search
     * engine information from the other shards. If we want to support accurate
     * ranking, we would need to first gather the term frequencies from all
     * shards to calculate global term frequencies, then execute the query on
     * each shard using these global frequencies.
     *
     * Also, because of the need to sort the results, getting back a large
     * document set, or even scrolling it, while maintaining the correct sorting
     * behavior can be a very expensive operation. For large result set
     * scrolling, it is best to sort by _doc if the order in which documents are
     * returned is not important.
     *
     * Elasticsearch is very flexible and allows to control the type of search
     * to execute on a per search request basis. The type can be configured by
     * setting the search_type parameter in the query string. The types are:
     *
     * Query Then Fetch
     * ----------------
     * Parameter value: `query_then_fetch`.
     *
     * Distributed term frequencies are calculated locally for each shard
     * running the search. We recommend this option for faster searches with
     * potentially less accurate scoring.
     *
     * This is the default setting, if you do not specify a `search_type` in
     * your request.
     *
     * Dfs, Query Then Fetch
     * ---------------------
     * Parameter value: `dfs_query_then_fetch`.
     *
     * Distributed term frequencies are calculated globally, using information
     * gathered from all shards running the search. While this option increases
     * the accuracy of scoring, it adds a round-trip to each shard, which can
     * result in slower searches.
     *
     * @var string
     * @psalm-var 'query_then_fetch'|'dfs_query_then_fetch'
     * @see       https://www.elastic.co/guide/en/elasticsearch/reference/7.10/search-search.html#search-type
     */
    protected $searchType;

    /**
     * Number of hits to return
     * ========================
     * Defines the number of hits to return. Defaults to `10`.
     *
     * By default, you cannot page through more than 10,000 hits using the
     * `from` and `size` parameters. To page through more hits, use the
     * `search_after` parameter.
     *
     * @var int
     * @see https://www.elastic.co/guide/en/elasticsearch/reference/7.10/search-search.html#search-type
     */
    protected $size = Query::DEFAULT_LIMIT;

    /**
     * Starting document offset
     * ========================
     * Starting document offset. Defaults to `0`.
     *
     * By default, you cannot page through more than 10,000 hits using the
     * `from` and `size` parameters. To page through more hits, use the
     * `search_after` parameter.
     *
     * @var int
     * @see https://www.elastic.co/guide/en/elasticsearch/reference/7.10/search-search.html#search-type
     */
    protected $from = Query::DEFAULT_OFFSET;

    /**
     * Sets the name of the index to use for the query.
     *
     * @param string|null $index
     *
     * @return $this
     */
    public function index(?string $index = null): self
    {
        $this->index = $index;

        return $this;
    }

    /**
     * Sets the document mapping type to restrict the query to.
     *
     * @param string $type Name of the document mapping type
     *
     * @return $this
     * @deprecated Mapping types are deprecated as of Elasticsearch 6.0.0
     * @see        https://www.elastic.co/guide/en/elasticsearch/reference/7.10/removal-of-types.html
     */
    public function type(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    /**
     * Enables the scroll API. The argument may be used to set the duration to
     * keep the scroll ID alive for. Defaults to 5 minutes.
     *
     * @param string $keepAlive
     *
     * @return $this
     */
    public function scroll(string $keepAlive = '5m'): self
    {
        $this->scroll = $keepAlive;

        return $this;
    }

    /**
     * Sets the query scroll ID.
     *
     * @param string|null $scroll
     *
     * @return $this
     */
    public function scrollId(?string $scroll): self
    {
        $this->scrollId = $scroll;

        return $this;
    }

    /**
     * Sets the query search type.
     *
     * @param string $type
     *
     * @psalm-param 'query_then_fetch'|'dfs_query_then_fetch' $type
     *
     * @return $this
     * @see         https://www.elastic.co/guide/en/elasticsearch/reference/6.8/search-request-search-type.html
     */
    public function searchType(string $type): self
    {
        $this->searchType = $type;

        return $this;
    }

    /**
     * Retrieves the query search type.
     *
     * @return string|null
     * @psalm-return 'query_then_fetch'|'dfs_query_then_fetch'
     * @see          https://www.elastic.co/guide/en/elasticsearch/reference/6.8/search-request-search-type.html
     */
    public function getSearchType(): ?string
    {
        return $this->searchType;
    }

    /**
     * Avoids throwing an error on unsuccessful responses from the Elasticsearch
     * server, as returned by the Elasticsearch client.
     *
     * @param mixed ...$args
     *
     * @return $this
     */
    public function ignore(...$args): self
    {
        $this->ignores = array_merge(
            $this->ignores,
            $this->flattenArgs($args)
        );

        $this->ignores = array_unique($this->ignores);

        return $this;
    }

    /**
     * Set the sorting field
     *
     * @param string|int $field
     * @param string     $direction
     *
     * @return $this
     */
    public function orderBy($field, string $direction = 'asc'): self
    {
        $this->sort[] = [$field => $direction];

        return $this;
    }

    /**
     * Set the query fields to return
     *
     * @param mixed ...$args
     *
     * @return $this
     */
    public function select(...$args): self
    {
        $fields = $this->flattenArgs($args);

        $this->source['include'] = array_unique(array_merge(
            $this->source['include'] ?? [],
            $fields
        ));

        $this->source['exclude'] = array_values(array_filter(
            $this->source['exclude'] ?? [], function ($field) {
            return ! in_array(
                $field,
                $this->source['include'],
                false
            );
        }));

        return $this;
    }

    /**
     * Set the ignored fields to not be returned
     *
     * @param mixed ...$args
     *
     * @return $this
     */
    public function unselect(...$args): self
    {
        $fields = $this->flattenArgs($args);

        $this->source[Query::SOURCE_EXCLUDES] = array_unique(array_merge(
            $this->source[Query::SOURCE_EXCLUDES] ?? [],
            $fields
        ));

        $this->source[Query::SOURCE_INCLUDES] = array_values(array_filter(
            $this->source[Query::SOURCE_INCLUDES], function ($field) {
            return ! in_array(
                $field,
                $this->source[Query::SOURCE_EXCLUDES] ?? [],
                false
            );
        }));

        return $this;
    }

    /**
     * @param string|null $id ID to filter by
     *
     * @return $this
     * @deprecated Use id() instead
     * @see        Query::id()
     */
    public function _id(?string $id = null): self
    {
        return $this->id($id);
    }

    /**
     * Set the query where clause
     *
     * @param string|callable $name
     * @param string          $operator
     * @param mixed|null      $value
     *
     * @return $this
     */
    public function where(
        $name,
        $operator = Query::OPERATOR_EQUAL,
        $value = null
    ): self {
        if (is_callable($name)) {
            $name($this);

            return $this;
        }

        if ( ! $this->isOperator((string)$operator)) {
            $value = $operator;
            $operator = Query::OPERATOR_EQUAL;
        }

        switch ((string)$operator) {
            case Query::OPERATOR_EQUAL:
                if ($name === Query::FIELD_ID) {
                    return $this->id((string)$value);
                }

                $this->filter[] = ['term' => [$name => $value]];
                break;

            case Query::OPERATOR_GREATER_THAN:
                $this->filter[] = ['range' => [$name => ['gt' => $value]]];
                break;

            case Query::OPERATOR_GREATER_THAN_OR_EQUAL:
                $this->filter[] = ['range' => [$name => ['gte' => $value]]];
                break;

            case Query::OPERATOR_LOWER_THAN:
                $this->filter[] = ['range' => [$name => ['lt' => $value]]];
                break;

            case Query::OPERATOR_LOWER_THAN_OR_EQUAL:
                $this->filter[] = ['range' => [$name => ['lte' => $value]]];
                break;

            case Query::OPERATOR_LIKE:
                $this->must[] = ['match' => [$name => $value]];
                break;

            case Query::OPERATOR_EXISTS:
                $this->whereExists($name, (bool)$value);
        }

        return $this;
    }

    /**
     * Set the query where clause and retrieve the first matching document.
     *
     * @param string|callable $name
     * @param string          $operator
     * @param mixed|null      $value
     *
     * @return Model|null
     */
    public function firstWhere(
        $name,
        $operator = Query::OPERATOR_EQUAL,
        $value = null
    ): ?Model {
        return $this
            ->where($name, $operator, $value)
            ->first();
    }

    /**
     * Set the query inverse where clause
     *
     * @param string|callable $name
     * @param string          $operator
     * @param null            $value
     *
     * @return $this
     */
    public function whereNot(
        $name,
        $operator = Query::OPERATOR_EQUAL,
        $value = null
    ): self {
        if (is_callable($name)) {
            $name($this);

            return $this;
        }

        if ( ! $this->isOperator($operator)) {
            $value = $operator;
            $operator = Query::OPERATOR_EQUAL;
        }

        switch ($operator) {
            case Query::OPERATOR_EQUAL:
                $this->must_not[] = ['term' => [$name => $value]];
                break;

            case Query::OPERATOR_GREATER_THAN:
                $this->must_not[] = ['range' => [$name => ['gt' => $value]]];
                break;

            case Query::OPERATOR_GREATER_THAN_OR_EQUAL:
                $this->must_not[] = ['range' => [$name => ['gte' => $value]]];
                break;

            case Query::OPERATOR_LOWER_THAN:
                $this->must_not[] = ['range' => [$name => ['lt' => $value]]];
                break;

            case Query::OPERATOR_LOWER_THAN_OR_EQUAL:
                $this->must_not[] = ['range' => [$name => ['lte' => $value]]];
                break;

            case Query::OPERATOR_LIKE:
                $this->must_not[] = ['match' => [$name => $value]];
                break;

            case Query::OPERATOR_EXISTS:
                $this->whereExists($name, ! $value);
        }

        return $this;
    }

    /**
     * Set the query where between clause
     *
     * @param string $name
     * @param mixed  $firstValue
     * @param mixed  $lastValue
     *
     * @return $this
     */
    public function whereBetween(
        string $name,
        $firstValue,
        $lastValue = null
    ): self {
        if (is_array($firstValue) && count($firstValue) === 2) {
            [$firstValue, $lastValue] = $firstValue;
        }

        $this->filter[] = [
            'range' => [
                $name => [
                    'gte' => $firstValue,
                    'lte' => $lastValue,
                ],
            ],
        ];

        return $this;
    }

    /**
     * Set the query where not between clause
     *
     * @param string     $name
     * @param mixed      $firstValue
     * @param mixed|null $lastValue
     *
     * @return $this
     */
    public function whereNotBetween(
        string $name,
        $firstValue,
        $lastValue = null
    ): self {
        if (is_array($firstValue) && count($firstValue) === 2) {
            [$firstValue, $lastValue] = $firstValue;
        }

        $this->must_not[] = [
            'range' => [
                $name => [
                    'gte' => $firstValue,
                    'lte' => $lastValue,
                ],
            ],
        ];

        return $this;
    }

    /**
     * Set the query where in clause
     *
     * @param string|callable $name
     * @param array           $value
     *
     * @return $this
     */
    public function whereIn($name, $value = []): self
    {
        if (is_callable($name)) {
            $name($this);

            return $this;
        }

        $this->filter[] = [
            'terms' => [$name => $value],
        ];

        return $this;
    }

    /**
     * Set the query where not in clause
     *
     * @param string|callable $name
     * @param array           $value
     *
     * @return $this
     */
    public function whereNotIn($name, $value = []): self
    {
        if (is_callable($name)) {
            $name($this);

            return $this;
        }

        $this->must_not[] = [
            'terms' => [$name => $value],
        ];

        return $this;
    }

    /**
     * Set the query where exists clause
     *
     * @param string $name
     * @param bool   $exists
     *
     * @return $this
     */
    public function whereExists(string $name, bool $exists = true): self
    {
        if ($exists) {
            $this->must[] = [
                'exists' => ['field' => $name],
            ];
        } else {
            $this->must_not[] = [
                'exists' => ['field' => $name],
            ];
        }

        return $this;
    }

    /**
     * Add a condition to find documents which are some distance away from the
     * given geo point.
     *
     * @see https://www.elastic.co/guide/en/elasticsearch/reference/2.4/query-dsl-geo-distance-query.html
     *
     * @param string|callable $name     A name of the field.
     * @param mixed           $value    A starting geo point which can be
     *                                  represented by a string 'lat,lon', an
     *                                  object like `{'lat': lat, 'lon': lon}`
     *                                  or an array like `[lon,lat]`.
     * @param string          $distance A distance from the starting geo point.
     *                                  It can be for example '20km'.
     *
     * @return $this
     */
    public function distance($name, $value, string $distance): self
    {
        if (is_callable($name)) {
            $name($this);

            return $this;
        }

        $this->filter[] = [
            'geo_distance' => [
                $name => $value,
                'distance' => $distance,
            ],
        ];

        return $this;
    }

    /**
     * Search the entire document fields
     *
     * @param string|null   $queryString
     * @param callable|null $settings
     * @param int|null      $boost
     *
     * @return $this
     * @noinspection PhpParamsInspection
     */
    public function search(
        ?string $queryString = null,
        $settings = null,
        ?int $boost = null
    ): self {
        if ($queryString) {
            $search = new Search(
                $this,
                $queryString,
                $settings
            );

            $search->boost($boost ?? 1);
            $search->build();
        }

        return $this;
    }

    /**
     * @param string $path
     *
     * @return $this
     */
    public function nested(string $path): self
    {
        $this->body = [
            'query' => [
                'nested' => [
                    'path' => $path,
                ],
            ],
        ];

        return $this;
    }

    /**
     * Get highlight result
     *
     * @param mixed ...$args
     *
     * @return $this
     */
    public function highlight(...$args): self
    {
        $fields = $this->flattenArgs($args);
        $new_fields = [];

        foreach ($fields as $field) {
            $new_fields[$field] = new stdClass();
        }

        $this->body['highlight'] = [
            'fields' => $new_fields,
        ];

        return $this;
    }

    /**
     * Sets the query body
     *
     * @param array $body
     *
     * @return $this
     */
    public function body(array $body = []): self
    {
        $this->body = $body;

        return $this;
    }

    /**
     * Set the collapse field
     *
     * @param string $field
     *
     * @return $this
     */
    public function groupBy(string $field): self
    {
        $this->body['collapse'] = [
            'field' => $field,
        ];

        return $this;
    }

    /**
     * Retrieves the ID the query is restricted to.
     *
     * @return string|null
     */
    public function getId(): ?string
    {
        return $this->id;
    }

    /**
     * Retrieves all ignored fields
     *
     * @return array
     */
    public function getIgnores(): array
    {
        return $this->ignores;
    }

    /**
     * Retrieves the name of the index used for the query.
     *
     * @return string|null
     */
    public function getIndex(): ?string
    {
        return $this->index;
    }

    /**
     * Get the query scroll
     *
     * @return string|null
     */
    public function getScroll(): ?string
    {
        return $this->scroll;
    }

    public function getScrollId(): ?string
    {
        return $this->scrollId;
    }

    /**
     * Retrieves the document mapping type the query is restricted to.
     *
     * @return string|null
     * @deprecated Mapping types are deprecated as of Elasticsearch 6.0.0
     * @see        https://www.elastic.co/guide/en/elasticsearch/reference/7.10/removal-of-types.html
     */
    public function getType(): ?string
    {
        return $this->type;
    }

    /**
     * Adds a term filter for the `_id` field.
     *
     * @param string|null $id
     *
     * @return $this
     */
    public function id(?string $id = null): self
    {
        $this->id = $id;
        $this->filter[] = [
            'term' => [
                Query::FIELD_ID => $id,
            ],
        ];

        return $this;
    }

    /**
     * Set the query offset
     *
     * @param int $from
     *
     * @return $this
     */
    public function skip(int $from = 0): self
    {
        $this->from = $from;

        return $this;
    }

    /**
     * Sets the number of hits to return from the result.
     *
     * @param int $size
     *
     * @return $this
     */
    public function take(int $size = Query::DEFAULT_LIMIT): self
    {
        $this->size = $size;

        return $this;
    }

    /**
     * Get the query limit
     *
     * @return int
     * @deprecated Use getSize() instead
     */
    protected function getTake(): int
    {
        return $this->getSize();
    }

    /**
     * Retrieves the number of hits to limit the query to.
     *
     * @return int
     */
    protected function getSize(): int
    {
        return $this->size;
    }

    /**
     * Get the query offset
     *
     * @return int
     */
    protected function getSkip(): int
    {
        return $this->from;
    }

    /**
     * check if it's a valid operator
     *
     * @param $string
     *
     * @return bool
     */
    protected function isOperator(string $string): bool
    {
        return in_array(
            $string,
            $this->operators,
            true
        );
    }

    /**
     * Generate the query body
     *
     * @return array
     */
    protected function getBody(): array
    {
        $body = $this->body;

        if ($this->source !== null) {
            $source = $body[Query::FIELD_SOURCE] ?? [];

            // TODO: Shouldn't the body-defined source take precedence here?
            $body[Query::FIELD_SOURCE] = array_merge(
                $source,
                $this->source
            );
        }

        $body[self::FIELD_QUERY] = $body[self::FIELD_QUERY] ?? [];

        if (count($this->must)) {
            $body[self::FIELD_QUERY]['bool']['must'] = $this->must;
        }

        if (count($this->must_not)) {
            $body[self::FIELD_QUERY]['bool']['must_not'] = $this->must_not;
        }

        if (count($this->filter)) {
            $body[self::FIELD_QUERY]['bool']['filter'] = $this->filter;
        }

        if (count($body[self::FIELD_QUERY]) === 0) {
            unset($body[self::FIELD_QUERY]);
        }

        if (count($this->sort)) {
            $sortFields = array_key_exists(self::FIELD_SORT, $body)
                ? $body[self::FIELD_SORT]
                : [];

            $body[self::FIELD_SORT] = array_unique(
                array_merge($sortFields, $this->sort),
                SORT_REGULAR
            );
        }

        $this->body = $body;

        return $body;
    }

    private function flattenArgs(array $args): array
    {
        $flattened = [];

        foreach ($args as $arg) {
            if (is_array($arg)) {
                /** @noinspection SlowArrayOperationsInLoopInspection */
                $flattened = array_merge($flattened, $arg);
            } else {
                $flattened[] = $arg;
            }
        }

        return $flattened;
    }
}
