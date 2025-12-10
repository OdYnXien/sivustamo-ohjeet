<?php
/**
 * Sivustamo Master WP-CLI komennot
 */

namespace Sivustamo\Master\CLI;

use Sivustamo\Master\Post_Types\Ryhma_CPT;
use Sivustamo\Master\Helpers\License_Generator;

if (!defined('WP_CLI')) {
    return;
}

/**
 * Sivustamo Master -lisäosan WP-CLI komennot
 */
class Sivustamo_CLI {

    /**
     * Tuo sivustot CSV-tiedostosta ja vie tulokset uuteen CSV:ään
     *
     * ## OPTIONS
     *
     * <input>
     * : CSV-tiedoston polku (sisään)
     *
     * [--output=<output>]
     * : Tulostiedoston polku (oletus: sivustamo-output.csv samassa hakemistossa)
     *
     * [--skip-header]
     * : Ohita ensimmäinen rivi (sarakkeiden nimet)
     *
     * [--dry-run]
     * : Näytä mitä tehtäisiin, älä tee muutoksia
     *
     * ## EXAMPLES
     *
     *     # Tuo sivustot CSV:stä
     *     wp sivustamo import domains.csv
     *
     *     # Tuo ilman ensimmäistä otsikkoriviä
     *     wp sivustamo import domains.csv --skip-header
     *
     *     # Testaa ensin
     *     wp sivustamo import domains.csv --dry-run
     *
     * ## CSV FORMAATTI (sisään)
     *
     *     domain,dev_domain,ryhmat,nimi
     *     asiakas1.fi,asiakas1.sivustamo.dev,oletus,Asiakas 1 Oy
     *     asiakas2.fi,,oletus;woocommerce,Asiakas 2 Oy
     *
     * ## CSV FORMAATTI (ulos)
     *
     *     domain,api_key,secret,master_url
     *     asiakas1.fi,SVM_XXXX-XXXX-XXXX-XXXX,abc123...,https://sivustamo.dev
     *
     * @param array $args
     * @param array $assoc_args
     */
    public function import($args, $assoc_args) {
        $input_file = $args[0];
        $skip_header = isset($assoc_args['skip-header']);
        $dry_run = isset($assoc_args['dry-run']);

        // Tarkista tiedosto
        if (!file_exists($input_file)) {
            \WP_CLI::error("Tiedostoa ei löydy: $input_file");
        }

        // Määritä tulostiedosto
        $output_file = isset($assoc_args['output'])
            ? $assoc_args['output']
            : dirname($input_file) . '/sivustamo-output.csv';

        // Lue CSV
        $handle = fopen($input_file, 'r');
        if (!$handle) {
            \WP_CLI::error("Tiedoston avaaminen epäonnistui: $input_file");
        }

        $rows = [];
        $line_num = 0;

        while (($data = fgetcsv($handle, 1000, ',')) !== false) {
            $line_num++;

            // Ohita otsikkorivi
            if ($skip_header && $line_num === 1) {
                continue;
            }

            // Tarkista sarakkeet
            if (count($data) < 4) {
                \WP_CLI::warning("Rivi $line_num: liian vähän sarakkeita, ohitetaan");
                continue;
            }

            $rows[] = [
                'domain' => trim($data[0]),
                'dev_domain' => trim($data[1]),
                'ryhmat' => trim($data[2]),
                'nimi' => trim($data[3]),
            ];
        }

        fclose($handle);

        if (empty($rows)) {
            \WP_CLI::error("CSV-tiedosto on tyhjä tai virheellinen");
        }

        \WP_CLI::log(sprintf("Löytyi %d sivustoa tuotavaksi", count($rows)));

        if ($dry_run) {
            \WP_CLI::log("\n--- DRY RUN - ei tehdä muutoksia ---\n");
        }

        // Master URL
        $master_url = get_site_url();

        // Tulosdata
        $output_data = [];

        // Prosessoi jokainen rivi
        $progress = \WP_CLI\Utils\make_progress_bar('Tuodaan sivustoja', count($rows));

        foreach ($rows as $row) {
            $domain = $row['domain'];
            $dev_domain = $row['dev_domain'];
            $nimi = $row['nimi'];
            $ryhmat_str = $row['ryhmat'];

            // Parsii ryhmät (puolipisteillä erotettu)
            $ryhma_slugs = array_filter(array_map('trim', explode(';', $ryhmat_str)));
            $ryhma_ids = [];

            foreach ($ryhma_slugs as $slug) {
                $ryhma = Ryhma_CPT::get_group_by_slug($slug);
                if ($ryhma) {
                    $ryhma_ids[] = $ryhma->ID;
                } else {
                    \WP_CLI::warning("Ryhmää ei löydy: '$slug' (sivusto: $domain)");
                }
            }

            // Jos ei ryhmiä, käytä oletusryhmää
            if (empty($ryhma_ids)) {
                $default = Ryhma_CPT::get_default_group();
                if ($default) {
                    $ryhma_ids[] = $default->ID;
                }
            }

            // Tarkista onko domain jo olemassa
            $existing = get_posts([
                'post_type' => 'sivustamo_sivusto',
                'post_status' => 'any',
                'posts_per_page' => 1,
                'meta_query' => [
                    [
                        'key' => '_sivusto_domain',
                        'value' => $domain,
                    ]
                ]
            ]);

            if (!empty($existing)) {
                \WP_CLI::warning("Sivusto '$domain' on jo olemassa, ohitetaan");
                $progress->tick();
                continue;
            }

            // Generoi avaimet
            $api_key = License_Generator::generate_api_key();
            $secret = License_Generator::generate_secret();

            if (!$dry_run) {
                // Luo sivusto
                $post_id = wp_insert_post([
                    'post_type' => 'sivustamo_sivusto',
                    'post_title' => $nimi ?: $domain,
                    'post_status' => 'publish',
                ]);

                if (is_wp_error($post_id)) {
                    \WP_CLI::warning("Sivuston '$domain' luonti epäonnistui: " . $post_id->get_error_message());
                    $progress->tick();
                    continue;
                }

                // Tallenna metat
                update_post_meta($post_id, '_sivusto_domain', $domain);
                update_post_meta($post_id, '_sivusto_dev_domain', $dev_domain);
                update_post_meta($post_id, '_sivusto_api_key', $api_key);
                update_post_meta($post_id, '_sivusto_secret', $secret);
                update_post_meta($post_id, '_sivusto_active', '1');
                update_post_meta($post_id, '_sivusto_ryhmat', $ryhma_ids);
                update_post_meta($post_id, '_sivusto_created', current_time('mysql'));
            }

            // Lisää tulosdataan
            $output_data[] = [
                'domain' => $domain,
                'api_key' => $api_key,
                'secret' => $secret,
                'master_url' => $master_url,
            ];

            $progress->tick();
        }

        $progress->finish();

        // Kirjoita tulostiedosto
        if (!$dry_run && !empty($output_data)) {
            $out_handle = fopen($output_file, 'w');
            if ($out_handle) {
                // Otsikkorivi
                fputcsv($out_handle, ['domain', 'api_key', 'secret', 'master_url']);

                foreach ($output_data as $data) {
                    fputcsv($out_handle, $data);
                }

                fclose($out_handle);
                \WP_CLI::success("Tulokset kirjoitettu tiedostoon: $output_file");
            } else {
                \WP_CLI::warning("Tulostiedoston kirjoitus epäonnistui: $output_file");
            }
        }

        if ($dry_run) {
            \WP_CLI::log("\n--- DRY RUN VALMIS ---");
            \WP_CLI::log("Suorita ilman --dry-run tehdäksesi muutokset.\n");
        }

        \WP_CLI::success(sprintf("Valmis! %d sivustoa käsitelty.", count($output_data)));
    }

