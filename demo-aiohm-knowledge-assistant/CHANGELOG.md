# Changelog

All notable changes to AIOHM Knowledge Assistant will be documented in this file.

## [1.2.4] - 2025-01-25

### 🔒 Security Fixes
- **CRITICAL**: Fixed broken API key encryption/decryption system that prevented AI provider connectivity
- **Fixed SQL injection vulnerability** in knowledge base manager queries
- **Enhanced error message security** to prevent sensitive information disclosure
- **Strengthened rate limiting** with IP-based protection to prevent bypass via logout/login
- **Improved file upload validation** with JSON content verification

### 🛠️ Code Quality Improvements
- **WordPress Coding Standards compliance** - Fixed all phpcs warnings for WordPress.org submission
- **Removed unnecessary prepared statements** for static SQL commands
- **Fixed table name interpolation** in database queries
- **Enhanced database schema migration** safety during plugin activation

### 🚨 Breaking Changes
- **API keys will need to be re-entered** due to encryption system fix
- Existing encrypted API keys will be cleared for security

### 📝 Technical Details
- Fixed `wp_generate_password()` usage in encryption that broke IV storage
- Replaced unsafe manual SQL escaping with proper prepared statements
- Added safe error message handler to prevent API error disclosure
- Enhanced rate limiting with dual user/IP tracking
- Updated all database queries to meet WordPress.org plugin review standards

## [1.2.0] - 2025-01-16

### 🚀 Added
- **Enhanced Content Extraction**: Intelligent fallback content generation for pages with minimal text
- **Shortcode Recognition**: Automatic detection and description of common WordPress shortcodes
- **Visual Progress Indicators**: Real-time progress bars with percentage completion for bulk operations
- **Detailed Error Handling**: Comprehensive error messages with specific failure reasons
- **Status Management**: Clear visual indicators (Ready to Add, Failed to Add, Knowledge Base)
- **API Key Validation**: Pre-processing checks to ensure API keys are configured before operations
- **Cache Management**: Improved cache handling with delays for real-time status updates
- **Batch Processing**: Enhanced bulk content processing with success/failure tracking

### 🔧 Improved
- **Content Processing**: Pages with only shortcodes (login, profile, etc.) now generate meaningful content
- **User Experience**: Better notifications and feedback messages throughout the interface
- **Error Recovery**: Failed items now show specific reasons and can be retried
- **Performance**: Optimized database queries and cache management
- **Accessibility**: Enhanced admin notices with proper ARIA attributes

### 🐛 Fixed
- **Empty Content Issue**: Pages with shortcodes no longer skipped as "empty content"
- **Silent Failures**: Knowledge base addition failures now properly reported
- **Cache Timing**: Fixed race conditions between cache clearing and status updates
- **Error Propagation**: Backend errors now properly reach the frontend interface
- **Status Accuracy**: Table status now reflects actual knowledge base state

### 🔒 Security
- **Enhanced Error Handling**: Detailed error information only shown to administrators
- **Input Validation**: Improved sanitization of user inputs
- **API Security**: Better handling of API key validation and storage

---

## [1.1.11] - 2024-12-XX

### 🔧 Fixed
- AI model selection persistence in settings
- Dashicons display issues in Muse interface
- Fullscreen mode functionality
- Sidebar menu styling improvements
- Delete button positioning issues
- Mirror mode send button animations
- Settings reflection between different modes
- WordPress admin menu icon design

### 🎨 Enhanced
- Plugin description with brand-focused messaging
- Progress save functionality in Brand Soul questionnaire
- Access control for private features
- Consistent OHM branding across all pages

---

## [1.1.0] - 2024-11-XX

### 🚀 Initial Public Release
- **Mirror Mode**: Public Q&A chatbot functionality
- **Muse Mode**: Private brand assistant for content creators
- **Multi-AI Support**: Integration with OpenAI, Claude, and Gemini
- **Content Scanning**: Automatic indexing of posts, pages, and media files
- **Brand Soul Questionnaire**: Voice training system for AI personality
- **Membership Integration**: Paid Memberships Pro compatibility
- **Shortcode System**: Easy embedding with `[aiohm_chat]` and `[aiohm_private_assistant]`
- **Knowledge Base Management**: Complete CRUD operations for content
- **Vector Search**: Semantic search capabilities using embeddings
- **Admin Interface**: Full WordPress admin integration with branded design

### 🔧 Technical Features
- Vector database with `wp_aiohm_vector_entries` table
- Conversation tracking and management
- Project-based content organization
- Usage tracking and analytics
- Scheduled content scanning
- API key management for multiple providers
- WordPress security compliance
- Proper uninstall cleanup

---

## Development Notes

### Version Numbering
- **Major.Minor.Patch** format (Semantic Versioning)
- Major: Breaking changes or significant new features
- Minor: New features, backward compatible
- Patch: Bug fixes, minor improvements

### Release Process
1. Update version numbers in all relevant files
2. Update README.md and readme.txt
3. Test on clean WordPress installation
4. Verify all shortcodes and features work
5. Check WordPress Coding Standards compliance
6. Create release package
7. Submit to WordPress.org repository

### Support Information
- **Minimum WordPress**: 5.8
- **Tested up to**: 6.7
- **Minimum PHP**: 7.4
- **Dependencies**: OpenAI/Claude/Gemini API keys
- **Optional**: Paid Memberships Pro for access control