<?php

/*

  Package: Mambo / Joomla to Wordpress Importer
  Author: misterpah, webuddha
  Version: 1.0, 2.0
  License: GPL
  Author URI: http://www.misterpah.com, http://www.webuddha.com/
  Github URI:

  Insert this file into the root of joomla installation. make sure that mambo
  configuration file (configuration.php) is present.

*/

// Runtime Params
  $format     = (string)$_REQUEST['format'];
  $catFilters = (array)$_REQUEST['catFilters'];

// Read Mambo Configuration
  include "configuration.php";
  $JConfig = new JConfig();
  $dbh      = $JConfig->db;
  $dbuser   = $JConfig->user;
  $dbpass   = $JConfig->password;
  $dbhost   = $JConfig->host;         // localhost is default
  $dbprefix = $JConfig->dbprefix;

// Connect to Database
  $link = mysql_connect("$dbhost", "$dbuser", "$dbpass")
    or die("Could not connect");
  mysql_select_db("$dbh") or die("<p>Could not select database</p>");

// Query Sections
  $query = str_replace('#__', $dbprefix, "
    SELECT *
    FROM #__sections
    ");
  $result = mysql_query($query) or die("<h3>Query failed</h3><pre>" . $query . "\n\n" . mysql_error() . "</pre>");
  while($row = mysql_fetch_assoc( $result )){ $sections[] = $row; }

// Query Categories
  $query = str_replace('#__', $dbprefix, "
    SELECT *
    FROM #__categories
    WHERE section > 0
    ");
  $result = mysql_query($query) or die("<h3>Query failed</h3><pre>" . $query . "\n\n" . mysql_error() . "</pre>");
  while($row = mysql_fetch_assoc( $result )){ $categories[] = $row; }

// Process Request
  if( !empty($catFilters) ){

    $payload = array(
      'site'       => $mosConfig_live_site,
      'categories' => array(),
      'content'    => array()
      );

    $where = array();
    $section_ids = array();
    $category_ids = array();
    foreach( $catFilters AS $catFilter ){
      if( preg_match('/^section\.(\d+)$/', $catFilter, $match) ){
        $section_ids[] = $match[1];
      }
      else if( preg_match('/^category\.(\d+)$/', $catFilter, $match) ){
        $category_ids[] = $match[1];
      }
    }
    if( $category_ids )
      $where[] = "content.catid IN ('". implode("','", $category_ids) ."')";
    if( $section_ids )
      $where[] = "content.sectionid IN ('". implode("','", $section_ids) ."')";

    while( $row = mysql_fetch_assoc( $result ) ){
      $payload['content'][] = $row;
    }

    $query = "
      SELECT category.name AS category_name
        , category.title AS category_title
        , category.description AS category_description
        , section.name AS parent_category_name
        , section.title AS parent_category_title
        , section.description AS parent_category_description
      FROM #__content AS content
      LEFT JOIN #__categories AS category ON category.id = content.catid
      LEFT JOIN #__sections AS section ON section.id = content.sectionid
      ". ($where ? "WHERE " . implode(' AND ', $where) : '') ."
      GROUP BY `category`.`id`
      ORDER BY `section`.`name`, `category`.`name`
      ";
    $query = str_replace('#__', $dbprefix, $query);
    $result = mysql_query($query) or die("<h3>Query failed</h3><pre>" . $query . "\n\n" . mysql_error() . "</pre>");
    while( $row = mysql_fetch_assoc( $result ) ){
      $payload['categories'][] = array(
        'title' => $row['category_title'],
        'slug' => $row['category_name'],
        'parent' => array(
          'title' => $row['parent_category_title'],
          'slug' => $row['parent_category_name']
          )
        );
    }

    $query = "
      SELECT content.title AS post_title
        , content.title_alias AS post_name
        , CONCAT(content.introtext,content.fulltext) AS post_content
        , IF(content.state > 0,'publish','draft') AS post_status
        , content.created AS post_date
        , content.created AS post_date_gmt
        , content.modified AS post_modified
        , content.modified AS post_modified_gmt
        , content.metakey AS meta_keywords
        , content.metadesc AS meta_description
        , category.name AS category_name
        , category.title AS category_title
        , category.description AS category_description
        , section.name AS parent_category_name
        , section.title AS parent_category_title
        , section.description AS parent_category_description
      FROM #__content AS content
      LEFT JOIN #__categories AS category ON category.id = content.catid
      LEFT JOIN #__sections AS section ON section.id = content.sectionid
      ". ($where ? "WHERE " . implode(' AND ', $where) : '') ."
      ORDER BY `content`.`id`
      ";
    $query = str_replace('#__', $dbprefix, $query);
    $result = mysql_query($query) or die("<h3>Query failed</h3><pre>" . $query . "\n\n" . mysql_error() . "</pre>");
    while( $row = mysql_fetch_assoc( $result ) ){
      $row['category'] = array(
        'title' => $row['category_title'],
        'slug' => $row['category_name'],
        'parent' => array(
          'title' => $row['parent_category_title'],
          'slug' => $row['parent_category_name']
          )
        );
      unset( $row['category_name'], $row['category_title'], $row['parent_category_name'], $row['parent_category_title'] );
      $payload['content'][] = $row;
    }

  }

// Render Page
  ?>
  <table width="100%" height="100%">
    <tr>
      <td valign=top>
        <form>
          <input type="submit">
          <hr>
          <label>Format</label>
          <select name="format">
            <option value="json_compress">Compressed JSON</option>
            <option value="json" <?= ($format == 'json'?'selected':'') ?>>JSON</option>
            <option value="raw" <?= ($format == 'raw'?'selected':'') ?>>Raw</option>
          </select>
          <br>
          <label>Category Filter(s)</label>
          <select name="catFilters[]" multiple size="50">
            <option>* All Categories</option>
            <?php
              foreach( $sections AS $section ){
                echo '<optgroup label="' . $section['title'] . '">';
                echo '<option value="section.' . $section['id'] . '">* All Section Categories</option>';
                foreach( $categories AS $category ){
                  if( $category['section'] == $section['id'] )
                    echo '<option value="category.' . $category['id'] . '" '. (in_array('category.' . $category['id'], $catFilters) ? 'selected' : '') .'>' . $category['title'] . '</option>';
                }
                echo '</optgroup>';
              }
            ?>
          </select>
        </form>
      </td>
      <td valign=top width="100%">
        <textarea style="width: 100%; height: 100%;"><?php
          // http://stackoverflow.com/questions/2996049/how-to-compress-decompress-a-long-query-string-in-php
          // $compressed   = rtrim(strtr(base64_encode(gzdeflate(json_encode($payload), 9)), '+/', '-_'), '=');
          // $uncompressed = gzinflate(base64_decode(strtr($compressed, '-_', '+/')));
          if( $format == 'raw' )
            print_r( $payload );
          else if( $format == 'json' )
            echo json_encode($payload);
          else
            echo rtrim(strtr(base64_encode(gzdeflate(json_encode($payload), 9)), '+/', '-_'), '=');
        ?></textarea>
      </td>
    </tr>
  </table>