    /**
     * Listaa käyttöoikeusryhmät
     *
     * ## OPTIONS
     *
     * [--format=<format>]
     * : Output format (table, csv, json)
     * ---
     * default: table
     * options:
     *   - table
     *   - csv
     *   - json
     * ---
     *
     * ## EXAMPLES
     *
     *     wp sivustamo groups
     *     wp sivustamo groups --format=csv
     *
     * @param array $args
     * @param array $assoc_args
     */
    public function groups($args, $assoc_args) {
        $format = isset($assoc_args['format']) ? $assoc_args['format'] : 'table';

        $groups = Ryhma_CPT::get_all_groups();

        if (empty($groups)) {
            \WP_CLI::log("Ei ryhmiä.");
            return;
        }

        $items = [];
        foreach ($groups as $group) {
            $sivustot = get_posts([
                'post_type' => 'sivustamo_sivusto',
                'post_status' => 'publish',
                'posts_per_page' => -1,
                'fields' => 'ids',
                'meta_query' => [
                    [
                        'key' => '_sivusto_ryhmat',
                        'value' => $group->ID,
                        'compare' => 'LIKE',
                    ]
                ],
            ]);

            $is_default = get_post_meta($group->ID, '_ryhma_is_default', true) === '1';

            $items[] = [
                'ID' => $group->ID,
                'Nimi' => $group->post_title,
                'Slug' => get_post_meta($group->ID, '_ryhma_slug', true),
                'Sivustoja' => count($sivustot),
                'Oletus' => $is_default ? 'Kyllä' : '-',
            ];
        }

        \WP_CLI\Utils\format_items($format, $items, ['ID', 'Nimi', 'Slug', 'Sivustoja', 'Oletus']);
    }

