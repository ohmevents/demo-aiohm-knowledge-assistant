# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is the **AIOHM Knowledge Assistant** WordPress plugin that transforms WordPress sites into AI-powered knowledge hubs. The plugin provides dual-mode AI functionality:

- **Mirror Mode**: Public-facing chatbot for website visitors (uses public content)
- **Muse Mode**: Private AI assistant for authenticated users (includes private Brand Soul data)

The plugin uses Retrieval-Augmented Generation (RAG) with vector search and supports multiple AI providers (OpenAI, Claude, Gemini, ShareAI, Ollama).

## Development Commands

This is a WordPress plugin with no build process. Development involves:

- **Testing**: Install in WordPress development environment and test manually
- **Linting**: WordPress follows coding standards but no automated linting is configured
- **Debugging**: Enable `WP_DEBUG` and `WP_DEBUG_LOG` in wp-config.php to view logs

## Architecture Overview

### Main Plugin Structure

- **aiohm-kb-assistant.php**: Main plugin file with activation/deactivation hooks, settings management, and database table creation
- **includes/core-init.php**: Core initialization class handling AJAX endpoints, user management, and admin functionality
- **includes/rag-engine.php**: RAG (Retrieval-Augmented Generation) engine for vector search and content retrieval
- **includes/ai-gpt-client.php**: AI client abstraction layer supporting multiple providers
- **includes/aiohm-kb-manager.php**: Knowledge base management for content indexing and chunking

### Key Components

1. **Database Tables**:
   - `aiohm_vector_entries`: Stores chunked content with metadata and vector data
   - `aiohm_conversations`: Chat conversation storage
   - `aiohm_messages`: Individual chat messages
   - `aiohm_projects`: User projects for organizing conversations
   - `aiohm_mcp_tokens` & `aiohm_mcp_usage`: MCP (Model Context Protocol) token management

2. **Content Processing**:
   - Automatic scanning of WordPress posts, pages, and uploaded files
   - Support for PDF, CSV, TXT, JSON file processing
   - Configurable chunking with overlap for better context retrieval
   - Vector embeddings generation for semantic search

3. **AI Integration**:
   - Multi-provider support with fallback mechanisms
   - Encrypted API key storage using WordPress auth constants
   - Rate limiting and security validation
   - Custom system prompts and temperature controls

### Admin Interface

Templates in `templates/` directory:
- **admin-settings.php**: Main settings page with API configuration
- **admin-mirror-mode.php** & **admin-muse-mode.php**: Mode-specific configuration
- **admin-manage-kb.php**: Knowledge base content management
- **admin-mcp.php**: MCP token management
- **scan-website.php**: Content scanning interface

### Frontend Integration

- **Shortcodes**: `[aiohm_chat]`, `[aiohm_search]`, `[aiohm_private_assistant]`
- **Widgets**: Floating chat widget option
- **AJAX Endpoints**: All interactions via WordPress AJAX with nonce security

## Security Considerations

- API keys are encrypted using WordPress `SECURE_AUTH_KEY` and `LOGGED_IN_KEY`
- All database queries use prepared statements with `%i` placeholders for IDs
- Rate limiting implemented for sensitive endpoints
- Input validation and sanitization on all user inputs
- Content type validation prevents malicious file uploads
- IP-based and user-based rate limiting

## Important Notes

- Requires WordPress 6.2+ for `%i` placeholder support in prepared statements
- PHP 7.4+ required
- Uses WordPress transients for caching and rate limiting
- Supports WordPress multisite installations
- Integrates with Paid Memberships Pro for access control