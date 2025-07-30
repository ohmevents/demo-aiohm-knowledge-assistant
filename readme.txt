=== AIOHM Knowledge Assistant ===
Contributors: ohm-events-agency
Tags: ai assistant, knowledge base, chatbot, brand voice, personalized ai
Requires at least: 6.2
Tested up to: 6.8
Stable tag: 1.2.9
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Transform your WordPress site into an intelligent AI assistant with dual-mode knowledge base. Create public chatbots and private AI assistants.

== Description ==

**AIOHM Knowledge Assistant** is the first WordPress plugin to offer true dual-mode AI functionality. Create a public-facing chatbot that embodies your brand voice while maintaining a completely separate private AI assistant for your personal creative workflow.

### 🎯 **Why AIOHM is Different**

**Dual-Mode Architecture**: Unlike generic chatbot plugins, AIOHM provides two distinct AI personalities:

* **Mirror Mode** - Your public brand ambassador trained on approved content
* **Muse Mode** - Your private creative partner with access to confidential materials

**Voice-Aligned Intelligence**: AIOHM doesn't just answer questions - it captures and reflects your unique communication style, creating responses that sound authentically *you*.

**RAG-Powered Accuracy**: Advanced Retrieval-Augmented Generation ensures responses are grounded in your actual content, not generic AI training data.

### 🚀 **Core Features**

**📚 Intelligent Knowledge Base**
* Automatic content indexing from pages, posts, and uploads
* Support for PDF, CSV, TXT, and JSON files
* Vector search with semantic understanding
* Full-text search with relevance ranking
* Smart content chunking with configurable overlap

**🤖 Multi-Provider AI Support**
* OpenAI GPT models (GPT-3.5, GPT-4)
* Google Gemini Pro with embeddings
* Anthropic Claude models
* Ollama for self-hosted privacy
* ShareAI for specialized models

**🎨 Brand Voice Training**
* Brand Soul questionnaire system
* Tone and personality configuration
* Custom system prompts and temperature controls
* Voice consistency across all interactions

**🔒 Privacy & Security**
* Encrypted API key storage
* Rate limiting (100 requests/hour per user + IP)
* Membership-based access control
* Private knowledge segregation
* GDPR-compliant data handling

**⚡ Performance Optimized**
* WordPress object cache integration
* Smart caching with automatic invalidation
* Minimal server resource usage
* CDN-friendly static assets

### 🎯 **Perfect For**

**🎓 Coaches & Consultants**
Replace repetitive client questions with an AI that understands your methodology and speaks with your authority.

**📝 Content Creators**
Get writing assistance that matches your style while maintaining a public assistant for your audience.

**🏢 Professional Services**
Provide 24/7 client support while keeping internal processes and sensitive information completely private.

**🛍️ E-commerce**
Answer product questions instantly while using private mode for inventory management and strategy.

**🎨 Creative Agencies**
Client-facing project information with private creative briefs and internal workflows.

### 🛠️ **How It Works**

**Setup in Minutes:**
1. Install and activate the plugin
2. Add your AI provider API key (OpenAI, Claude, or Gemini)
3. Run content scan to build your knowledge base
4. Configure your brand voice and tone
5. Add shortcodes where you want AI functionality

**Three Powerful Shortcodes:**
* `[aiohm_chat]` - Public chatbot for website visitors
* `[aiohm_search]` - Knowledge base search interface
* `[aiohm_private_assistant]` - Private AI assistant (members only)

**Smart Content Processing:**
* Automatically detects and indexes new content
* Processes WordPress shortcodes intelligently
* Extracts meaningful content from complex layouts
* Maintains context and relationships between content

### 🔐 **Privacy-First Design**

**Private Mode Benefits:**
* Confidential documents never accessible to public users
* Separate AI training for internal vs. external use
* Membership integration with Paid Memberships Pro
* Full control over what information is shared

