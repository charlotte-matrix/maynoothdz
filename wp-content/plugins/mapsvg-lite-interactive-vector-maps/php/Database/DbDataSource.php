<?php

namespace MapSVG;

class DbDataSource implements DataSourceInterface
{
  /**
   * @var Database
   */
  private $db;
  /**
   * @var string
   */
  private $tablePrefix;

  /**
   * @var Schema
   */
  private $schema;
  private $source;

  public function __construct($schema)
  {
    $this->db = Database::get();
    $this->tablePrefix = $this->db->mapsvg_prefix;
    $this->schema = $schema;
    $this->source = $schema->name;
  }

  public function setSchema($schema)
  {
    $this->schema = $schema;
  }

  public function getTableName()
  {
    return $this->tablePrefix . $this->source;
  }

  public function setSource(string $source): void
  {
    $this->source = $source;
  }

  public function find($query)
  {
    // return $this->db->get_results($this->getTableName(), $criteria);
    $filters_sql = array();
    $filters_sql_fields = array();
    $filter_regions = '';
    $filter_regions_fields = [];
    $filter_post_ids = '';

    $start = ($query->page - 1) * $query->perpage;
    $search_fallback = isset($query->searchFallback) ? $query->searchFallback === true : false;

    $select_distance = '';
    $select_distance_fields = [];
    $having = '';
    $having_fields = [];
    $postIds = null;
    $region_table_name = "";
    $postField = null;

    if (!empty($query->filters) || !empty($query->search)) {

      $postField = $this->schema->getFieldByType('post');
      $postFilter = null;

      if ($postField && (isset($query->filters["post"]) || !empty($query->search))) {
        $postFilter = isset($query->filters["post"]) ? $query->filters["post"] : array();
        unset($query->filters["post"]);
      }

      if ($postField && $postFilter) {

        $postType = $postField->post_type;

        $args = [
          'post_type' => $postType,
          'fields' => 'ids',
          'posts_per_page' => -1,
          'meta_query' => [
            'relation' => 'AND'
          ]
        ];

        if (isset($postFilter['name'])) {
          $args['name'] = $postFilter['name'];
        }

        if (isset($postFilter['meta']) && is_array($postFilter['meta'])) {
          // $args['meta_query'] = ["relation" => "AND"];

          foreach ($postFilter['meta'] as $metaKey => $metaValue) {
            $meta_query_items = [];
            $acfField = null;
            if (function_exists('get_field_object')) {
              $acfField = acf_get_field($metaKey);
            }

            if ($acfField && !empty($acfField['multiple']) && $acfField['multiple']) {
              $compare = 'LIKE';
              $meta_query_sub = ['relation' => 'OR'];

              if (!is_array($metaValue)) {
                $metaValue = array($metaValue);
              }


              foreach ($metaValue as $val) {

                $valueFormatted = isset($val['value']) ? $val['value'] : $val;

                $meta_query_sub[] = [
                  'key'     => $metaKey,
                  'value'   => '"' . $valueFormatted . '"',
                  'compare' => 'LIKE'
                ];
              }
              $meta_query_items[] = $meta_query_sub;
            } else {
              $compare = is_array($metaValue) ? 'IN'  : '=';
              $value = is_array($metaValue) ? array_map(function ($item) {

                $val = isset($item['value']) ? $item['value'] : $item;
                return $val;
              }, $metaValue) : $metaValue;

              $meta_query_items[] = [
                'key' => $metaKey,
                'value' => $value,
                'compare' => $compare
              ];
            }
          }
          // Set meta_query - always wrap in array, add relation only if multiple items
          if (count($meta_query_items) > 1) {
            $args['meta_query'] = array_merge(["relation" => "AND"], $meta_query_items);
          } else {
            $args['meta_query'] = $meta_query_items;
          }
        }
        if (isset($postFilter['taxonomy']) && is_array($postFilter['taxonomy'])) {
          $args['tax_query'] = ["relation" => "AND"];
          foreach ($postFilter['taxonomy'] as $taxKey => $taxValue) {
            $operator = 'IN';
            $terms = $taxValue;
            if (is_array($taxValue)) {
              $terms = array_map(function ($item) {
                return isset($item['value']) ? $item['value'] : $item;
              }, $taxValue);
            }
            $args['tax_query'][] = [
              'taxonomy' => $taxKey,
              'field' => 'slug',
              'terms' => $terms,
              'operator' => $operator
            ];
          }
        }
        // Add post_status filter
        if (isset($postFilter['post_status'])) {
          $status = $postFilter['post_status'];
          if (is_array($status)) {
            // If it's an array of objects with value/label, extract value
            if (!empty($status) && is_array($status[0]) && isset($status[0]['value'])) {
              $status = array_map(function ($item) {
                return $item['value'];
              }, $status);
            }
          }
          $args['post_status'] = $status;
        }
        // Add post_title filter
        if (isset($postFilter['post_title'])) {
          // $exact_title = $postFilter['post_title'];
          // add_filter('posts_where', function ($where) use ($exact_title) {
          //   global $wpdb;
          //   $where .= $wpdb->prepare(" AND post_title = %s ", $exact_title);
          //   return $where;
          // });
          $args['s'] = $postFilter['post_title'];
        }

        error_log(print_r($args, true));

        $wpQuery = new \WP_Query($args);

        $postIds = $wpQuery->posts;

        // In no posts were found, just return emty array - no need to run the query
        if (empty($postIds)) {
          return [];
        }

        $placeholders = implode(',', array_fill(0, count($postIds), '%d'));

        $filters_sql[] = " post IN ($placeholders)";
        $filters_sql_fields = array_merge($filters_sql_fields, $postIds);
      }

      if (!empty($query->filters)) foreach ($query->filters as $fieldName => $fieldValue) {

        if ($fieldValue != '') {

          // There are 2 special parameters in the search query which are not real DB fields.
          // 1. prefix - for filtering objects by prefix in the ID
          if ($fieldName === 'prefix') {
            $pkField = $this->schema->getPrimaryKeyFieldName();
            $filters_sql[] = '`' . $pkField . '` LIKE %s';
            $filters_sql_fields[] = $fieldValue . '%';
            continue;
          }
          // 2. distance - for filtering objects by lat/lng coordinates
          if ($fieldName === 'distance') {
            $fieldName = 'location';
          }


          /**
           * This ensures that the field is a valid field in the schema
           * So we don't need to sanitize the field name
           */
          $field = $this->schema->getField($fieldName);

          if (!$field) {
            continue;
          }

          if ($field->type === 'region') {
            $regions_table = $fieldValue["table_name"];
            $regions_array = $fieldValue["region_ids"];

            foreach ($regions_array as $region_id) {
              $regions_placeholders[] = "%s";
            }

            $regions_sql = "r2o.region_id IN (" . implode(',', $regions_placeholders) . ")";

            $pkField = $this->schema->getPrimaryKeyFieldName();
            $filter_regions = "INNER JOIN {$this->db->mapsvg_prefix}r2o r2o ON r2o.objects_table=%s AND r2o.regions_table=%s AND r2o.object_id=`{$pkField}` AND {$regions_sql}";

            $filter_regions_fields = array_merge([$this->source, $regions_table], $regions_array);
          } else if ($field->type === 'location') {
            if (isset($fieldValue['geoPoint']) && !empty($fieldValue['geoPoint']["lat"]) && !empty($fieldValue['geoPoint']["lng"])) {
              $having = ' HAVING distance < %d ';
              $having_fields = [$fieldValue['length']];
              $geoPoint = $fieldValue['geoPoint'];
              $koef = $fieldValue['units'] === 'mi' ? 3959 : 6371;

              $select_distance = ", (
                                %d * acos(
                                    cos( radians(%f) )
                                    * cos( radians( location_lat ) )
                                    * cos( radians( location_lng ) - radians(%f) )
                                    + sin( radians(%f) )
                                    * sin( radians( location_lat ) )
                                )
                                ) AS distance ";
              $select_distance_fields = [$koef, $geoPoint['lat'], $geoPoint['lng'],  $geoPoint['lat']];
            }
          } else {
            if (isset($field->multiselect) && $field->multiselect === true) {

              // Support new format: {operator: "in"|"or"|"and", value: [...]}
              $multiselect_operator = 'and';
              if (is_array($fieldValue) && isset($fieldValue['operator']) && isset($fieldValue['value'])) {
                $multiselect_operator = strtolower($fieldValue['operator']);
                $fieldValue            = $fieldValue['value'];
              }

              // "in" and "or" both produce OR logic; "and" (default) produces AND logic
              $multiselect_glue = in_array($multiselect_operator, ['in', 'or'], true) ? ' OR ' : ' AND ';

              if (is_array($fieldValue)) {
                foreach ($fieldValue as $index => $v) {
                  if (is_array($v) && isset($v["label"]) && isset($v["value"])) {
                    $value = $v["value"];
                  } else {
                    $value = $v;
                  }
                  $fieldValue[$index] = "`{$field->name}` LIKE %s";
                  $filters_sql_fields[] = "%\"" . $this->db->esc_like($value) . "\"%";
                }
                $filters_sql[] = "(" . implode($multiselect_glue, $fieldValue) . ")";
              } else {
                $filters_sql[] = "`{$field->name}` LIKE %s";
                $filters_sql_fields[] = "%\"" . $this->db->esc_like($fieldValue) . "\"%";
              }
            } else {
              if (is_array($fieldValue)) {
                if (!empty($fieldValue[0]) && is_array($fieldValue[0])) {
                  $fieldValue = array_map(function ($elem) {
                    return $elem["value"];
                  }, $fieldValue);
                }
                $values = implode(', ', array_fill(0, count($fieldValue), '%s'));
                $filters_sql[] = "`{$field->name}` IN ({$values})";
                $filters_sql_fields = array_merge($filters_sql_fields, $fieldValue);
              } else {
                $filters_sql[] = "`{$field->name}`=%s";
                $filters_sql_fields[] = $fieldValue;
              }
            }
          }
        }
      }
    }

