<div class="wrap">
    <h2><?php esc_html_e( 'Generate Featured images for posts', 'apt' ) ?></h2>
    <div class="factory-bootstrap-426 factory-fontawesome-000">
        <div class="row">
            <div class="col-md-9">

                <div class="row wrap apt-filter-row">
                    <?php
                    if(auto_post_thumbnails()->is_premium())
                        do_action( 'wapt/filter_form_print');
                    else {
	                    echo '<div class="col-md-12">';
	                    echo '<a target="_blank" href="'.WAPT_Plugin::app()->get_support()->get_pricing_url( true, 'license_page' ).'"><img src="'.WAPT_PLUGIN_URL.'/admin/assets/img/premium_filter.png"></a><br />';
	                    printf( __( 'Advanced filter form available in <a href="%s">Premium version</a>', 'aptp' ), WAPT_Plugin::app()->get_support()->get_pricing_url( true, 'license_page' ) );
	                    echo '</div>';
                    }
                    ?>
                    <div class="col-md-12">&nbsp;</div>

                    <div class="col-md-12">
                        <button class="button button-primary button-large hide-if-no-js" name="generate-post-thumbnails" id="generate-post-thumbnails">
		                    <?php esc_attr_e( 'Generate Featured images', 'apt' ) ?>
                        </button>&nbsp;
                        <button class="button button-danger button-large hide-if-no-js" name="delete-post-thumbnails" id="delete-post-thumbnails">
		                    <?php esc_attr_e( 'Delete Featured images', 'apt' ) ?>
                        </button>
                    </div>

                    <div class="col-md-12">&nbsp;</div>

                    <div class="col-md-12">
                        <div id="genpostthumbsbar" style="position:relative;height:40px;display: none;">
                            <div id="genpostthumbsbar-percent"
                                 style="position:absolute;left:50%;top:50%;margin-left:-25px;height:25px;margin-top:-9px;font-weight:bold;text-align:center;"></div>
                        </div>
                    </div>
                </div>
                <div class="wrap genpostthumbs">
                    <p>
                    <div id="message" class="updated fade" style="display:none"></div>
                    </p>
                    <p><?php _e( 'Note: Thumbnails won\'t be generated for posts that already have post thumbnail or <strong><em>skip_post_thumb</em></strong> custom meta field.', 'apt' ) ?></p>
                    <noscript><p>
                            <em><?php esc_html_e( 'You must enable Javascript in order to proceed!', 'apt' ) ?></em>
                        </p>
                    </noscript>
                    <!-- esc_html_e( 'We are generating post thumbnails. Please be patient!', 'apt' ); -->
                    <script type="text/javascript">
						// <![CDATA[
						jQuery(document).ready(function($) {
                            jQuery('#generate-post-thumbnails').on('click', function(event) {
                                rt_images = [];

                                $("#generate-post-thumbnails").attr('disabled','');
                                $("#delete-post-thumbnails").attr('disabled','');
                                $("#message").hide();
                                $("#genpostthumbsbar").show();
                                $("#genpostthumbsbar").progressbar();
                                $("#genpostthumbsbar-percent").html("1%");

                                $.post("admin-ajax.php", {
                                    action: "get-posts-ids",
                                    withThumb: 0,
                                    <?php
	                                if(auto_post_thumbnails()->is_premium()) { ?>
                                    poststatus: $("#filter_poststatus").val(),
                                    posttype: $("#filter_posttype").val(),
                                    date_start: $("#filter_startdate").val(),
                                    date_end: $("#filter_enddate").val(),
                                    category: $("#filter_postcategory").val(),
	                                <?php } ?>
                                    _ajax_nonce: '<?php echo wp_create_nonce( 'get-posts' ); ?>'
                                }, function(ids) {
                                    rt_images = JSON.parse("[" + ids + "]");

                                    var rt_total = rt_images.length;
                                    var rt_count = 1;
                                    var rt_percent = 0;
                                    var posted_count = 0;

                                    function genPostThumb(id) {
                                        $.post("admin-ajax.php", {
                                            action: "generatepostthumbnail",
                                            id: id,
                                            _ajax_nonce: '<?php echo wp_create_nonce( 'generate-post-thumbnails' ); ?>'
                                        }, function(posted) {
                                            if( Number(posted) !== 0 ) {
                                                posted_count++;
                                            }
                                            rt_percent = (rt_count / rt_total) * 100;
                                            $("#genpostthumbsbar").progressbar("value", rt_percent);
                                            $("#genpostthumbsbar-percent").html(Math.round(rt_percent) + "% ("+ rt_count + "/" + rt_total +")");
                                            rt_count = rt_count + 1;

                                            if( rt_images.length ) {
                                                genPostThumb(rt_images.shift());
                                            } else {
                                                setTimeout(function(){
                                                    $("#genpostthumbsbar").hide();
                                                    $("#genpostthumbsbar").progressbar("value", 0);
                                                    $("#generate-post-thumbnails").removeAttr('disabled');
                                                    $("#delete-post-thumbnails").removeAttr('disabled');
                                                    $("#message").html("<p><strong><?php echo esc_html__( 'All done! Processed posts:', 'apt' ); ?> " + rt_total + "<br><?php echo esc_html__( 'Set featured image in posts:', 'apt' ); ?> " + posted_count + "</strong></p>");
                                                    $("#message").show();
                                                }, 500);
                                            }
                                        });
                                    }

                                    genPostThumb(rt_images.shift());
                                });
                            });
                            //delete thumbnails
                            jQuery('#delete-post-thumbnails').on('click', function(event) {
                                if(!confirm('Are sure to delete thumbnails from posts?'))
                                    return;

                                rt_images = [];

                                $("#generate-post-thumbnails").hide();
                                $("#delete-post-thumbnails").hide();
                                $("#genpostthumbsbar").show();
                                $("#genpostthumbsbar").progressbar();
                                $("#genpostthumbsbar-percent").html("1%");

                                $.post("admin-ajax.php", {
                                    action: "get-posts-ids",
                                    withThumb: 1,
	                                <?php
	                                if(auto_post_thumbnails()->is_premium()) { ?>
                                    poststatus: $("#filter_poststatus").val(),
                                    posttype: $("#filter_posttype").val(),
                                    date_start: $("#filter_startdate").val(),
                                    date_end: $("#filter_enddate").val(),
                                    category: $("#filter_postcategory").val(),
                                    <?php } ?>
                                    _ajax_nonce: '<?php echo wp_create_nonce( 'get-posts' ); ?>'
                                }, function(ids) {
                                    rt_images = JSON.parse("[" + ids + "]");

                                    var rt_total = rt_images.length;
                                    var rt_count = 1;
                                    var rt_percent = 0;
                                    var posted_count = 0;

                                    function delPostThumb(id) {
                                        $.post("admin-ajax.php", {
                                            action: "delete_post_thumbnails",
                                            id: id,
                                            _ajax_nonce: '<?php echo wp_create_nonce( 'delete-post-thumbnails' ); ?>'
                                        }, function(posted) {
                                            if( Boolean(posted) ) {
                                                posted_count++;
                                            }
                                            rt_percent = (rt_count / rt_total) * 100;
                                            $("#genpostthumbsbar").progressbar("value", rt_percent);
                                            $("#genpostthumbsbar-percent").html(Math.round(rt_percent) + "%");
                                            rt_count = rt_count + 1;

                                            if( rt_images.length ) {
                                                delPostThumb(rt_images.shift());
                                            } else {
                                                $("#genpostthumbsbar").hide();
                                                $("#generate-post-thumbnails").removeAttr('disabled');
                                                $("#delete-post-thumbnails").removeAttr('disabled');
                                                $("#message").html("<p><strong><?php echo esc_html__( 'All done! Processed posts:', 'apt' ); ?> " + rt_total + "<br><?php echo esc_html__( 'Delete featured image in posts:', 'apt' ); ?> " + posted_count + "</strong></p>");
                                                $("#message").show();
                                            }
                                        });
                                    }

                                    delPostThumb(rt_images.shift());
                                });
                            });
						});
						// ]]>
                    </script>
                </div>
            </div>
            <div class="col-md-3">
                <div style="padding:20px">
					<?php WAPT_Plugin::app()->get_adverts_manager()->render_placement( 'right_sidebar' ); ?>
                </div>
                <div id="wbcr-clr-support-widget" class="wbcr-factory-sidebar-widget">
                    <p><strong>Having Issues?</strong></p>
                    <div class="wbcr-clr-support-widget-body">
                        <p>
                            We provide free support for this plugin. If you are pushed with a problem, just create a new
                            ticket.
                            We will definitely help you! </p>
                        <ul>
                            <li><span class="dashicons dashicons-sos"></span>
                                <a href="https://forum.webcraftic.com" target="_blank" rel="noopener">Get starting free
                                    support</a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>