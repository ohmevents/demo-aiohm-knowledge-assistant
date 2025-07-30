<?php
/**
 * Demo Response Generator for AIOHM Knowledge Assistant Demo Version
 * 
 * This file provides mock responses for all AI interactions to demonstrate
 * the plugin functionality without actual API calls.
 */

if (!defined('ABSPATH')) exit;

class AIOHM_Demo_Responses {
    
    /**
     * Get demo response based on mode and input
     */
    public static function get_demo_response($mode, $input = '', $context = '') {
        switch ($mode) {
            case 'mirror':
                return self::get_mirror_mode_response($input);
            case 'muse':
                return self::get_muse_mode_response($input);
            case 'search':
                return self::get_search_response($input);
            default:
                return self::get_default_response();
        }
    }
    
    /**
     * Mirror Mode demo responses (public chatbot)
     */
    private static function get_mirror_mode_response($input) {
        $responses = [
            'default' => "🔍 **This is a demo response from Mirror Mode**\n\nIn the full version, I would provide detailed answers based on your website's content and knowledge base. This response demonstrates the interface and functionality.\n\n✨ **Available in AIOHM Club** - [Upgrade Now](https://aiohm.app/club)",
            
            'hello' => "👋 Hello! This is a demo of Mirror Mode - your public-facing AI assistant.\n\nIn the full version, I would:\n• Answer questions using your website content\n• Maintain your brand voice\n• Provide accurate, contextual responses\n\n✨ **Available in AIOHM Club** - [Upgrade Now](https://aiohm.app/club)",
            
            'features' => "🚀 **Mirror Mode Features** (Demo)\n\nIn the full version, you get:\n• Public chatbot for website visitors\n• Brand voice alignment\n• Knowledge base integration\n• Customizable responses\n\n✨ **Available in AIOHM Club** - [Upgrade Now](https://aiohm.app/club)",
            
            'pricing' => "💰 **AIOHM Pricing** (Demo Response)\n\n• **Tribe**: Free - Brand Soul questionnaire\n• **Club**: €1/month - Full AI features\n• **Private**: Custom - Enterprise solutions\n\n✨ **Join today** - [Visit AIOHM](https://aiohm.app)"
        ];
        
        $input_lower = strtolower($input);
        
        if (strpos($input_lower, 'hello') !== false || strpos($input_lower, 'hi') !== false) {
            return $responses['hello'];
        } elseif (strpos($input_lower, 'feature') !== false || strpos($input_lower, 'what can') !== false) {
            return $responses['features'];
        } elseif (strpos($input_lower, 'price') !== false || strpos($input_lower, 'cost') !== false) {
            return $responses['pricing'];
        }
        
        return $responses['default'];
    }
    
    /**
     * Muse Mode demo responses (private assistant)
     */
    private static function get_muse_mode_response($input) {
        $responses = [
            'default' => "🎨 **This is a demo response from Muse Mode**\n\nYour private brand assistant would help you with:\n• Content creation aligned with your brand\n• Strategic brand decisions\n• Creative ideation\n• Private Brand Soul insights\n\n✨ **Available in AIOHM Club** - [Upgrade Now](https://aiohm.app/club)",
            
            'brand' => "🎯 **Brand Strategy Demo**\n\nIn Muse Mode, I would analyze your Brand Soul answers and provide:\n• Personalized brand recommendations\n• Voice and tone guidance\n• Content strategy insights\n• Competitive positioning\n\n✨ **Available in AIOHM Club** - [Upgrade Now](https://aiohm.app/club)",
            
            'content' => "✍️ **Content Creation Demo**\n\nMuse Mode would help you create:\n• Blog posts in your brand voice\n• Social media content\n• Marketing copy\n• Brand messaging\n\n✨ **Available in AIOHM Club** - [Upgrade Now](https://aiohm.app/club)",
            
            'strategy' => "🧠 **Strategic Insights Demo**\n\nWith your Brand Soul data, I would provide:\n• Market positioning advice\n• Audience targeting insights\n• Brand differentiation strategies\n• Growth opportunities\n\n✨ **Available in AIOHM Club** - [Upgrade Now](https://aiohm.app/club)"
        ];
        
        $input_lower = strtolower($input);
        
        if (strpos($input_lower, 'brand') !== false) {
            return $responses['brand'];
        } elseif (strpos($input_lower, 'content') !== false || strpos($input_lower, 'write') !== false) {
            return $responses['content'];
        } elseif (strpos($input_lower, 'strategy') !== false || strpos($input_lower, 'plan') !== false) {
            return $responses['strategy'];
        }
        
        return $responses['default'];
    }
    
    /**
     * Search demo responses
     */
    private static function get_search_response($input) {
        return [
            'results' => [
                [
                    'title' => 'Demo Search Result 1',
                    'content' => 'This is a demo search result. In the full version, you would see actual content from your knowledge base.',
                    'relevance' => 0.95,
                    'source' => 'Demo Content'
                ],
                [
                    'title' => 'Demo Search Result 2', 
                    'content' => 'Another demo result showing how semantic search works with your content.',
                    'relevance' => 0.87,
                    'source' => 'Demo Content'
                ]
            ],
            'query' => $input,
            'demo_notice' => '✨ **Available in AIOHM Club** - [Upgrade Now](https://aiohm.app/club)'
        ];
    }
    
    /**
     * Default demo response
     */
    private static function get_default_response() {
        return "🎭 **AIOHM Knowledge Assistant Demo**\n\nThis is a demonstration of the AIOHM AI system. In the full version, you would get:\n\n• Real AI responses from multiple providers\n• Knowledge base integration\n• Brand voice alignment\n• Advanced features\n\n✨ **Upgrade to access full functionality** - [Join AIOHM](https://aiohm.app)";
    }
    
    /**
     * Check if this is demo version
     */
    public static function is_demo_version() {
        return defined('AIOHM_KB_VERSION') && AIOHM_KB_VERSION === 'DEMO';
    }
    
    /**
     * Get upgrade prompt for premium features
     */
    public static function get_upgrade_prompt($feature_name, $tier = 'club') {
        $tiers = [
            'tribe' => [
                'name' => 'AIOHM Tribe',
                'price' => 'Free',
                'url' => 'https://aiohm.app/register'
            ],
            'club' => [
                'name' => 'AIOHM Club', 
                'price' => '€1/month',
                'url' => 'https://aiohm.app/club'
            ],
            'private' => [
                'name' => 'AIOHM Private',
                'price' => 'Custom pricing',
                'url' => 'https://aiohm.app/private'
            ]
        ];
        
        $tier_info = $tiers[$tier] ?? $tiers['club'];
        
        return [
            'title' => "Upgrade Required",
            'message' => "The '{$feature_name}' feature is available in {$tier_info['name']} ({$tier_info['price']}).",
            'upgrade_url' => $tier_info['url'],
            'tier' => $tier_info['name']
        ];
    }
}