**Data Security:**
* API keys encrypted with WordPress security constants
* All database queries use prepared statements
* Input sanitization and output escaping throughout
* No data stored on external servers (except AI provider APIs)

### 🎨 **Customization Options**

**Visual Integration:**
* CSS customization for perfect theme matching
* Responsive design works on all devices
* Customizable chat bubble colors and positioning
* Brand logo and styling integration

**Behavioral Control:**
* Adjustable response temperature and creativity
* Custom fallback messages
* Conversation flow management
* Response length and format control

**Advanced Settings:**
* Chunk size optimization for your content type
* Vector search sensitivity tuning
* Rate limiting customization
* Cache duration configuration

### 🔗 **Integrations**

**MCP (Model Context Protocol) API** (Private Members Only):
* Share knowledge base with external AI assistants
* Standardized endpoints for cross-platform compatibility
* Token-based authentication with granular permissions
* Connect to Claude Desktop, other WordPress sites, mobile apps
* API rate limiting and usage monitoring
* Private members automatically get Club and Tribal level access

**Membership Systems:**
* Paid Memberships Pro (restrict private assistant access)
* Custom user role support
* Granular permission controls

**AI Providers:**
* OpenAI (GPT-3.5-turbo, GPT-4, text-embedding-ada-002)
* Google Gemini (gemini-pro, text-embedding-004)
* Anthropic Claude (claude-3-sonnet, claude-3-haiku)
* Ollama (self-hosted models for maximum privacy)

**Content Types:**
* WordPress posts and pages
* Custom post types
* PDF documents with text extraction
* CSV data files
* JSON structured data
* Plain text files

### 📊 **Analytics & Insights**

**Usage Tracking:**
* Conversation analytics and popular questions
* Response accuracy monitoring
* User engagement metrics
* Content performance insights

**Optimization Tools:**
* Identify content gaps in your knowledge base
* Monitor AI response quality
* Track user satisfaction and engagement
* Performance optimization recommendations

### 🌐 **Multilingual Support**

* Full internationalization support
* Translation-ready with .pot files
* RTL language support
* Custom language configurations

== Installation ==

### Automatic Installation

1. Log in to your WordPress admin dashboard
2. Navigate to **Plugins > Add New**
3. Search for "AIOHM Knowledge Assistant"
4. Click **Install Now** and then **Activate**

### Manual Installation

1. Download the plugin zip file
2. Upload to `/wp-content/plugins/aiohm-knowledge-assistant/`
3. Activate the plugin through the **Plugins** menu in WordPress

### First-Time Setup

1. **Configure AI Provider**:
   - Go to **AIOHM > Settings**
   - Add your OpenAI, Claude, or Gemini API key
   - Test the connection

2. **Build Knowledge Base**:
   - Navigate to **AIOHM > Dashboard**
   - Click **Scan Website** to index your content
   - Upload additional files if needed

3. **Configure Brand Voice**:
   - Complete the **Brand Soul** questionnaire
   - Adjust Mirror Mode settings for public use
   - Configure Muse Mode for private assistance

4. **Add to Your Site**:
   - Use `[aiohm_chat]` for public chatbot
   - Use `[aiohm_search]` for knowledge base search
   - Use `[aiohm_private_assistant]` for private AI (requires user login)

== Frequently Asked Questions ==

= What AI providers are supported? =

AIOHM supports OpenAI (GPT-3.5, GPT-4), Google Gemini Pro, Anthropic Claude, and Ollama for self-hosted models. You only need one API key to get started.

= How is this different from other chatbot plugins? =

AIOHM is the only WordPress plugin offering true dual-mode functionality: a public chatbot trained on your approved content AND a private AI assistant with access to confidential materials. Plus, it captures and reflects your actual writing style.

= Do I need coding skills? =

None at all! Everything is managed through your WordPress dashboard with simple point-and-click interfaces. The most technical thing you'll do is copy-paste a shortcode.

= Will this slow down my website? =