    if (!empty($query->filterout)) {
      foreach ($query->filterout as $key => $value) {
        if ($key) {
          $fieldOut = $this->schema->getField($key);
          if ($fieldOut) {
            $filters_sql[] = "`{$fieldOut->name}`!= %s";
            $filters_sql_fields[] = $value;
          }
        }
      }
    }

    // Do text search
    if (!empty($query->search)) {

      // START filters_posts
      $options = ['withPost' => true];
      // REPLACE
      // $options = [];
      // END
      $searchable_fields = $this->schema->getSearchableFields(null, $options);


      $like_fields = array();
      $like_fields_values = array();
      $postTextSearchIds = array();

      if (count($searchable_fields) > 0) {

        // START filters_posts
        $postField = null;
        foreach ($searchable_fields as $field) {
          if ($field['type'] === 'post') {
            $postField = $field;
            break;
          }
        }

        if ($postField) {
          $searchArgs = [
            'post_type' => $postField["type"],
            'fields' => 'ids',
            'posts_per_page' => -1,
            's' => $query->search,
          ];
          $wpQuery = new \WP_Query($searchArgs);
          $postTextSearchIds = $wpQuery->posts;
        }
        // END

        if (isset($search_fallback) && $search_fallback) {
          // Search using LIKE %%
          foreach ($searchable_fields as $f) {

            if ($f['type'] === 'post' && count($postTextSearchIds) > 0) {
              $like_fields[] = 'post IN (' . implode(',', $postTextSearchIds) . ')';
              $like_fields_values = array_merge($like_fields_values, $postTextSearchIds);
            } elseif ((isset($f['type']) && $f['type'] == 'region') || (isset($f['multiselect']) && $f['multiselect'] === true)) {
              $like_fields[] = "`{$f['name']}` LIKE %s";
              $like_fields_values[] = "%{$this->db->esc_like($query->search)}%";
            } else {
              $like_fields[] = "`{$f['name']}` REGEXP %s";
              $like_fields_values[] = "(^| ){$this->db->esc_like($query->search)}";
            }
          }
          $filters_sql[] = '(' . implode(' OR ', $like_fields) . ')';
          $filters_sql_fields = array_merge($filters_sql_fields, $like_fields_values);
        } else {
          // Search using FULLTEXT
          $_search = array();
          $match = array();
          $search_in  = array();
          $search_in_fields = array();
          $search_like  = array();
          $search_like_fields = array();
          $search_exact_fields = array();
          $search_exact = array();
          foreach ($searchable_fields as $index => $f) {
            if ($f['type'] === 'post') {
              if (count($postTextSearchIds) > 0) {
                $search_in[] = 'post IN (' . implode(',', array_fill(0, count($postTextSearchIds), '%d')) . ')';
                $search_in_fields = array_merge($search_in_fields, $postTextSearchIds);
              }
            } elseif ($f['type'] === 'text') {
              if (isset($f['searchType'])) {
                if ($f['searchType'] == 'fulltext') {
                  $match[] = $f['name'];
                } elseif ($f['searchType'] == 'like') {
                  $search_like[] = "`{$f['name']}` LIKE %s";
                  $search_like_fields[] = "{$this->db->esc_like($query->search)}%";
                } else {
                  $search_exact[] = "`{$f['name']}` = %s";
                  $search_exact_fields[] = $query->search;
                }
              } else {
                $match[] = $f['name'];
              }
            } else {
              $match[] = $f['name'];
            }
          }
          if (count($match) > 0) {
            $_search[] = "MATCH (" . implode(',', $match) . ") AGAINST (%s IN BOOLEAN MODE)";
            $filters_sql_fields[] = $query->search . "*";
          }
          if (!empty($search_in)) {
            $_search[] = '(' . implode(' OR ', $search_in) . ')';
            $filters_sql_fields = array_merge($filters_sql_fields, $search_in_fields);
          }
          if (!empty($search_like)) {
            $_search[] = '(' . implode(' OR ', $search_like) . ')';
            $filters_sql_fields = array_merge($filters_sql_fields, $search_like_fields);
          }
          if (!empty($search_exact)) {
            $_search[] = '(' . implode(' OR ', $search_exact) . ')';
            $filters_sql_fields = array_merge($filters_sql_fields, $search_exact_fields);
          }
          $filters_sql[] = '(' . implode(' OR ', $_search) . ')';
        }
      }
    }

