<?php

namespace MapSVG;

class PostTypesRepository
{
  /**
   * Returns the list of WP post types.
   *
   * @return array
   */
  public function find(): array
  {
    $args = array(
      '_builtin' => false
    );

    $_post_types = get_post_types($args, 'names');
    if (!$_post_types)
      $_post_types = array();

    $post_types = array();
    foreach ($_post_types as $pt) {
      if ($pt != 'mapsvg')
        $post_types[] = $pt;
    }
    $post_types[] = 'post';
    $post_types[] = 'page';


    return $post_types;
  }

  /**
   * Returns taxonomies and meta for a given post type.
   *
   * @param string $post_type
   * @return array
   */
  public function get(string $post_type): array
  {
    // Get taxonomies for the post type
    $taxonomies = [];
    $tax_objs = get_object_taxonomies($post_type, 'objects');
    foreach ($tax_objs as $tax) {
      $terms = get_terms(['taxonomy' => $tax->name, 'hide_empty' => false]);
      $taxonomies[] = [
        'name'  => $tax->name,
        'label' => $tax->label,
        'items' => array_map(function ($term) {
          return [
            'id'   => $term->term_id,
            'name' => $term->name,
            'slug' => $term->slug,
          ];
        }, is_array($terms) ? $terms : [])
      ];
    }

    // Get meta fields for the post type (using registered meta)
    $meta = [];
    global $wp_meta_keys;
    if (!empty($wp_meta_keys['post'][$post_type])) {
      foreach ($wp_meta_keys['post'][$post_type] as $meta_key => $meta_args) {
        $meta[] = [
          'name'  => $meta_key,
          'type'  => isset($meta_args['type']) ? $meta_args['type'] : 'string',
          'label' => isset($meta_args['description']) && !empty($meta_args['description']) ? ucfirst($meta_args['description']) : ucfirst($meta_key),
        ];
      }
    }
    // Add ACF fields
    if (function_exists('acf_get_field_groups')) {
      $field_groups = acf_get_field_groups(['post_type' => $post_type]);
      foreach ($field_groups as $group) {
        $fields = acf_get_fields($group['key']);
        if (is_array($fields)) {
          foreach ($fields as $field) {
            $exists = false;
            foreach ($meta as $m) {
              if ($m['name'] === $field['name']) {
                $exists = true;
                break;
              }
            }
            if (!$exists) {
              $meta[] = [
                'name' => $field['name'],
                'type' => isset($field['type']) ? $field['type'] : 'string',
                'label' => isset($field['label']) ? $field['label'] : ucfirst($field['name']),
              ];
            }
          }
        }
      }
    }

    return [
      'postType'  => $post_type,
      'taxonomy' => $taxonomies,
      'meta'       => $meta,
    ];
  }

  /**
   * Retrieves distinct values for a given field name from published posts.
   *
   * Only public-safe wp_posts columns are queried. Password-protected posts
   * are excluded even for those columns.
   *
   * @param string $fieldName The name of the field to retrieve distinct values for.
   * @param string $post_type
   * @return array An array of distinct values for the specified field.
   */
  public function getFieldValues($fieldName, $post_type)
  {
    $column = PublicPostColumns::canonicalName($fieldName);
    if ($column === null || !Utils::isSafeSlug($post_type)) {
      return [];
    }

    $db = Database::get();
    $postsTable = $db->posts();
    if (!Utils::isTableColumn($postsTable, $column)) {
      return [];
    }

    // Column name is allow-listed and verified against DESCRIBE; post_type is bound via prepare.
    $sql = $db->prepare(
      "SELECT DISTINCT `{$column}` FROM `{$postsTable}` WHERE post_status = 'publish' AND post_type = %s AND post_password = ''",
      $post_type
    );
    $results = $db->get_col($sql, 0);
    return $results ? $results : [];
  }

  /**
   * Retrieves unique taxonomy term names for a given taxonomy from published posts of one post type.
   *
   * @param string $name The taxonomy name (e.g., 'category', 'post_tag').
   * @param string $post_type
   * @return array An array of unique term names.
   */
  public function getTaxonomyValues($name, $post_type)
  {
    if (!Utils::isSafeSlug($name) || !Utils::isSafeSlug($post_type)) {
      return [];
    }

    $db = Database::get();
    $sql = $db->prepare(
      "SELECT DISTINCT t.name FROM {$db->prefix}terms t
                INNER JOIN {$db->prefix}term_taxonomy tt ON t.term_id = tt.term_id
                INNER JOIN {$db->prefix}term_relationships tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
                INNER JOIN {$db->posts} p ON tr.object_id = p.ID
                WHERE tt.taxonomy = %s AND p.post_type = %s AND p.post_status = 'publish' AND p.post_password = ''",
      $name,
      $post_type
    );
    $results = $db->get_col($sql, 0);
    return $results ? $results : [];
  }

  /**
   * Retrieves unique meta values for a given meta key from published posts of one post type.
   *
   * @param string $name The meta key.
   * @param string $post_type
   * @return array An array of unique meta values.
   */
  public function getMetaValues($name, $post_type)
  {
    if (!Utils::isSafeSlug($name) || !Utils::isSafeSlug($post_type)) {
      return [];
    }

    $db = Database::get();
    $sql = $db->prepare(
      "SELECT DISTINCT pm.meta_value FROM {$db->postmeta} pm
                INNER JOIN {$db->posts} p ON pm.post_id = p.ID
                WHERE pm.meta_key = %s AND p.post_type = %s AND p.post_status = 'publish' AND p.post_password = '' AND pm.meta_value IS NOT NULL AND pm.meta_value != ''",
      $name,
      $post_type
    );
    $results = $db->get_col($sql, 0);

    // Unserialize meta values if needed and flatten arrays to scalars
    $flat = array();
    if (is_array($results)) {
      foreach ($results as $value) {
        $unserialized = maybe_unserialize($value);
        if (is_array($unserialized)) {
          foreach ($unserialized as $u) {
            if (is_scalar($u) || (is_object($u) && method_exists($u, '__toString'))) {
              $flat[] = (string)$u;
            }
          }
        } elseif (is_scalar($unserialized) || (is_object($unserialized) && method_exists($unserialized, '__toString'))) {
          $flat[] = (string)$unserialized;
        }
      }
    }

    if (empty($flat)) {
      return $results ? $results : [];
    }

    $flat = array_values(array_unique($flat));
    return $flat;
  }
}
