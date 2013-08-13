<?php
/*
 * Script to migrate from wp-unread-comments plugin to comment-chero
 *
 * You can delete the old wuc usermeta entries by setting $cleanup_query to true
 *
 *
 * Copyright (C) 2013, Onur Küçük <onur@ozguryazilim.com.tr>
 * Licensed under GNU GPLv2
 *
 */


$wp_header_file = dirname($_ENV['PWD']) . '/../../../wp-blog-header.php';
if (!file_exists($wp_header_file)) {
    exit("Could not find $wp_header_file. Plese run the script in WORDPRESS_ROOT/wp-content/plugins/comment-chero directory\n");
}

define('WP_USE_THEMES', false);
require_once($wp_header_file);

global $wpdb, $comment_chero_db_post_reads;
$wuckey = 'wuc_post_id';
$cleanup = false; // Cleanup old entries


$count_query = "SELECT count(*) FROM $wpdb->usermeta
                WHERE meta_key like '$wuckey%';";

$cleanup_query = "DELETE FROM $wpdb->usermeta
                  WHERE meta_key like '$wuckey%';";

$migrate_query = "INSERT INTO $comment_chero_db_post_reads (post_id, user_id, read_time)
                  SELECT
                        REPLACE(u.meta_key, '$wuckey', ''),
                        u.user_id,
                        u.meta_value
                  FROM $wpdb->usermeta u
                  WHERE
                        u.meta_key like '$wuckey%'
                        AND
                        u.meta_key != '$wuckey'
                  ORDER BY u.user_id
                  ON DUPLICATE KEY UPDATE read_time=u.meta_value;";


$workcount = $wpdb->get_var($count_query);

echo "\n";
echo "Migrating $workcount entries to $comment_chero_db_post_reads";
echo "\n";
$success = $wpdb->query($migrate_query);

if ($cleanup) {
    echo "Cleaning up old entries";
    echo "\n";
    $success = $wpdb->query($cleanup_query);
}

echo "...done";
echo "\n";

?>
