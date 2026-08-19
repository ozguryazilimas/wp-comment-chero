<?php
defined( 'ABSPATH' ) || exit;

/*
 * admin_notices action support for User Role Editor plugin
 *
 * Author: Vladimir Garagulya
 * Author email: support@role-editor.com
 * Author URI: https://role-editor.com
 */
 
class URE_Admin_Notice {
    
    public const ALLOWED_CLASSES = ['update', 'success', 'warning', 'error'];
    
    /* Message class: update, success, warning, error
     * @var string
     */
    private string $message_class;
    
    /**
     * The notice message text.
     *
     * @var string
     */
    private string $message;
    
    /* Constructor
     * @param string $message_class One of: update, success, warning, error.
     * @param string $message       The notice text.
     * @throws InvalidArgumentException If $message_class is not allowed.     
     */
    function __construct( string $message_class, string $message ) {
        
        if ( !in_array( $message_class, self::ALLOWED_CLASSES, true ) ) {
            throw new InvalidArgumentException(
                            sprintf(
                                    'Invalid message class "%s". Allowed values are: %s.',
                                    esc_html( $message_class ),
                                    esc_html( implode(', ', self::ALLOWED_CLASSES) )
                            )
            );
        }
        
        $this->message = $message;
        $this->message_class = $message_class;
        
    }
    // end of __construct()
    
    
    public function register(): void {
        
        add_action( 'admin_notices', [ $this, 'render' ] );
        
    }
    
    public function render() {
        
        printf('<div class="notice notice-%s is-dismissible"><p>%s</p></div>', 
               esc_attr( $this->message_class ), 
               wp_kses_post( $this->message ) 
              );
        
    }
    // end of render()
            
}
// end of class URE_Admin_Notice
