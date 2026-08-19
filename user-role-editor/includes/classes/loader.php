<?php
/*
 * Project: User Role Editor
 * According to the project list of files: 
 * - verifies file existence, 
 * - optionally checks for class existence,
 * - loads class from a file,
 * - verifies the class after loading
 * Author: Vladimir Garagulya
 * Author email: support@role-editor.com
 * Author URI: https://www.role-editor.com
 * License: GPL v2+
 */

defined( 'ABSPATH' ) || exit;

class URE_Loader {
    
    // Array of file definitions: URE_File_Definition objects
    private static ?array $file_defs = null;
    
    // Array of classes already processed. Used to check unique class names inside self::$defs
    private static ?array $seen_classes = null;

    private static $logger = null; // Allow injection
    
    /**
     * Set custom logger (for testing purpose)
     */
    public static function set_logger(?callable $callback): void {
        
        if ( is_callable( $callback) ) {
            self::$logger = $callback;
        }
        
    }
    
    private static function log(string $message): void {
        if ( self::$logger!==null ) {
            $callback = self::$logger;
            if ( is_callable( $callback) ) {
                call_user_func( self::$logger, $message );
            }
            return;
        }
        
        ure_log_error( '[URE Loader] ' . $message );
    }

    /**
     * Loads a file if the specified class does not yet exist.
     *
     * @param string $file        Absolute path to the PHP file.
     * @param string|null $class_name Name of the class that should be declared in the file.
     *                                If null, the file is loaded without class existence check
     *                                (used for files with constants, functions, or multiple classes).
     * @return bool True if the file was loaded; false if the file does not exist or the class already exists.
     */
    public static function load_file( string $file, ?string $class_name = null ): bool {
        if ( empty( $file ) || !file_exists( $file ) ) {
            self::log( 'File '. $file .' does not exist');
            return false;
        }

        // If a class name is provided, check if it already exists
        if ( $class_name !== null ) {
            if ( class_exists( $class_name, false ) ) {
                self::log( 'Class '. $class_name .' loaded already.');
                return false;
            }

            // Load the file
            require $file;

            // Verify that the class actually exists after loading
            if ( ! class_exists( $class_name, false ) ) {
                 self::log( 'File '. $file .' loaded, but class '. $class_name .' not found.' );
                 return false;
            }

            return true;
        }

        // Case: $class_name is null → file without a single target class
        // We just require_once and assume success (no class verification)
        require_once $file;
        
        return true;
    }


    /*
     * Add Loader definition to the list of definitions
     */
    public static function add_def( string $file_name, ?string $class_name ) {
        if ( self::$file_defs===null ) {
            self::$file_defs = [];
        }
        if ( self::$seen_classes===null ) {
            self::$seen_classes = [];
        }
        
        if ( $class_name === null ) {
            self::$file_defs[] = new URE_File_Definition( $file_name, null );
            return;
        }

        if ( isset( self::$seen_classes[$class_name] ) ) {
            self::log('Class '. $class_name .' added to definitions list already.' );
            return;
        }

        self::$seen_classes[$class_name] = true;
        self::$file_defs[] = new URE_File_Definition( $file_name, $class_name );
        
    }
    // end of add_def()
    
    
    /**
     * Returns a list of URE_File_Definition objects : files and their corresponding classes.
     * For files without a specific class (e.g., define-constants.php), $class_name is null.
     */
    public static function get_file_defs(): array {
                                
        return self::$file_defs;
    }

    /**
     * Loads all files from the list.
     * Uses [file, class] pairs and calls load_file with the explicit class name (or null).
     */
    public static function load_files(): void {
        
        $file_defs = self::get_file_defs();
        if ( $file_defs === null || !is_array( $file_defs )  || count( $file_defs )=== 0 ) {
            return;
        }
        
        foreach ( $file_defs as $file_def ) {
            self::load_file( $file_def->file_name, $file_def->class_name );
        }
        
    }
    
}
// end of URE_Loader class


/*
 * File definition class
 */
final class URE_File_Definition {
    // Full path to file, like /home/site/wp-content/plugins/includes/classes/admin-notice.php
    public string $file_name;
    // Class name, like URE_Admin_Notice
    public ?string $class_name;
    
    public function __construct( string $file_name, ?string $class_name ) {
        $this->file_name = $file_name;
        $this->class_name = $class_name;
    }
}
// end of URE_File_Definition class