    if ($filters_sql)
      $filters_sql = ' WHERE ' . implode(' AND ', $filters_sql);
    else
      $filters_sql = '';

    $sort  = '';


    if (!empty($query->sort)) {
      $sortArray = array();
      $distanceSortPresent = false;
      foreach ($query->sort as $group) {
        if ((isset($group['field']) && isset($group['order'])) && (!empty($group['field']) && in_array(strtolower($group['order']), array('asc', 'desc')))) {
          if ($group['field'] === 'distance') {
            $distanceSortPresent = true;
            if (!isset($filters['distance']) || empty($filters['distance'])) {
              continue;
            }
          }
          // If fields exists in schema, add it to the sort array
          // $group['order'] is checked for asc or desc
          if ($this->schema->getField($group['field'])) {
            $sortArray[] = '`' . $group['field'] . '` ' . $group['order'];
          }
        }
      }
      if (isset($query->filters['distance']) && !empty($query->filters['distance']) && !$distanceSortPresent) {
        array_unshift($sortArray, '`distance` ASC');
      }
      $sort = implode(',', $sortArray);
    } else {
      $pkField = $this->schema->getPrimaryKeyFieldName();
      $sortBy  = '`' . $pkField . '`';
      $sortDir = 'DESC';
      if (isset($query->sortBy) && !empty($query->sortBy) && $this->schema->getField($query->sortBy)) {
        // $query->sortBy is checked for valid field in schema
        $sortBy = '`' . $query->sortBy . '`';
      }
      if (isset($query->sortDir) && !empty($query->sortDir)) {
        if (in_array(strtolower($query->sortDir), array('desc', 'asc'))) {
          $sortDir = $query->sortDir;
        }
      }
      $sort = ($sortBy) . ' ' . ($sortDir);

      if (isset($query->filters['distance'])) {
        $sort = 'distance ASC, ' . $sort . ' ';
      }
    }

