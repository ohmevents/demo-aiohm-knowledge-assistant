<?php
/**
 * Enhanced PDF class for conversation exports using Simple PDF Generator
 * WordPress.org compatible version
 */
if (!defined('ABSPATH')) exit;

// Only define the class if not already defined
if (!class_exists('AIOHM_Enhanced_PDF')) {
    class AIOHM_Enhanced_PDF {
        
        private $pdf_instance;
        
        public function __construct($orientation = 'P', $unit = 'mm', $size = 'A4') {
            // Load simple PDF generator
            require_once AIOHM_KB_PLUGIN_DIR . 'includes/simple-pdf-generator.php';
            
            // Create simple PDF instance
            $this->pdf_instance = new AIOHM_Simple_PDF_Generator('AIOHM Conversation Export');
        }
        /**
         * Add a chapter title
         */
        public function ChapterTitle($title) {
            $this->pdf_instance->ChapterTitle($title);
        }
        
        /**
         * Add a message block to the PDF
         */
        public function MessageBlock($sender, $content, $timestamp) {
            $this->pdf_instance->MessageBlock($sender, $content, $timestamp);
        }
        
        /**
         * Add a new page
         */
        public function AddPage($orientation = '') {
            $this->pdf_instance->AddPage($orientation);
        }
        
        /**
         * Output the PDF
         */
        public function Output($filename = '', $dest = 'I') {
            return $this->pdf_instance->Output($filename, $dest);
        }
        
        /**
         * Legacy method compatibility
         */
        public function SetFont($family, $style = '', $size = 0) {
            // Simple PDF Generator doesn't use this method - ignore
        }
        
        public function SetTextColor($r, $g = null, $b = null) {
            // Simple PDF Generator doesn't use this method - ignore
        }
        
        public function Cell($w, $h = 0, $txt = '', $border = 0, $ln = 0, $align = '', $fill = false, $link = '') {
            // Simple PDF Generator doesn't use this method - ignore
        }
        
        public function Ln($h = null) {
            // Simple PDF Generator doesn't use this method - ignore
        }
    }
}