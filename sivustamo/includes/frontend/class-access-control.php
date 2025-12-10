<?php
/**
 * Pääsynhallinta
 */

namespace Sivustamo\Client\Frontend;

use Sivustamo\Client\Helpers\Helpers;

class Access_Control {

    /**
     * Tarkista pääsy ohjeet-sivuille
     */
    public static function check_access() {
        // Tarkista onko kirjautunut
        if (!is_user_logged_in()) {
            return false;
        }

        // Käyttäjät joilla on read-oikeus pääsevät katsomaan ohjeita
        // Oletuksena: administrator, editor, author, contributor, subscriber
        // Tarkempi suodatus tapahtuu ohje/kategoria-kohtaisesti
        return current_user_can('read');
    }

    /**
     * Tarkista pääsy tiettyyn ohjeeseen
     */
    public static function can_view_ohje($ohje_id) {
        if (!is_user_logged_in()) {
            return false;
        }

        return Helpers::can_view_ohje($ohje_id);
    }

    /**
     * Tarkista pääsy kategoriaan
     */
    public static function can_view_kategoria($term_id) {
        if (!is_user_logged_in()) {
            return false;
        }

        return Helpers::can_view_kategoria($term_id);
    }

    /**
     * Suodata ohjeet käyttöoikeuksien mukaan
     */
    public static function filter_ohjeet($ohjeet) {
        if (!is_user_logged_in()) {
            return [];
        }

        $user = wp_get_current_user();
        $filtered = [];

        foreach ($ohjeet as $ohje) {
            $ohje_id = is_object($ohje) ? $ohje->ID : $ohje;

            if (self::can_view_ohje($ohje_id)) {
                $filtered[] = $ohje;
            }
        }

        return $filtered;
    }

    /**
     * Suodata kategoriat käyttöoikeuksien mukaan
     */
    public static function filter_kategoriat($kategoriat) {
        if (!is_user_logged_in()) {
            return [];
        }

        $filtered = [];

        foreach ($kategoriat as $kategoria) {
            $term_id = is_object($kategoria) ? $kategoria->term_id : $kategoria;

            if (self::can_view_kategoria($term_id)) {
                $filtered[] = $kategoria;
            }
        }

        return $filtered;
    }

    /**
     * Näytä virhesivu
     */
    public static function show_access_denied() {
        if (!is_user_logged_in()) {
            // Ohjaa kirjautumiseen
            $redirect_url = add_query_arg('redirect_to', urlencode($_SERVER['REQUEST_URI']), wp_login_url());
            wp_redirect($redirect_url);
            exit;
        }

        // Näytä virhesivu
        status_header(403);
        get_header();
        ?>
        <div class="sivustamo-access-denied">
            <h1><?php _e('Ei käyttöoikeutta', 'sivustamo'); ?></h1>
            <p><?php _e('Sinulla ei ole oikeutta nähdä tätä sivua.', 'sivustamo'); ?></p>
            <p><a href="<?php echo esc_url(home_url()); ?>"><?php _e('Palaa etusivulle', 'sivustamo'); ?></a></p>
        </div>
        <?php
        get_footer();
        exit;
    }
}