    $fields = '*';
    if ($query->fields) {
      $validFields = array_intersect($query->fields, $this->schema->getFieldNames());
      if (!empty($validFields)) {
        $fields = '`' . implode('`,`', $validFields) . '`';
      }
    }

    $sort_sql = ($sort ? "ORDER BY {$sort}" : '');
    $limit_fields = [];
    $limit_sql = "";
    if ($query->perpage > 0) {
      $limit_sql = ($query->perpage > 0 ? "LIMIT %d,%d" : '');
      $limit_fields = [$start, ($query->perpage + 1)];
    }


    $queryParams = array_merge(
      // {$fields} -> already sanitized
      $select_distance_fields,
      // {$this->getTableName()} -> already sanitized
      $filter_regions_fields,
      $filters_sql_fields,
      $having_fields,
      // {$sort_sql} -> already sanitized
      $limit_fields
    );

    $query_sql = "SELECT {$fields}{$select_distance} FROM `{$this->getTableName()}`
        {$filter_regions}    
        {$filters_sql}
        {$having}     
        {$sort_sql}
        {$limit_sql}";
    if (count($queryParams) > 0) {
      $query_sql = $this->db->prepare($query_sql, $queryParams);
    }


    try {
      $data = $this->db->get_results($query_sql, ARRAY_A);
    } catch (\Exception $e) {
      Logger::error("[SERVER-002] " . $e->getMessage() . " — Read more: https://mapsvg.com/docs/errors#SERVER-002");
      $data = [];
    }