No. AI processing happens on external servers (OpenAI, Claude, etc.), not your website. The plugin is optimized for performance with smart caching and minimal resource usage.

= How does the privacy mode work? =

Private mode content is completely separate from public content. Only logged-in users with proper permissions can access private AI features. Your confidential documents are never visible to public users.

= Can I customize the appearance? =

Yes! The plugin includes comprehensive CSS customization options, supports theme integration, and provides responsive design that works on all devices.

= What file types are supported? =

WordPress posts/pages, PDF documents, CSV files, TXT files, and JSON data. The plugin intelligently extracts and processes content from each format.

= Is my data secure? =

Absolutely. API keys are encrypted, all database operations use prepared statements, and the plugin follows WordPress security best practices. Your data stays on your server except when sent to AI providers for processing.

= Can I restrict access to the private assistant? =

Yes! The plugin integrates with Paid Memberships Pro and supports custom user roles. You have complete control over who can access private AI features.

= What happens if the AI doesn't know something? =

The AI will politely indicate when it doesn't have enough information and direct users to contact you directly. It won't make up answers or use outdated internet information.

= How do I get support? =

Visit the WordPress.org support forum for community help, or contact support@aiohm.app for direct assistance. We also provide comprehensive documentation and video tutorials.

= Is there a free trial? =

The plugin itself is free. You'll need an API key from supported providers (OpenAI, Claude, etc.), most of which offer free credits to get started.

== Screenshots ==

1. **Dashboard Overview** - Complete control center for your AI assistant setup and management
2. **Mirror Mode Configuration** - Set up your public chatbot with brand voice and personality
3. **Muse Mode Settings** - Configure your private AI assistant for creative workflows
4. **Knowledge Base Manager** - View, organize, and manage all your indexed content
5. **Brand Soul Questionnaire** - Define your unique voice and communication style
6. **Public Chat Interface** - Clean, responsive chatbot for website visitors
7. **Private Assistant Interface** - Full-featured AI assistant for authenticated users
8. **Content Scanning** - Automatic indexing of posts, pages, and uploaded documents

== Changelog ==

= 1.2.9 =
**🛠️ WordPress.org Compliance & Code Quality Update**
* **Fixed**: Added missing translators comments for all internationalization functions with placeholders
* **Fixed**: Resolved syntax errors in file upload crawler that prevented proper file processing
* **Fixed**: Proper escaping of all translatable strings to meet WordPress security standards
* **Removed**: Deprecated load_plugin_textdomain() call (WordPress automatically handles translations)
* **Removed**: Hidden .gitattributes file that was flagged by WordPress.org guidelines
* **Enhanced**: User consent system for external API calls with clear privacy disclosure
* **Enhanced**: Complete translation support implementation for international users
* **Enhanced**: Plugin now fully complies with WordPress.org plugin directory standards
* **Technical**: All code now passes WordPress Coding Standards validation
* **Technical**: Improved error handling and logging throughout the plugin

= 1.2.8 =
**🔧 Maintenance & Stability Update**
* Various bug fixes and performance improvements
* Enhanced error handling and logging

= 1.2.5 =
**🚀 Major Feature Release: MCP (Model Context Protocol) API - Private Members Only**
* **NEW**: Complete MCP server implementation for sharing knowledge base with external AI assistants
* **NEW**: Token-based authentication system with granular permissions (read-only or read/write)
* **NEW**: Standardized API endpoints: `/wp-json/aiohm/mcp/v1/manifest` and `/wp-json/aiohm/mcp/v1/call`
* **NEW**: MCP admin interface for token management and API configuration (Private level access only)
* **NEW**: Built-in rate limiting (1000 requests/hour per token) with IP-based protection
* **NEW**: API usage logging and monitoring for security and analytics
* **NEW**: Six MCP capabilities: queryKB, getKBEntry, listKBEntries, addKBEntry, updateKBEntry, deleteKBEntry
* **NEW**: Comprehensive MCP documentation with integration examples
* **NEW**: Membership-based access control - MCP restricted to Private level members
* **NEW**: Private members automatically receive Club and Tribal level access through MCP
* **Enhancement**: Plugin now supports external AI assistant connections (Claude Desktop, mobile apps, other sites)
* **Enhancement**: Future-proofing for the emerging AI agent ecosystem
* **Technical**: New database tables for MCP token management and usage tracking
* **Technical**: Integrated membership validation for all MCP operations

