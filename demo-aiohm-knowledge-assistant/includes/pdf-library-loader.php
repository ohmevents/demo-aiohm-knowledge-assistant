<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * AIOHM PDF Library Loader
 * 
 * Lightweight PDF library loader for conversation exports
 * WordPress.org compatible version using simple PDF generator
 */
class AIOHM_PDF_Library_Loader {
    
    private static $instance = null;
    private $pdf_loaded = false;
    
    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Private constructor to prevent direct instantiation
     */
    private function __construct() {
        // Constructor intentionally left empty
    }
    
    /**
     * Load PDF library - WordPress.org version uses simple generator
     * 
     * @return bool True if loaded successfully, false otherwise
     */
    public function load_mpdf() {
        if ($this->pdf_loaded) {
            return true;
        }
        
        // Check if mPDF is available from another plugin
        if (class_exists('Mpdf\\Mpdf')) {
            $this->pdf_loaded = true;
            return true;
        }
        
        // Load simple PDF generator
        $simple_pdf_path = AIOHM_KB_PLUGIN_DIR . 'includes/simple-pdf-generator.php';
        if (file_exists($simple_pdf_path)) {
            require_once $simple_pdf_path;
            $this->pdf_loaded = class_exists('AIOHM_Simple_PDF_Generator');
            return $this->pdf_loaded;
        }
        
        return false;
    }
    
    /**
     * Check if PDF generator is available
     * 
     * @return bool
     */
    public function is_mpdf_available() {
        return $this->pdf_loaded || class_exists('Mpdf\\Mpdf') || class_exists('AIOHM_Simple_PDF_Generator');
    }
    
    /**
     * Get PDF generator instance with error handling
     * 
     * @param array $config Configuration options
     * @return Mpdf\Mpdf|AIOHM_Simple_PDF_Generator|false
     */
    public function get_mpdf_instance($config = []) {
        if (!$this->load_mpdf()) {
            return false;
        }
        
        try {
            // Try to use mPDF first if available from another plugin
            if (class_exists('Mpdf\Mpdf')) {
                $default_config = [
                    'mode' => 'utf-8',
                    'format' => 'A4',
                    'orientation' => 'P',
                    'margin_left' => 15,
                    'margin_right' => 15,
                    'margin_top' => 25,
                    'margin_bottom' => 25,
                    'tempDir' => sys_get_temp_dir()
                ];
                
                $config = array_merge($default_config, $config);
                return new Mpdf\Mpdf($config);
            }
            
            // Use simple PDF generator
            $title = isset($config['title']) ? $config['title'] : 'AIOHM Document';
            return new AIOHM_Simple_PDF_Generator($title);
        } catch (Exception $e) {
            return false;
        }
    }
    
}