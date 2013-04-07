<?php
/*
Plugin Name: LinkRobo
Plugin URI: http://www.searchtified.com/linkrobo
Tags: SEO, Posts, links, keywords, plugin, posts, seo links
Description: LinkRobo turns Keywords into SEO links. It helps create a successful website SEO promotion campaign at your own blog.
Version: 1.1
Author: ZmeyNet
Requires at least: 2.8.6
Stable tag: 1.1
Author URI: http://www.searchtified.com
License: GPL2
*/

/*  Copyright 2010-2011 ZmeyNet (email: zmeynet at gmail.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

define("LINKBOT_DEBUG", false);
define("LINKBOT_LIMIT", 10);
define("LINKBOT_LINKS_PER_CRON", 5);

function linkbot_install() {
	    global $wpdb;

      $sql = "
CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}tb_links` (
  `id` int(11) unsigned NOT NULL auto_increment,
  `created` timestamp NOT NULL default CURRENT_TIMESTAMP,
  `url` varchar(200) collate utf8_unicode_ci NOT NULL,
  `query` varchar(200) collate utf8_unicode_ci NOT NULL,
  `posts` text collate utf8_unicode_ci NOT NULL,
  `links` int(10) unsigned NOT NULL default '0',
  `last_check` datetime default NULL,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";

      require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
      dbDelta($sql);

      $query = "SELECT COUNT(*) as count FROM {$wpdb->prefix}tb_links";
      $count = $wpdb->get_var($query);
      if ($count==0) {
      	if (file_exists(dirname(__FILE__)."/links.xml")) {
      	$xml = simplexml_load_file(dirname(__FILE__)."/links.xml");
      		foreach ($xml->database->table as $columns){
      			$row = array();
      			foreach($columns->column as $column) {
      			$cattr = $column->attributes();
      			$row[(string)$cattr['name']] = html_entity_decode((string)$column, ENT_QUOTES);
      			}
      			$wpdb->query($wpdb->prepare("INSERT INTO {$wpdb->prefix}tb_links (`url`, `query`) VALUES (%s, %s)", $row["url"], $row["query"]));
   			}
   		}
      }

      if (file_exists(dirname(__FILE__)."/settings.xml")) {
      $xml = simplexml_load_file(dirname(__FILE__)."/settings.xml");
      	foreach ($xml->database->table as $columns){
      			$row = array();
      			foreach($columns->column as $column) {
      			$cattr = $column->attributes();
      			$row[(string)$cattr['name']] = html_entity_decode((string)$column, ENT_QUOTES);
      			}
      			add_option($row["option_name"], $row["option_value"], '', 'no');
   		}
      }

      wp_schedule_event(time(), 'hourly', 'linkbot_cron_event');
}

function linkbot_uninstall(){
	wp_clear_scheduled_hook('linkbot_cron_event');}

function linkbot_export_xml($sql){
	    global $wpdb;

		$xml = "";
		$xml_header = "<?xml version=\"1.0\" encoding=\"utf-8\"?>
<pma_xml_export version=\"1.0\">
<database name=\"translator\">";
		$xml_footer = "</database>
</pma_xml_export>";

		$rows = $wpdb->get_results($sql);

		if ($rows) {
		    	foreach($rows as $row) {
		    		    $xml .= "<table name=\"{$wpdb->prefix}tb_links\">\r\n";
		    		    foreach ($row as $key=>$value) {
		    		    	$xml .= "<column name=\"{$key}\">".htmlspecialchars($value)."</column>\r\n";
		    		    	}
		    		    $xml .= "</table>\r\n";
		    	}
		    }
		$rows_xml = $xml_header.$xml.$xml_footer;
		return $rows_xml;
}

function linkbot_menu() {
	global $wpdb;

	if ( is_admin() ) {
		if ($_POST['lbdelete']) {
			$sites = $_POST['sites'];
			if (is_array($sites)) {
				foreach($sites as $site) {
					$query = "DELETE FROM {$wpdb->prefix}tb_links WHERE id=".intval($site);
					$wpdb->query($query);
				}
			}
		}
		if ($_POST['lbdeletelinks']) {
			$sites = $_POST['sites'];
			if (is_array($sites)) {
				foreach($sites as $site) {
					$posts = $wpdb->get_var("SELECT posts FROM {$wpdb->prefix}tb_links WHERE id=".intval($site));
					if ($posts) {
						$sql = "UPDATE {$wpdb->prefix}tb_links SET posts = '', links = 0 WHERE id=".intval($site);
						$wpdb->query($sql);
					}
				}
			}
		}
		if ($_POST['lbcreate']) {
			$new_url = trim($_POST['lb_new_url']);
			$new_query = trim($_POST['lb_new_query']);
			if ($new_url && $new_query) {
				$query = "INSERT INTO {$wpdb->prefix}tb_links (`url`, `query`) VALUES ('".mysql_escape_string($new_url)."', '".mysql_escape_string($new_query)."')";
				$wpdb->query($query);
			}
		}
		if ($_POST['lbsavesettings']) {
			update_option("linkbot_use_new_posts", intval($_POST['setting_use_new_posts']));
			update_option("linkbot_use_old_posts", intval($_POST['setting_use_old_posts']));
			update_option("linkbot_links_per_day_for_new_posts", intval($_POST['setting_links_per_day_in_new_posts']));
			update_option("linkbot_links_per_day_for_old_posts", intval($_POST['setting_links_per_day_in_old_posts']));
		}

		if ($_POST['lbexport']) {

			require_once(dirname(__FILE__)."/classes/zip.class.php");
			$zip = new zipfile;
			$zip->create_file(file_get_contents(__FILE__), "linkrobo/linkrobo.php");
			$zip->create_file(file_get_contents(dirname(__FILE__)."/classes/zip.class.php"), "linkrobo/classes/zip.class.php");
			$zip->create_file(linkbot_export_xml("SELECT `url`, `query` FROM {$wpdb->prefix}tb_links ORDER BY `created`"), "linkrobo/links.xml");
			$zip->create_file(linkbot_export_xml("SELECT `option_name`, `option_value` FROM {$wpdb->prefix}options WHERE `option_name` LIKE 'linkbot_%'"), "linkrobo/settings.xml");
			header('Content-type: application/zip');
			header("Content-length: " . strlen($zip->zipped_file()));
			header('Content-Disposition: attachment; filename="linkrobo.zip"');
			nocache_headers();
			echo $zip->zipped_file();
			exit;
		}

	add_management_page( 'LinkBot Monitor', 'LinkBot Monitor', 5, 'linkbot', 'linkbot_monitor_page' );
	add_options_page( 'LinkBot Settings', 'LinkBot Settings', 5, 'linkbot_settings', 'linkbot_settings_page' );
	}

}

function linkbot_monitor_page() {
	global $wpdb;
	$items_per_page = 50;

	echo '<div class="wrap"><h2>Linkbot Monitor</h2><br/>';
	     echo '
<div>
<b>Menu:</b> Monitor | <a href="options-general.php?page=linkbot_settings">Settings</a> | <a href="http://www.searchtified.com/linkrobo/">Support</a><br/><br/>
</div>
	     ';
	echo '
	<div class="postbox">
	<div class="inside">
	<table class="form-table">
			<tr valign="top">

				<td>Today: '.date('Y-m-d H:i:s').'</td>

				<td>New links: '.get_option('linkbot_cron_links_per_day', 0).'</td>

				<td>For New Posts: '.get_option('linkbot_cron_links_per_day_for_new_posts', 0).'</td>

				<td>For Old Posts: '.get_option('linkbot_cron_links_per_day_for_old_posts', 0).'</td>
			</tr>
	</table>
	</div></div>
	';
echo '<div style="clear: both;"></div>';
	echo '
	<div class="postbox " style="width: 50%; float: left;">
	<div class="inside"><br/>
	<form name="lbcreatesite" action="" method="POST">
	&nbsp;URL: <input type="text" name="lb_new_url" value="" />
	Keyword: <input type="text" name="lb_new_query" value="" />
	<input type="submit" class="button-secondary action" name="lbcreate" value="Create"/>
		</form><br/>
	</div>
	</div>
	';
	echo '
	<form name="lbsearch" action="/wp-admin/tools.php?page=linkbot" method="POST">
	<p class="search-box">
	<label class="screen-reader-text" for="post-search-input">Search Posts:</label>
	&nbsp;&nbsp;&nbsp;URL: <input type="text" id="url-search-input" name="url-search-input" value="'.htmlspecialchars($_REQUEST['url-search-input']).'" /><br/>
	Keyword: <input type="text" id="query-search-input" name="query-search-input" value="'.htmlspecialchars($_REQUEST['query-search-input']).'" />
	<input type="submit" value="Search Posts" class="button" />
	</p>
	</form>
	';
	echo '<div class="clear"></div>
	<form name="lbsites" action="" method="POST">
<table class="widefat post fixed" cellspacing="0">
<thead>
	<tr>
	<th scope="col" id="cb" class="manage-column column-cb check-column" style=""><input type="checkbox" /></th>
	<th scope="col" id="created" class="manage-column column-created" style="">Created</th>
	<th scope="col" id="url" class="manage-column column-url" style="">Url</th>
	<th scope="col" id="query" class="manage-column column-query" style="">Keyword</th>
	<th scope="col" id="posts" class="manage-column column-posts" style="width: 30%;">Posts</th>
	<th scope="col" id="links" class="manage-column column-links" style="">Links</th>
	<th scope="col" id="last-check" class="manage-column column-last-check" style="">Last Check</th>
	</tr>
</thead>
	';
	$search = " WHERE 1=1 ";
	if ($_REQUEST['url-search-input']) $search .= " AND `url` LIKE '%".$wpdb->escape($_REQUEST['url-search-input'])."%'";
	if ($_REQUEST['query-search-input']) $search .= " AND `query` LIKE '%".$wpdb->escape($_REQUEST['query-search-input'])."%'";

	$page = intval($_GET['lbpage']);
	if ($page<1) $page = 1;
	$offset = ($page - 1)*$items_per_page;

	$query = "SELECT COUNT(*) as count FROM {$wpdb->prefix}tb_links {$search}";
	$count = $wpdb->get_var($query);
	$total_pages = ceil($count/$items_per_page);

	$query = "SELECT * FROM {$wpdb->prefix}tb_links {$search} ORDER BY created DESC LIMIT $offset,$items_per_page";
	$sites = $wpdb->get_results($query);
	if ($sites) {
		foreach ($sites as $site) {
			$posts_html_arr = array();			$acceptors = explode(",", $site->posts);
			if ($acceptors)
				foreach ($acceptors as $acceptor) if ($acceptor>0) $posts_html_arr[] = "<a title='".get_the_title($acceptor)."' href='".get_permalink($acceptor)."'>".substr(get_the_title($acceptor), 0, 50).((strlen(get_the_title($acceptor))>50)?"...":"")."</a>";
			echo '<tr><th scope="row" class="check-column"><input type="checkbox" name="sites[]" value="'.$site->id.'" /></th>';
			echo '<td>'.$site->created.'</td>';
			echo '<td>'.$site->url.'</td>';
			echo '<td>'.$site->query.'</td>';
			//echo '<td>'.preg_replace("|([0-9]+)|", "<a href='/wp-admin/post.php?post=\\1&action=edit'>\\1</a>", $site->posts).'</td>';
			echo '<td><ul><li>'.implode("</li><li>", $posts_html_arr).'</li></ul></td>';
			echo '<td>'.$site->links.'</td>';
			echo '<td>'.$site->last_check.'</td>';
			echo '</tr>';
		}
	}
	echo '</table>
	<div class="clear"></div><br/>
	<input type="submit" class="button-secondary action" name="lbdelete" value="Delete Keywords"/>&nbsp;<input type="submit" class="button-secondary action" name="lbdeletelinks" value="Delete Links"/>
	<div style="float: right;"><input type="submit" name="lbexport" class="button-primary" value="Export Plugin" /></div>
	<div class="clear"></div><br/>
	';
	echo '<div class="tablenav"><div class=\'tablenav-pages\'><span class="displaying-num">Displaying '.($offset+1).'&#8211;'.$items_per_page.' of '.$count.'</span>';
	for ($i=1; $i<=$total_pages; $i++) {
		if ($i==$page) echo "<span class='page-numbers current'>$i</span> ";
		else echo "<a class='page-numbers' href='?page=linkbot&lbpage=$i".($_REQUEST['url-search-input']?"&url-search-input=".urlencode($_REQUEST['url-search-input']):"").($_REQUEST['query-search-input']?"&query-search-input=".urlencode($_REQUEST['query-search-input']):"")."'>$i</a> ";
	}
	echo '
	</div></div>
	</form>
	</div>';
}

function linkbot_settings_page() {
	     global $wpdb;

	     echo '<div class="wrap"><br/><h2>Linkbot Settings</h2><br/><br/>';
	     echo '
<div>
<b>Menu:</b> <a href="tools.php?page=linkbot">Monitor</a> | Settings | <a href="http://www.searchtified.com/linkrobo/">Support</a><br/><br/>
</div>
	     ';
	     echo '
	     <form method="post" action="">
<input type="hidden" name="option_page" value="general" />
<input type="hidden" name="action" value="update" />
<input type="hidden" id="_wpnonce" name="_wpnonce" value="198537e122" />
<input type="hidden" name="_wp_http_referer" value="/wp-admin/options-general.php" />

	     <table class="form-table">
			<tr valign="top">
				<th scope="row"><label>Use new posts</label></th>
				<td><input type="checkbox" name="setting_use_new_posts" value="1" '.(get_option('linkbot_use_new_posts', '0')?"checked":"").' /></td>
			</tr>
			<tr valign="top">
				<th scope="row"><label>Use old posts</label></th>
				<td><input type="checkbox" name="setting_use_old_posts" value="1" '.(get_option('linkbot_use_old_posts', '0')?"checked":"").' /></td>
			</tr>
			<tr valign="top">
				<th colspan="2" scope="row"><strong><label>Links/day</label></strong></th>
			</tr>
			<tr valign="top">
				<th scope="row"><label>in new posts:</label></th>
				<td><input type="text" name="setting_links_per_day_in_new_posts" value="'.get_option('linkbot_links_per_day_for_new_posts', '0').'" /></td>
			</tr>
			<tr valign="top">
				<th scope="row"><label>in old posts:</label></th>
				<td><input type="text" name="setting_links_per_day_in_old_posts" value="'.get_option('linkbot_links_per_day_for_old_posts', '0').'" /></td>
			</tr>
		</table>
		<div class="clear"></div><br/>
	<input type="submit" name="lbsavesettings" class="button-primary" value="Save Changes" />
		</form>
		';
	     echo '</div>';
}

function linkbot_escape_query($query){
	global $wpdb;

	$query = str_replace(array("|",".","*","?","+","[","{","\\","^",'$',"("), array("\|","\.","\*","\?","\+","\[","\{","\\\\","\^",'\$',"\("), $query);

	return $query;

}

function linkbot_cron(){
	global $wpdb;

	if (strtotime(get_option('linkbot_cron_day_start', '0'))<time()-24*60*60) {
		update_option("linkbot_cron_day_start", time());
		update_option("linkbot_cron_links_per_day", '0');
		update_option("linkbot_cron_links_per_day_for_new_posts", '0');
		update_option("linkbot_cron_links_per_day_for_old_posts", '0');
	}

	$links_per_day = 0;
	$need_links_for_old_posts = true;
	$need_links_for_new_posts = true;
	if (get_option('linkbot_use_old_posts', '0') && get_option('linkbot_use_new_posts', '0')) {
		$links_per_day = intval(get_option('linkbot_links_per_day_for_new_posts', '0') + get_option('linkbot_links_per_day_for_old_posts', '0'));
		if (get_option('linkbot_links_per_day_for_new_posts', '0')>0 && get_option('linkbot_links_per_day_for_new_posts', '0')<=get_option('linkbot_cron_links_per_day_for_new_posts', '0'))
		$need_links_for_new_posts = false;
		if (get_option('linkbot_links_per_day_for_old_posts', '0')>0 && get_option('linkbot_links_per_day_for_old_posts', '0')<=get_option('linkbot_cron_links_per_day_for_old_posts', '0'))
		$need_links_for_old_posts = false;

	} elseif (get_option('linkbot_use_old_posts', '0')) {
		$links_per_day = intval(get_option('linkbot_links_per_day_for_old_posts', '0'));
		$need_links_for_new_posts = false;
		if (get_option('linkbot_links_per_day_for_old_posts', '0')>0 && get_option('linkbot_links_per_day_for_old_posts', '0')<=get_option('linkbot_cron_links_per_day_for_old_posts', '0'))
		$need_links_for_old_posts = false;

	} elseif (get_option('linkbot_use_new_posts', '0')) {
		$links_per_day = intval(get_option('linkbot_links_per_day_for_new_posts', '0'));
		$need_links_for_old_posts = false;
		if (get_option('linkbot_links_per_day_for_new_posts', '0')>0 && get_option('linkbot_links_per_day_for_new_posts', '0')<=get_option('linkbot_cron_links_per_day_for_new_posts', '0'))
		$need_links_for_new_posts = false;
		}

	if ($links_per_day<=0 || get_option('linkbot_cron_links_per_day', '0')<$links_per_day) $need_links = true;
	else $need_links = false;

    if (LINKBOT_DEBUG) echo "links_per_day:$links_per_day, need_links:".intval($need_links).", need_links_for_old_posts: ".intval($need_links_for_old_posts).", need_links_for_new_posts: ".intval($need_links_for_new_posts)."<hr/>";

	if ($need_links && ($need_links_for_old_posts || $need_links_for_new_posts)) {

	for ($i=0; $i<LINKBOT_LIMIT; $i++) {

		$sql = "SELECT * FROM {$wpdb->prefix}tb_links WHERE 1=1 ORDER BY last_check LIMIT 1";
		if (LINKBOT_DEBUG) echo $sql."<hr/>";
		$url = $wpdb->get_results($sql, ARRAY_A);
		if ($url) {
			$url = $url[0];

			$limit = LINKBOT_LINKS_PER_CRON;

			$base_sql = "SELECT * FROM {$wpdb->prefix}posts WHERE ".(($url["posts"])?" ID NOT IN (".mysql_escape_string($url["posts"]).") AND " : "")." post_type='post' AND post_status='publish' AND post_content LIKE '%".mysql_escape_string($url['query'])."%'";
            $sql = $base_sql." ORDER BY RAND() LIMIT ".$limit;

			if ($need_links_for_old_posts && $need_links_for_new_posts) {
			$sql = $base_sql." ORDER BY RAND() LIMIT ".$limit;
			} elseif ($need_links_for_old_posts) {
			$sql = $base_sql." AND `post_date`<'".mysql_escape_string($url["created"])."' ORDER BY RAND() LIMIT ".$limit;
			} elseif ($need_links_for_new_posts) {
			$sql = $base_sql." AND `post_date`>='".mysql_escape_string($url["created"])."' ORDER BY RAND() LIMIT ".$limit;
			}

			if (LINKBOT_DEBUG) echo $sql."<hr/>";

			$posts = $wpdb->get_results($sql, ARRAY_A);

			if($posts) {
				foreach($posts as $post) {
				$query = linkbot_escape_query($url["query"]);
					if (preg_match("|[^a-z0-9<>]+(".$query.")[^a-z0-9<>]+|i", $post["post_content"])) {
						$links = array();
						if ($url["posts"]) $links = explode(",", $url["posts"]);
						$links[] = $post["ID"];
						$url["posts"] = implode(",",$links);

						$sql = "UPDATE {$wpdb->prefix}tb_links SET posts = '".mysql_escape_string(implode(",",$links))."', links = links + 1 WHERE id={$url['id']}";
						if (LINKBOT_DEBUG) echo $sql."<hr/>";
						$wpdb->query($sql);

						if (strtotime($post["post_date"])<strtotime($url["created"])) {
						update_option("linkbot_cron_links_per_day", get_option('linkbot_cron_links_per_day', '0')+1);
						update_option("linkbot_cron_links_per_day_for_old_posts", get_option('linkbot_cron_links_per_day_for_old_posts', '0')+1);
						} elseif (strtotime($post["post_date"])>=strtotime($url["created"])) {
						update_option("linkbot_cron_links_per_day", get_option('linkbot_cron_links_per_day', '0')+1);
						update_option("linkbot_cron_links_per_day_for_new_posts", get_option('linkbot_cron_links_per_day_for_new_posts', '0')+1);
						}
					}
				}
			}

			$sql = "UPDATE {$wpdb->prefix}tb_links SET last_check = '".date('Y-m-d H:i:s')."' WHERE id={$url['id']}";
			if (LINKBOT_DEBUG) echo $sql."<hr/>";
			$wpdb->query($sql);
		}

	}

	}
}

function linkrobo_plugin_settings( $links ) {
	$settings_link = '<a href="options-general.php?page=linkbot_settings">'.__('Settings').'</a>';
	array_unshift( $links, $settings_link );
	return $links;
}

function linkrobo_plugin_add_settings($links, $file) {
	if ( $file == basename( dirname( __FILE__ ) ).'/'.basename( __FILE__ ) ) {		$links[] = '<a href="tools.php?page=linkbot">' . __('Monitor') . '</a>';
		$links[] = '<a href="options-general.php?page=linkbot_settings">' . __('Settings') . '</a>';
		$links[] = '<a href="http://www.searchtified.com/linkrobo/">' . __('Support') . '</a>';
	}

	return $links;
}

add_action('admin_menu', 'linkbot_menu');
register_activation_hook(__FILE__,'linkbot_install');
add_action('linkbot_cron_event', 'linkbot_cron');
register_deactivation_hook(__FILE__, 'linkbot_uninstall');
add_action('plugin_action_links_'.basename( dirname( __FILE__ ) ).'/'.basename( __FILE__ ), 'linkrobo_plugin_settings', 10, 4 );
add_filter('plugin_row_meta', 'linkrobo_plugin_add_settings', 10, 2 );

function linkrobo_keyword2link_filter($content) {	global $wpdb, $post;

	$patterns = array(
	"|<\s*a[^>]*>.*</a\s*>|Usi",
	"|<\s*style[^>]*>.*</style\s*>|Usi",
	"|<\s*script[^>]*>.*</script\s*>|Usi",
	);

	$id = intval($post->ID);
	$sql = "SELECT * FROM {$wpdb->prefix}tb_links WHERE posts LIKE '$id' OR posts LIKE '%,$id' OR posts LIKE '$id,%' OR posts LIKE '%,$id,%'";
	$links = $wpdb->get_results($sql, ARRAY_A);
	if ($links) {		foreach ($links as $link) {			$query = $link['query'];
			$query_pattern = "|([^a-z0-9<>]+)(".$query.")([^a-z0-9<>]+)|i";
			$try = 0;			while(preg_match($query_pattern, $content)) {				$try++;
				$good_replacement = false;
				$content1 = preg_replace($query_pattern, "\\1xLINKROBO_STAMPx\\3", $content, 1);
				$content1 = preg_replace($patterns, "", $content1);
				$content1 = strip_tags($content1);
				if (strpos($content1, "xLINKROBO_STAMPx")!==false) $good_replacement = true;
				if ($good_replacement) {					$content = preg_replace($query_pattern, "\\1<a href=\"{$link['url']}\">\\2</a>\\3", $content, 1);
					break;
					}
				if ($try>=15) break;			}
		}	}	return $content;}

add_filter('the_content', 'linkrobo_keyword2link_filter');

?>