= 1.2.4 =
**🔒 Critical Security & Stability Update**
* **CRITICAL FIX**: Resolved broken API key encryption that prevented AI provider connectivity
* **Security Enhancement**: Fixed SQL injection vulnerability in knowledge base manager  
* **Enhanced Protection**: Improved error message security to prevent sensitive information disclosure
* **Stronger Rate Limiting**: Added IP-based protection to prevent bypass attempts
* **File Upload Security**: Enhanced JSON validation for safer file uploads
* **WordPress.org Ready**: Fixed all coding standards issues for official plugin directory submission
* **Breaking Change**: API keys will need to be re-entered due to encryption fix

= 1.2.0 =
**🎉 Major User Experience Update**
* **Notes & Knowledge Management**: Save ideas and add them to your AI's knowledge base instantly
* **Improved Admin Notifications**: Beautiful, branded confirmation dialogs
* **Better Content Processing**: Smarter handling of complex page layouts
* **Enhanced Error Messages**: Clear, helpful feedback when things need attention
* **Visual Progress Indicators**: See exactly what's happening during setup
* **ServBay/Ollama Support**: Use local AI models for maximum privacy
* **Email Verification System**: Secure access to advanced features
* **Streamlined Interface**: Cleaner, more intuitive user experience

= 1.1.11 =
* Enhanced Mirror Mode customization options
* Improved Muse Mode brand voice consistency
* Better mobile responsiveness
* Fixed various UI elements and animations
* Added comprehensive brand styling throughout

= 1.1.10 =
* Added support for Anthropic Claude models
* Improved error handling and user feedback
* Enhanced file upload processing for PDFs
* Better integration with Paid Memberships Pro
* Performance optimizations for large knowledge bases

= 1.1.9 =
* Initial public release
* Dual-mode AI functionality (Mirror + Muse)
* Multi-provider AI support (OpenAI, Gemini)
* RAG-powered knowledge base system
* Brand voice training and customization
* WordPress integration with shortcodes

== Upgrade Notice ==

= 1.2.5 =
New MCP API for Private members to share knowledge base with external AI assistants like Claude Desktop. Private members get Club and Tribal access. Upgrade to Private level to unlock this feature.

= 1.2.4 =
Critical security update with encryption fixes. API keys will need to be re-entered after update for continued functionality. This version resolves all WordPress.org submission requirements.

= 1.2.0 =
Major user experience improvements with new knowledge management features and better admin interface. Recommended for all users.

= 1.1.11 =
Important updates for mobile users and visual improvements. Recommended update for better user experience.

== Developer Information ==

**Plugin Architecture**:
* Object-oriented PHP with singleton patterns
* WordPress coding standards compliant
* Comprehensive error logging and debugging
* Extensive use of WordPress hooks and filters

**Database Schema**:
* Custom tables for vector storage and conversations
* Efficient indexing for fast retrieval
* Automatic schema updates with version management
* Cleanup procedures for uninstallation

**Security Features**:
* Encrypted API key storage with WordPress constants
* Prepared SQL statements throughout
* Nonce verification for all AJAX requests
* Capability checks for admin functions
* Input sanitization and output escaping

**Performance Optimizations**:
* WordPress object cache integration
* Smart query optimization with prepared statements
* Minimal HTTP requests with efficient caching
* Lazy loading for improved page speed

**Extensibility**:
* Action and filter hooks for developers
* Modular architecture for easy customization
* Template system for UI modifications
* API endpoints for third-party integrations

