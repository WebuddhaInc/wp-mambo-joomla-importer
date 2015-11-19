<?php

/**
 * Plugin Name: Mambo / Joomla to Wordpress Importer
 * Plugin URI: http://www.joomunited.com/wordpress-products/wp-meta-seo
 * Description: Migrate content from Mambo into Wordpress Posts, including Images, Categories, and Tags
 * Version: 2.0
 * Author: misterpah, webuddha
 * Author URI: http://www.misterpah.com
 * License: GPL2
 */

/**
 * Add Menu to Wordpress
 */
	add_action('admin_menu','MamboImporter_menu');

/**
 * Menu Handler
 */
	function MamboImporter_menu(){
		add_menu_page(
			'Mambo Importer',
			'Mambo Importer',
			'activate_plugins',
			'mamboImporter',
			'MamboImporter_process'
			);
	}

/**
 * Import Handler
 */
	function MamboImporter_process(){
		global $wpdb;

		// Params
			$ss_action = isset($_POST['ss_action']) ? (string)$_POST['ss_action'] : null;
			$ss_type   = isset($_POST['ss_type']) ? (string)$_POST['ss_type'] : 'post';
			$ss_media  = isset($_POST['ss_media']) ? (int)$_POST['ss_media'] : 1;

		// Render Option Form
			?>
			<h1>Mambo / Joomla Importer</h1>
			<p>Copy the code produced from the companion showArticle-*.php file, installed on your Mambo / Joomla installation and paste them into the form below.</p>
			<form action="" method="post" class="themeform">
				<input type="hidden" id="ss_action" name="ss_action" value="save">
				<div>
					<label for="ss_type">Import Target:</label>
					<select id="ss_type" name="ss_type">
						<option value="post">Post</option>
						<option value="page" <?php echo ($ss_type == 'page' ? 'selected' : '') ?>>Page</option>
					</select>
				</div>
				<div>
					<label for="ss_media">Import Media:</label>
					<select id="ss_media" name="ss_media">
						<option value="1">Post</option>
						<option value="0" <?php echo (!$ss_media ? 'selected' : '') ?>>Page</option>
					</select>
				</div>
				<div>
					<textarea style="width:100%;min-width:320px;height:420px;font-size:0.8em;border:1px solid #000000;background:#FFF4D3;color:#000000;" name="data_compressed"></textarea>
				</div>
				<input type="submit" value="Import data" name="cp_save"/>
			</form>
			<?php

		// Process
			if( $ss_action == 'save' ){
				$compressed_data = trim((string)@$_POST['data_compressed']);
				if( empty($compressed_data) ){
					echo "<b style='color:#ff0000;'>no data recieved. nothing to do.</b>";
				}
				else {
					$importedData = json_decode(gzinflate(base64_decode(strtr($compressed_data, '-_', '+/'))));
					if( empty($importedData) ){
						echo "<b style='color:#ff0000;'>Invalid Data Format</b>";
					}
					else {
						$post_count = 0;
						foreach( $importedData->content AS $post ){

							// Import Categories (POST Type Only)
								$post_category = null;
								if( $ss_type == 'post' ){
									$term_res = MamboImporter_AddPostCategoryHelper( $post->category );
									if( $term_res['chain'] ){
										$post_category = $term_res['chain'];
									}
								}

							// Prepare POST Dataset
								$post_tag = array_filter(array_map('trim', explode(',', $post->meta_keywords)), 'strlen');
								$post_data = array(
									'post_title'           => $post->post_title,
									'post_name'            => sanitize_title_with_dashes($post->post_name),
									'post_content'         => $post->post_content,
									'post_date'            => $post->post_date,
									'post_date_gmt'        => $post->post_date_gmt,
									'post_modified'        => $post->post_modified,
									'post_modified_gmt'    => $post->post_modified_gmt,
									'post_status'          => $post->post_status,
									'post_type'            => ($ss_type == 'post' ? 'post' : 'page'),
									'post_category'        => $post_category,
									'tax_input'            => (empty($post_tag) ? null : array('post_tag' => $post_tag)),
									'metaseo_wpmseo_title' => null,
									'metaseo_wpmseo_desc'  => $post->meta_description
								);

							// Check Duplicate Post
								$_post = $wpdb->get_row($wpdb->prepare("
									SELECT ID
									FROM {$wpdb->posts}
									WHERE post_title = %s
										AND post_name = %s
										AND post_type = %s
										AND post_date = %s
									LIMIT 1
									"
									, $post_data['post_title']
									, $post_data['post_name']
									, $post_data['post_type']
									, $post_data['post_date']
									));

							// Insert POST if new
								$isNew = $_post ? false : true;
								if( $isNew ){
									$post_id = wp_insert_post( $post_data );
								}
								else {
									$post_id = $_post->ID;
								}

							// Find / Import Images
								if( $post_id && $ss_media ){
									$post_data['ID'] = $post_id;
									$post_content = $post_data['post_content'];
									preg_match_all('!http://[a-z0-9\-\.\/]+\.(?:jpe?g|png|gif|bmp)!Ui', $post_content, $match_full);
									preg_match_all('!(?:"|\')(\/[a-z0-9\-\.\/]+\.(?:jpe?g|png|gif|bmp))!Ui', $post_content, $match_site);
									if( count($match_full) || count($match_site) ){
										$matches = array();
										if( count($match_full) ){
											$matches = $match_full[0];
										}
										if( count($match_site) ){
											foreach( $match_site[1] AS $partial ){
												$matches[] = $partial;
											}
										}
										foreach( $matches AS $file_source ){
											if( empty($media_cache[ $file_source ]) ){
												$filename = preg_replace('/^.*\/(.*?)/', '$1', preg_replace('/\?.*$/', '', $file_source));
												$file_title = str_replace('/', '-', preg_replace('/^.*?\/(.*?)/', '$1', preg_replace('/\?.*$/', '', $file_source)));
												$_media = $wpdb->get_row($wpdb->prepare("
													SELECT ID
													FROM {$wpdb->posts}
													WHERE post_title = %s
														AND post_type = %s
													LIMIT 1
													"
													, $file_title
													, 'attachment'
													));
												$media_id = $_media ? $_media->ID : 0;
												if( !$media_id ){
													$filepath = UPLOAD_BASE_DIR . '/' . $filename;
													$remote_file = strpos($file_source, '/') === 0 ? $importedData->site . $file_source : $file_source;
													if( @file_put_contents( $filepath, fopen($remote_file, 'r')) ){
														$file_data = array(
															'tmp_name' => $filepath,
															'name'     => $filename,
															'size'     => sizeof($filepath),
															'type'     => 'image/' . preg_replace('/^.*\.(.*?)$/', '$1', $filename)
															);
														$media_id = media_handle_sideload( $file_data, $post_data['ID'], $file_title );
													}
												}
												if( $media_id ){
													$media_post = get_post( $media_id );
													$media_meta = wp_get_attachment_metadata( $media_id );
													$media_cache[ $file_source ] = array(
														'id'    => $media_id,
														'post'  => $media_post,
														'meta'  => $media_meta,
														'image' => $media_post->guid,
														'thumb' => preg_replace('/^(.*\/).*$/', '$1', $media_post->guid) . $media_meta['sizes'][reset(array_keys($media_meta['sizes']))]['file']
														);
												}
												else {
													echo "<div style=color:red;><b>Download Failed: {$file_source}</b></div>";
												}
											}
											if( isset($media_cache[ $file_source ]) ){
												$post_content = str_replace( $file_source, $media_cache[ $file_source ]['image'], $post_content );
											}
										}
									}
									if( $post_content !== $post_data['post_content'] ){
										$post_data['post_content'] = $post_content;
										if( $isNew ){
											$res = $wpdb->query($wpdb->prepare("
												UPDATE {$wpdb->posts}
												SET post_content = %s
												WHERE ID = %d
												"
												, $post_data['post_content']
												, $post_data['ID']
												));
										}
										else {
											wp_update_post( $post_data );
										}
									}
								}

							// Increment Counter
								$post_count++;

						}
						echo "<div><b style='color:#0000FF;'>{$post_count} Records Imported. Have a nice day ! =)</b></div>";
					}
				}
			}

	}

/**
 * Import a Category Chain
 * @param [object] $category category object with optional parent object
 */
	function MamboImporter_AddPostCategoryHelper( $category ){
		$chain   = array();
		$term_id = -1;
		if( $category->parent ){
			$term_res = MamboImporter_AddPostCategoryHelper( $category->parent );
			if( empty($term_res['term_id']) ){
				return $term_res;
			}
			$chain   = $term_res['chain'];
			$term_id = $term_res['term_id'];
		}
		$term_res = wp_insert_term( $category->title, 'category', array(
			'slug'        => $category->slug,
			'description' => $category->description,
			'parent'      => $term_id
			));
		if( !$term_res ){
			$term_res = array(
				'errors' => array('unknown' => array('Unknown Error') )
				);
		}
		else if( $term_res instanceof WP_Error ){
			$term_res = array(
				'term_id' => $term_res->error_data['term_exists'],
				'errors'  => $term_res->errors
				);
		}
		if( $term_res['term_id'] ){
			$chain[] = $term_res['term_id'];
		}
		$term_res['chain'] = $chain;
		return $term_res;
	}