    return $data;
  }

  public function findOne($criteria)
  {
    // Start building the WHERE clause
    $whereClauses = [];
    $values = [];

    foreach ($criteria as $key => $value) {
      $field = $this->schema->getField($key);
      if (!$field) {
        continue;
      }
      $whereClauses[] = "{$field->name} = %s";
      $values[] = $value;
    }

    // Join the clauses with "AND"
    $whereClause = implode(' AND ', $whereClauses);

    // Execute the query and return the result
    return $this->db->get_row($this->db->prepare("SELECT * FROM {$this->getTableName()} WHERE {$whereClause} LIMIT 1", $values), ARRAY_A);
  }

  /**
   * Returns true if no existing row matches all given field/value pairs
   * (composite unique-index style check).
   *
   * @param array $criteria Column => value pairs, e.g. ['type' => 'post', 'postType' => 'movie']
   * @return bool
   */
  public function isUnique(array $criteria): bool
  {
    if (empty($criteria)) {
      return true;
    }

    $whereClauses = [];
    $values = [];

    foreach ($criteria as $key => $value) {
      $column = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $key);
      if ($column === '') {
        continue;
      }
      $whereClauses[] = "`{$column}` = %s";
      $values[] = $value;
    }

    if (empty($whereClauses)) {
      return true;
    }

    $whereClause = implode(' AND ', $whereClauses);
    $count = (int) $this->db->get_var(
      $this->db->prepare(
        "SELECT COUNT(*) FROM {$this->getTableName()} WHERE {$whereClause}",
        $values
      )
    );

    return $count === 0;
  }

  public function create($data)
  {
    $this->db->insert($this->getTableName(), $data);
    $pkField = $this->schema->getPrimaryKeyFieldName();
    $pkFieldObj = $this->schema->getField($pkField);
    $isAutoIncrement = $pkFieldObj && isset($pkFieldObj->auto_increment) && $pkFieldObj->auto_increment;
    Logger::info('DbDataSource::create - auto increment: ' . $pkField);
    Logger::info('DbDataSource::create - pkFieldObj: ' . print_r($pkFieldObj, true));
    Logger::info('DbDataSource::create - isAutoIncrement: ' . $isAutoIncrement);

    if ($isAutoIncrement) {
      Logger::info('DbDataSource::create - auto increment: ' . $this->db->insert_id);
      return $this->findOne([$pkField => $this->db->insert_id]);
    }
    Logger::info('DbDataSource::create - auto increment: ' . $data[$pkField]);
    return $this->findOne([$pkField => $data[$pkField]]);
  }

  public function update($data, $criteria)
  {
    return $this->db->update($this->getTableName(), $data, $criteria);
  }

  public function delete($id)
  {
    return $this->db->delete($this->getTableName(), $id);
  }

  public function truncate()
  {
    $query = "TRUNCATE TABLE `" . $this->getTableName() . "`";
    $this->db->query($query);
    return $this->db->rows_affected;
  }

  public function import($data, bool $upsert = true)
  {
    $values = array();
    $placeholders_sql_array = array();

    $keys = array_keys($data[0]);

    foreach ($data as $object) {
      $row_parts = array();
      foreach ($keys as $key) {
        $cell = $object[$key] ?? null;
        if ($cell === null) {
          $row_parts[] = 'NULL';
        } else {
          $row_parts[] = '%s';
          $values[] = $cell;
        }
      }
      $placeholders_sql_array[] = '(' . implode(',', $row_parts) . ')';
    }

    $placeholders_sql = implode(', ', $placeholders_sql_array);

    /**
     * Params in the query:
     * $this->getTableName(): sanitized
     * $keys: sanitized
     * VALUES: literal NULL or %s placeholders; $values matches %s count only
     */
    if ($upsert) {
      $update_sql_array = array();
      foreach ($keys as $key) {
        $update_sql_array[] = '`' . $key . '`=VALUES(`' . $key . '`)';
      }
      $update_sql = implode(', ', $update_sql_array);
      $query = $this->db->prepare(
        "INSERT INTO {$this->getTableName()} (`" . implode('`,`', $keys) . "`) VALUES {$placeholders_sql} ON DUPLICATE KEY UPDATE {$update_sql}",
        $values,
      );
    } else {
      $query = $this->db->prepare(
        "INSERT INTO {$this->getTableName()} (`" . implode('`,`', $keys) . "`) VALUES {$placeholders_sql}",
        $values,
      );
    }

    $this->db->query($query);

    return $data;
  }
}