**Testing & Quality Assurance**:
* WordPress 5.8+ compatibility tested
* PHP 7.4+ requirement with 8.x support
* Cross-browser compatibility verified
* Mobile-responsive design validated
* Security audit completed

For developers interested in extending AIOHM functionality, comprehensive documentation is available at https://aiohm.app/developers/

== Privacy Policy ==

AIOHM Knowledge Assistant is designed with privacy as a core principle:

**Data Collection**:
* The plugin only processes content you explicitly add to your knowledge base
* User conversations are stored locally in your WordPress database
* No personal data is transmitted to external services except AI provider APIs

**Third-Party Services**:

This plugin integrates with external AI service providers to deliver its core functionality. Usage of these services is optional and requires your explicit configuration with API keys.

**OpenAI (https://openai.com/)**
- Purpose: AI chat responses and content embeddings
- Data sent: User chat messages, knowledge base content chunks (when processing queries)
- When data is sent: Only when users interact with AI chat features or when content is being indexed
- Privacy Policy: https://openai.com/privacy/
- Terms of Service: https://openai.com/terms/

**Google Gemini (https://ai.google.dev/)**
- Purpose: AI chat responses and content embeddings  
- Data sent: User chat messages, knowledge base content chunks (when processing queries)
- When data is sent: Only when users interact with AI chat features or when content is being indexed
- Privacy Policy: https://policies.google.com/privacy
- Terms of Service: https://policies.google.com/terms

**Anthropic Claude (https://www.anthropic.com/)**
- Purpose: AI chat responses
- Data sent: User chat messages, knowledge base content chunks (when processing queries)
- When data is sent: Only when users interact with AI chat features
- Privacy Policy: https://www.anthropic.com/privacy
- Terms of Service: https://www.anthropic.com/terms

**ShareAI & Ollama (Self-hosted options)**
- Purpose: Private AI processing for maximum data privacy
- Data sent: Content remains on your server or specified private server
- When data is sent: Only to your configured private server endpoint

**Data Control**: You maintain full control over what content is sent to AI providers. Only content you explicitly add to your knowledge base and user chat interactions are processed. No personal data or user information is transmitted beyond what's necessary for AI processing.

**Data Retention**:
* All data remains on your WordPress installation
* Conversation history can be deleted at any time
* Complete data removal available through uninstallation

**User Rights**:
* Users can request deletion of their conversation data
* No tracking or analytics data collected without explicit consent
* Full transparency in data usage and processing

For complete privacy details, visit: https://aiohm.app/privacy/

== Support ==

**Community Support**:
* WordPress.org support forum for general questions
* Community-driven solutions and best practices
* Plugin compatibility discussions

**Professional Support**:
* Direct email support: support@aiohm.app
* Priority response for critical issues
* Custom implementation assistance available

**Documentation**:
* Comprehensive setup guides at https://aiohm.app/docs/
* Video tutorials for visual learners
* Developer documentation for customizations

**Contributing**:
* Bug reports and feature requests welcome
* Translation contributions appreciated
* Code contributions considered for future releases

== Credits ==

**Development Team**:
* OHM Events Agency - Core development and design
* WordPress community feedback and testing
* Open source libraries for PDF processing and utilities

**Special Thanks**:
* WordPress.org plugin review team
* Beta testers and early adopters
* AI provider communities for integration support

**Third-Party Libraries**:
* Smalot/PdfParser for PDF text extraction (LGPLv3 - GPL Compatible)
* FPDF for PDF generation capabilities (Permissive License - GPL Compatible)
* Various WordPress-compatible utility functions

**License Compatibility**:
All included third-party libraries are GPL-compatible. This plugin and all its components comply with GPLv2 or later licensing requirements. Users receive the four essential freedoms: (0) to run the program, (1) to study and change the program in source code form, (2) to redistribute exact copies, and (3) to distribute modified versions.

Visit https://ohm.events for more information about the development team.