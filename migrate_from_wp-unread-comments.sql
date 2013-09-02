
INSERT INTO wp_comment_chero_post_reads (post_id, user_id, read_time)
SELECT
      REPLACE(u.meta_key, 'wuc_post_id', ''),
      u.user_id,
      u.meta_value
FROM wp_usermeta u
WHERE
      u.meta_key like 'wuc_post_id%'
      AND
      u.meta_key != 'wuc_post_id'
ORDER BY u.user_id
ON DUPLICATE KEY UPDATE read_time=u.meta_value;

