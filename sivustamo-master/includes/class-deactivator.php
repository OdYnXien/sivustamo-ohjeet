<?php
/**
 * Deaktivointi-luokka
 */

namespace Sivustamo\Master;

class Deactivator {

    /**
     * Deaktivoi plugin
     */
    public static function deactivate() {
        // Flush rewrite rules
        flush_rewrite_rules();

        // Huom: Tietokantatauluja EI poisteta deaktivoinnissa
        // Ne poistetaan vain uninstall.php:ssä
    }
}
