=== Comment Chero ===
Contributors:
Donate link:
Tags: chero, comment chero, comments, unread comments, unread comments widgets, wordpress unread comments, view unread comments
Requires at least: 4.3.0
Tested up to: 4.9.6
Stable tag: 1.3.0

This plugin displays unread comments in a sidebar widget and can highlight unread comments in comment lists.


== Description ==

This plugin displays unread comments in a sidebar widget and can highlight
unread comments in comment lists. Also tracks which comments logged in users
has seen, and highlights them for each user. Developed on systems with tens of
thousands of posts, optimized for speed and usability.


== Installation ==

For WordPress 4.+:

1. Download the last version of the plugin.
2. Put the files in plugins folder in your Wordpress site.
3. Activate the plugin through the Plugins menu of the WordPress administrator.
4. Pull the widget named Comment Chero into your sidebar.

This plug needs to keep track of when users last read comments. To activate it
you simply need to add 2 lines to your theme / template.

In your templates comments.php file, add the following line at the top of the file:
`<?php do_action('comment_chero_get_time');?>`
In your templates comments.php file, add the following line at the end of the file:
`<?php do_action('comment_chero_set_time');?>`


If you find that the unread list in the sidebar doesn't clear until you reload
the page, or you don't want to modify your theme, you can take the following steps for a more fine-tuned updating of reader times.
Find the plug-in file comment-chero.php and comment out the lines

`add_action('get_header', 'comment_chero_get_time');`
`add_action('get_footer', 'comment_chero_set_time');`


== Migrating from wp-unread-comments ==

If you are already using wp-unread-comments and want to switch to comment-chero, you can migrate your old data. Just go to plugins directory and run.
Make sure you backup your data before running this script.

`php migrate_from_wp-unread-comments.php`

If you want to keep the old data from wp-unread-comments just set "$cleanup = false" in the migration script.


== Frequently Asked Questions ==

= What are the options for this plug in? =

1. Title: This is what appears when a user is logged in.  Default: Comment Chero
2. Number of unread comments to show:  This limits how many posts with latest comments are displayed at a time when a user is logged in.
3. Highlight Unread Comments: If you check this on, unread comments will
appear in the readers list with a background color to set them apart from the
read comments. Once a user has read those comments, they will appear as your
template color. The color for unread comments is set in
/css/comment-chero.css. You may need to clear your caches to see the changes
if you modify CSS files.

