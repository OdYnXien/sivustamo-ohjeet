<?php
/**
 * Deaktivointi-luokka
 */

namespace Sivustamo\Client;

class Deactivator {

    /**
     * Deaktivoi plugin
     */
    public static function deactivate() {
        // Poista cron
        wp_clear_scheduled_hook('sivustamo_sync_cron');

        // Flush rewrite rules
        flush_rewrite_rules();
    }
}
