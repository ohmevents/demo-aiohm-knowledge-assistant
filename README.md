# AIOHM Knowledge Assistant - WordPress Plugin

Transform your WordPress site into an intelligent AI-powered knowledge hub with dual-mode AI functionality and brand voice alignment.

## 🚀 Core Features

### 🔄 **Dual AI Mode System**
- **Mirror Mode** - Public-facing chatbot for website visitors using public content
- **Muse Mode** - Private AI assistant for authenticated users with access to private Brand Soul data
- **Seamless mode switching** based on user authentication and permissions
- **Independent configuration** for each mode with separate settings

### 🤖 **Multi-AI Provider Support**
- **OpenAI Integration** - GPT-3.5 Turbo, GPT-4 support with API key management
- **Google Gemini** - Gemini Pro model integration with direct API connection
- **Anthropic Claude** - Claude 3 Sonnet model support with proper authentication
- **ShareAI** - Open-source LLM access with custom model selection
- **Ollama** - Local/private LLM server connection for maximum privacy
- **Automatic provider fallback** and error handling
- **Individual API key testing** and validation for each provider

### 🎨 **Brand Voice & Personality**
- **AI Brand Core Questionnaire** - 20 foundation questions to define brand essence
- **Soul Signature System Prompts** - Custom personality definition for each AI mode
- **Archetype Selection** - Pre-built personality templates (Sage, Creator, Explorer, etc.)
- **Dynamic placeholders** - %site_name%, %site_tagline%, %current_date%, %day_of_week%
- **Temperature controls** - Creativity vs. predictability slider (0-1 scale)
- **Custom welcome messages** for each mode

### 📚 **Knowledge Base Management**
- **Content Scanning Engine** - Automatic scanning of WordPress posts, pages, and media
- **File Upload Support** - PDF, CSV, TXT, JSON file processing and indexing
- **Vector Embeddings** - Semantic search using AI-generated embeddings
- **Chunking System** - Smart content splitting with configurable overlap
- **RAG (Retrieval-Augmented Generation)** - Context-aware AI responses
- **Knowledge Base Export** - Download curated knowledge as portable files
- **Content Curation** - Select, edit, and organize what AI learns from

### ⚙️ **Advanced Settings & Controls**
- **Privacy Consent Management** - User control over external API calls
- **Rate Limiting** - IP and user-based request limiting for security
- **API Key Encryption** - Secure storage using WordPress auth constants
- **Nonce Security** - CSRF protection for all AJAX operations
- **Input Validation** - Comprehensive sanitization and validation
- **Error Handling** - Graceful fallbacks and user-friendly error messages

### 🔌 **MCP (Model Context Protocol) Integration**
- **API Token Generation** - Create secure tokens for external applications
- **Token Type Management** - Private vs. public access token types
- **Rate Limiting Controls** - Configurable request limits per token
- **HTTPS Enforcement** - Security options for MCP endpoints
- **External AI Integration** - Connect with Claude Desktop, mobile apps, etc.
- **Usage Monitoring** - Track API usage and token activity

### 🖥️ **Admin Interface Features**

#### Mirror Mode Configuration
- **AI Model Selection** - Choose provider and model for public chatbot
- **Visual Customization** - Chat widget colors, positioning, and styling
- **Welcome Message Setup** - Customize first visitor interaction
- **System Prompt Editor** - Define public-facing AI personality
- **Temperature Adjustment** - Control response creativity level
- **Real-time Preview** - See changes instantly in preview panel

#### Muse Mode Configuration  
- **Archetype-Based Setup** - Choose from pre-built personality templates
- **Custom System Prompts** - Advanced AI instruction customization
- **Multi-Provider Support** - Select different AI models for private use
- **Temperature Controls** - Fine-tune creative vs. focused responses
- **Fullscreen Mode** - Option to start in fullscreen interface
- **Private Content Access** - Integration with member-only content

#### Knowledge Base Dashboard
- **Content Statistics** - Overview of scannable website content
- **File Management** - Upload and organize knowledge base files
- **Vector Entry Management** - View, edit, and delete indexed content
- **Bulk Operations** - Mass selection and management tools
- **Search Functionality** - Find specific content within knowledge base
- **Export Options** - Download knowledge base in various formats

### 🔗 **WordPress Integration**
- **Shortcode Support** - `[aiohm_chat]`, `[aiohm_search]`, `[aiohm_private_assistant]`
- **Widget Integration** - Floating chat widget with customizable positioning
- **Multisite Compatible** - Works with WordPress multisite installations
- **Hook System** - Developer-friendly action and filter hooks
- **Database Tables** - Custom tables for conversations, messages, and vectors

### 🛡️ **Security & Privacy Features**
- **Encrypted Storage** - API keys encrypted with WordPress auth keys
- **Prepared Statements** - SQL injection prevention with %i placeholders
- **Content Validation** - File type restrictions and upload security
- **IP Rate Limiting** - Prevent abuse with configurable request limits
- **User Session Security** - Enhanced session management and validation
- **CORS Headers** - Proper security headers for API endpoints

### 📱 **Frontend Features**
- **Responsive Chat Interface** - Mobile-friendly chat widgets
- **Real-time Communication** - AJAX-powered chat interactions
- **Search Functionality** - Knowledge base search shortcode
- **Progressive Loading** - Optimized content loading and caching
- **Accessibility Support** - ARIA labels and keyboard navigation
- **Theme Integration** - Adapts to existing WordPress theme styling

### 🔧 **Developer Features**
- **REST API Endpoints** - Programmatic access to knowledge base
- **Webhook Support** - Integration with external services
- **Custom Post Types** - Structured data for conversations and projects
- **Action Hooks** - Extensible plugin architecture
- **Debug Logging** - Comprehensive logging system for troubleshooting
- **Database Health** - Built-in database maintenance and optimization

### 📊 **Analytics & Monitoring**
- **Usage Statistics** - Track AI token usage and costs
- **Provider Performance** - Monitor response times and success rates
- **Conversation Tracking** - Store and analyze chat interactions
- **Error Monitoring** - Comprehensive error logging and reporting
- **API Health Checks** - Monitor external service connectivity

## 🎯 **Membership Tiers**

### **Tribe (Free)**
- AI Brand Core questionnaire access
- Basic plugin functionality
- Community support
- Knowledge base management

### **Club (€1/month or €10/year)**
- Full Mirror & Muse Mode access
- All AI provider integrations
- Advanced customization options
- Priority support

### **Private (€37/month or €370/year or €300/lifetime)**
- Self-hosted infrastructure
- Private AI server connections
- MCP API access
- White-glove support
- Enterprise features

## 🚀 **Getting Started**

1. **Install the Plugin** - Upload and activate the AIOHM Knowledge Assistant
2. **Connect Your Account** - Link your AIOHM membership for feature access
3. **Configure AI Providers** - Add API keys for your preferred AI services
4. **Set Up Your Brand Voice** - Complete the AI Brand Core questionnaire
5. **Scan Your Content** - Build your knowledge base from existing content
6. **Customize Your Modes** - Configure Mirror and Muse mode personalities
7. **Deploy & Test** - Add shortcodes and test your AI assistants

Transform your WordPress site into an intelligent, brand-aligned AI hub that speaks your unique voice and serves your audience with precision and personality.