    /**
     * Luo uusi käyttöoikeusryhmä
     *
     * ## OPTIONS
     *
     * <name>
     * : Ryhmän nimi
     *
     * [--slug=<slug>]
     * : Ryhmän tunniste (oletus: generoitu nimestä)
     *
     * [--description=<description>]
     * : Ryhmän kuvaus
     *
     * [--default]
     * : Aseta oletusryhmäksi
     *
     * ## EXAMPLES
     *
     *     wp sivustamo create-group "Oletus"
     *     wp sivustamo create-group "WooCommerce-sivustot" --slug=woocommerce
     *     wp sivustamo create-group "Perus" --default
     *
     * @param array $args
     * @param array $assoc_args
     */
    public function create_group($args, $assoc_args) {
        $name = $args[0];
        $slug = isset($assoc_args['slug']) ? sanitize_title($assoc_args['slug']) : sanitize_title($name);
        $description = isset($assoc_args['description']) ? $assoc_args['description'] : '';
        $is_default = isset($assoc_args['default']);

        // Tarkista onko slug käytössä
        $existing = Ryhma_CPT::get_group_by_slug($slug);
        if ($existing) {
            \WP_CLI::error("Ryhmä tunnistella '$slug' on jo olemassa.");
        }

        // Luo ryhmä
        $post_id = wp_insert_post([
            'post_type' => 'sivustamo_ryhma',
            'post_title' => $name,
            'post_status' => 'publish',
        ]);

        if (is_wp_error($post_id)) {
            \WP_CLI::error("Ryhmän luonti epäonnistui: " . $post_id->get_error_message());
        }

        update_post_meta($post_id, '_ryhma_slug', $slug);
        update_post_meta($post_id, '_ryhma_description', $description);

        // Jos asetetaan oletukseksi, poista muilta
        if ($is_default) {
            $other_defaults = get_posts([
                'post_type' => 'sivustamo_ryhma',
                'post_status' => 'any',
                'posts_per_page' => -1,
                'post__not_in' => [$post_id],
                'meta_key' => '_ryhma_is_default',
                'meta_value' => '1',
                'fields' => 'ids',
            ]);

            foreach ($other_defaults as $other_id) {
                update_post_meta($other_id, '_ryhma_is_default', '0');
            }

            update_post_meta($post_id, '_ryhma_is_default', '1');
        } else {
            update_post_meta($post_id, '_ryhma_is_default', '0');
        }

        \WP_CLI::success(sprintf("Ryhmä '%s' luotu (ID: %d, slug: %s)", $name, $post_id, $slug));
    }

    /**
     * Vie sivustojen API-avaimet CSV-tiedostoon
     *
     * ## OPTIONS
     *
     * [--output=<output>]
     * : Tulostiedoston polku
     *
     * [--group=<slug>]
     * : Vie vain tietyn ryhmän sivustot
     *
     * [--active-only]
     * : Vie vain aktiiviset sivustot
     *
     * ## EXAMPLES
     *
     *     wp sivustamo export
     *     wp sivustamo export --output=sivustot.csv
     *     wp sivustamo export --group=woocommerce
     *
     * @param array $args
     * @param array $assoc_args
     */
    public function export($args, $assoc_args) {
        $output_file = isset($assoc_args['output']) ? $assoc_args['output'] : 'sivustamo-export.csv';
        $group_slug = isset($assoc_args['group']) ? $assoc_args['group'] : null;
        $active_only = isset($assoc_args['active-only']);

        $query_args = [
            'post_type' => 'sivustamo_sivusto',
            'post_status' => 'publish',
            'posts_per_page' => -1,
        ];

        // Ryhmäsuodatus
        if ($group_slug) {
            $group = Ryhma_CPT::get_group_by_slug($group_slug);
            if (!$group) {
                \WP_CLI::error("Ryhmää ei löydy: $group_slug");
            }
            $query_args['meta_query'][] = [
                'key' => '_sivusto_ryhmat',
                'value' => $group->ID,
                'compare' => 'LIKE',
            ];
        }

        // Aktiivisuussuodatus
        if ($active_only) {
            $query_args['meta_query'][] = [
                'key' => '_sivusto_active',
                'value' => '1',
            ];
        }

        $sivustot = get_posts($query_args);

        if (empty($sivustot)) {
            \WP_CLI::log("Ei sivustoja vietäväksi.");
            return;
        }

        $master_url = get_site_url();
        $handle = fopen($output_file, 'w');

        if (!$handle) {
            \WP_CLI::error("Tiedoston avaaminen epäonnistui: $output_file");
        }

        // Otsikkorivi
        fputcsv($handle, ['domain', 'api_key', 'secret', 'master_url']);

        foreach ($sivustot as $sivusto) {
            fputcsv($handle, [
                get_post_meta($sivusto->ID, '_sivusto_domain', true),
                get_post_meta($sivusto->ID, '_sivusto_api_key', true),
                get_post_meta($sivusto->ID, '_sivusto_secret', true),
                $master_url,
            ]);
        }

        fclose($handle);

        \WP_CLI::success(sprintf("Viety %d sivustoa tiedostoon: %s", count($sivustot), $output_file));
    }
}

// Rekisteröi komennot
\WP_CLI::add_command('sivustamo', __NAMESPACE__ . '\\Sivustamo_CLI');
