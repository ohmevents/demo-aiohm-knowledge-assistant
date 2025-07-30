<?php if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly ?>
<div class="wrap">
    <h2>Your Membership Details</h2>
    <p><strong>Plan:</strong> <?php echo esc_html($plan_name ?? 'Unknown'); ?></p>
    <p><strong>Status:</strong> <?php echo esc_html($plan_status ?? 'Inactive'); ?></p>
    <p><strong>Expires:</strong> <?php echo esc_html($plan_expiration ?? 'N/A'); ?></p>
</div>