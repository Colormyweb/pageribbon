<?php
/**
 * PageRibbon — Page de réglages
 *
 * Une seule page, accessible via Réglages → PageRibbon, qui regroupe :
 *  - Le toggle global "mode conception" (kill-switch en 1 clic)
 *  - Le choix des taxos à colorer
 *  - L'attribution des couleurs aux termes
 *  - Les toggles d'affichage (admin liste / éditeur de pages)
 *
 * @package PageRibbon
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


/* ============================================================
 *  ENREGISTREMENT DU MENU
 *  Placé sous "Pages" pour rester proche du contexte d'usage.
 * ============================================================ */
add_action( 'admin_menu', 'pageribbon_register_menu', 30 );

function pageribbon_register_menu() {
    add_submenu_page(
        'edit.php?post_type=page',
        __( 'Réglages PageRibbon', 'pageribbon' ),
        __( '🎨 PageRibbon', 'pageribbon' ),
        'manage_categories',
        'pageribbon',
        'pageribbon_render_settings_page'
    );
}


/* ============================================================
 *  ENQUEUE DES ASSETS
 * ============================================================ */
add_action( 'admin_enqueue_scripts', 'pageribbon_enqueue_settings_assets' );

function pageribbon_enqueue_settings_assets( $hook ) {
    // Quand la page est sous edit.php?post_type=page, le hook devient
    // 'pages_page_pageribbon'. On gère les deux pour rester robuste si
    // un jour on change l'emplacement.
    if ( 'pages_page_pageribbon' !== $hook && 'settings_page_pageribbon' !== $hook ) {
        return;
    }

    wp_enqueue_script(
        'pageribbon-settings',
        PAGERIBBON_URL . 'assets/js/settings-page.js',
        array( 'jquery' ),
        PAGERIBBON_VERSION,
        true
    );

    // Petite feuille de style inline pour rester autonome.
    wp_add_inline_style( 'common', pageribbon_settings_inline_css() );
}


/* ============================================================
 *  CSS INLINE DE LA PAGE DE RÉGLAGES
 *  Tout inline pour minimiser les fichiers et garder le contrôle.
 * ============================================================ */
function pageribbon_settings_inline_css() {
    return '
    .pageribbon-wrap { max-width: 960px; }
    .pageribbon-card { background: #fff; border: 1px solid #c3c4c7; padding: 20px 24px; margin: 16px 0; border-radius: 8px; box-shadow: 0 1px 1px rgba(0,0,0,.04); }
    .pageribbon-card h2 { margin-top: 0; padding-bottom: 12px; border-bottom: 1px solid #f0f0f1; }
    .pageribbon-card p.description { color: #50575e; margin-top: 0; }
    .pageribbon-killswitch { background: #fcf9e8; border-color: #f0e6a6; }
    .pageribbon-killswitch.is-off { background: #fef2f2; border-color: #ef4444; }
    .pageribbon-killswitch h2 { border-bottom-color: rgba(0,0,0,.1); }
    .pageribbon-taxo-block { margin: 16px 0; padding: 16px; background: #f9fafb; border-radius: 4px; border: 1px solid #e5e7eb; }
    .pageribbon-taxo-block h3 { margin-top: 0; margin-bottom: 8px; }
    .pageribbon-terms-grid { display: grid; grid-template-columns: 1fr; gap: 8px; margin-top: 12px; }
    .pageribbon-term-row { display: flex; align-items: center; gap: 12px; padding: 8px 12px; background: #fff; border-radius: 4px; border: 1px solid #e5e7eb; }
    .pageribbon-term-row strong { flex: 1; }
    .pageribbon-term-row select { min-width: 160px; }
    .pageribbon-preview { display: inline-block; width: 32px; height: 32px; border-radius: 4px; border-width: 2px; border-style: solid; flex-shrink: 0; }
    .pageribbon-empty-state { padding: 24px; text-align: center; color: #50575e; background: #f9fafb; border-radius: 4px; }
    .pageribbon-toggle { display: flex; align-items: center; gap: 12px; padding: 12px 0; }
    .pageribbon-toggle input[type="checkbox"] { transform: scale(1.3); margin-right: 4px; }
    .pageribbon-sub-toggles { margin-left: 28px; padding-left: 12px; border-left: 2px solid #e5e7eb; margin-top: -4px; margin-bottom: 4px; }
    .pageribbon-sub-toggle { padding: 6px 0; font-size: 13px; color: #50575e; }
    .pageribbon-sub-toggle input[type="checkbox"] { transform: scale(1.1); }
    .pageribbon-axis { margin: 16px 0; padding: 16px; background: #f9fafb; border-radius: 4px; border: 1px solid #e5e7eb; }
    .pageribbon-axis + .pageribbon-axis { margin-top: 12px; }
    .pageribbon-axis-labels { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 12px; }
    .pageribbon-axis-labels label { flex: 1; min-width: 200px; }
    .pageribbon-axis-labels input[type="text"] { display: block; width: 100%; margin-top: 4px; }
    .pageribbon-mini-label { display: inline-block; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; color: #50575e; font-weight: 600; margin-right: 8px; }
    .pageribbon-axis-pts { padding-top: 10px; border-top: 1px solid #e5e7eb; }
    .pageribbon-pt-checkbox { margin-right: 16px; font-size: 13px; }
    .pageribbon-pt-checkbox input[type="checkbox"] { transform: scale(1.1); margin-right: 4px; }
    .pageribbon-pt-fixed { color: #50575e; font-size: 12px; font-style: italic; margin-left: 8px; }
    .pageribbon-alert { display: flex; align-items: center; gap: 14px; background: #fcf9e8; border: 1px solid #f0e6a6; border-radius: 8px; padding: 14px 18px; margin: 16px 0; box-shadow: 0 1px 1px rgba(0,0,0,.04); }
    .pageribbon-alert__icon { flex-shrink: 0; width: 26px; height: 26px; border-radius: 50%; background: #dba617; color: #fff; font-weight: 800; font-size: 16px; line-height: 26px; text-align: center; }
    .pageribbon-alert__body { flex: 1; font-size: 13.5px; color: #54451a; line-height: 1.5; }
    .pageribbon-alert__count { font-size: 15px; color: #7a5c00; }
    .pageribbon-alert .button { flex-shrink: 0; }
    .pageribbon-minihero { display: flex; align-items: stretch; gap: 0; background: #fff; border: 1px solid #c3c4c7; border-radius: 8px; overflow: hidden; margin: 16px 0; box-shadow: 0 1px 1px rgba(0,0,0,.04); }
    .pageribbon-minihero__ribbon { display: flex; flex-direction: column; width: 12px; flex-shrink: 0; }
    .pageribbon-minihero__ribbon span { flex: 1; display: block; }
    .pageribbon-minihero__body { padding: 18px 24px; }
    .pageribbon-minihero__title { margin: 0 0 6px; font-size: 18px; font-weight: 800; letter-spacing: -0.01em; color: #1f2328; border: 0; padding: 0; }
    .pageribbon-minihero__sub { margin: 0; font-size: 14px; line-height: 1.55; color: #50575e; }
    .pageribbon-minihero__cta { margin: 10px 0 0; font-size: 14px; font-weight: 700; color: #1f2328; }
    ';
}


/* ============================================================
 *  TRAITEMENT DU FORMULAIRE
 *
 *  Réécrit en 1.2.0 pour corriger un bug de persistance subtil :
 *  la version 1.0/1.1 enchaînait 7 appels update_option() successifs
 *  (un par section du formulaire), ce qui pouvait laisser une partie
 *  des modifications non écrite si le cache d'objet WP était mal-configuré
 *  ou si un plugin tiers interceptait l'un des appels.
 *
 *  Nouvelle logique : on lit l'option complète, on applique TOUS les
 *  changements en mémoire, on écrit l'option UNE SEULE fois à la fin.
 *  C'est plus robuste, plus rapide, et plus facile à débugger.
 *
 *  On profite aussi du passage pour nettoyer les taxos fantômes :
 *  toute entrée qui référence une taxo n'existant plus en base est éjectée.
 * ============================================================ */
add_action( 'admin_init', 'pageribbon_handle_settings_submit' );

function pageribbon_handle_settings_submit() {

    // Vérification de présence du marqueur de soumission.
    // On utilise isset() et NON empty() : un <button> sans attribut value
    // POSTE une chaîne vide, ce qui passe empty() à true et ferait sortir
    // le handler avant toute action.
    if ( ! isset( $_POST['pageribbon_submit'] ) ) {
        return;
    }

    if ( ! current_user_can( 'manage_categories' ) ) {
        wp_die( esc_html__( 'Permission refusée.', 'pageribbon' ) );
    }

    check_admin_referer( 'pageribbon_save_settings' );

    // Lecture de l'option complète, travail sur une copie en mémoire.
    $opts = get_option( PAGERIBBON_OPTION_KEY, array() );
    if ( ! is_array( $opts ) ) {
        $opts = array();
    }

    // 1. Kill-switch global (le différenciateur clé : "désactiver = tout disparaît")
    $opts['enabled'] = ! empty( $_POST['pageribbon_enabled'] );

    // 2. Toggles d'affichage par contexte
    $opts['show_in_admin_list'] = ! empty( $_POST['pageribbon_show_in_admin_list'] );
    $opts['show_in_editor']     = ! empty( $_POST['pageribbon_show_in_editor'] );

    // 2bis. Sous-toggles de la liste admin (filet / pastille / fond)
    $opts['admin_list_show_stripe'] = ! empty( $_POST['pageribbon_admin_list_show_stripe'] );
    $opts['admin_list_show_dot']    = ! empty( $_POST['pageribbon_admin_list_show_dot'] );
    $opts['admin_list_show_bg']     = ! empty( $_POST['pageribbon_admin_list_show_bg'] );

    // 2ter. Labels des taxos natives (renommage par l'utilisateur)
    $submitted_labels = isset( $_POST['pageribbon_taxo_labels'] ) ? (array) wp_unslash( $_POST['pageribbon_taxo_labels'] ) : array();
    $clean_labels = array();
    foreach ( array( 'famille', 'modele' ) as $key ) {
        foreach ( array( 'singular', 'plural' ) as $part ) {
            if ( isset( $submitted_labels[ $key ][ $part ] ) ) {
                $val = sanitize_text_field( $submitted_labels[ $key ][ $part ] );
                if ( '' !== $val ) {
                    $clean_labels[ $key ][ $part ] = $val;
                }
            }
        }
    }
    $opts['taxo_labels'] = $clean_labels;

    // 2quater. Post types ciblés
    //
    // Depuis la 1.4.1, PageRibbon se concentre sur les Pages uniquement par défaut.
    // L'extension à d'autres post types se fait via le filter `pageribbon_taxo_post_types`
    // dans taxonomies.php. On nettoie l'ancienne configuration en BDD si elle existait.
    if ( isset( $opts['taxo_post_types'] ) ) {
        unset( $opts['taxo_post_types'] );
    }

    // 3. Taxonomies activées
    //
    // Critère d'acceptation simplifié : on accepte la taxo si elle existe
    // vraiment côté WordPress et n'est pas une taxo système.
    // L'ancien code utilisait array_intersect avec $colorable, ce qui pouvait
    // produire des désynchronisations entre l'UI (qui montrait une case cochée)
    // et le save (qui la rejetait silencieusement).
    $submitted_taxos = isset( $_POST['pageribbon_enabled_taxonomies'] )
        ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['pageribbon_enabled_taxonomies'] ) )
        : array();

    $blocked = array( 'post_format', 'link_category', 'nav_menu' );
    $enabled_taxos = array();
    foreach ( $submitted_taxos as $taxo_slug ) {
        if ( ! taxonomy_exists( $taxo_slug ) ) {
            continue;
        }
        if ( in_array( $taxo_slug, $blocked, true ) ) {
            continue;
        }
        $enabled_taxos[] = $taxo_slug;
    }
    $opts['enabled_taxonomies'] = array_values( array_unique( $enabled_taxos ) );

    // 4. Couleurs par terme
    //
    // On reconstruit term_colors avec ce que le POST envoie pour les taxos posées,
    // et on garde les anciennes valeurs pour les taxos non touchées par ce POST.
    $palette_size = count( pageribbon_get_palette() );
    $term_colors  = isset( $opts['term_colors'] ) && is_array( $opts['term_colors'] ) ? $opts['term_colors'] : array();

    if ( isset( $_POST['pageribbon_term_colors'] ) && is_array( $_POST['pageribbon_term_colors'] ) ) {
        $posted_term_colors = wp_unslash( $_POST['pageribbon_term_colors'] );
        foreach ( $posted_term_colors as $taxo => $terms ) {
            $taxo = sanitize_key( $taxo );
            if ( ! taxonomy_exists( $taxo ) ) {
                continue;
            }
            // Pour cette taxo, on écrase complètement avec ce que le POST envoie
            $term_colors[ $taxo ] = array();
            foreach ( (array) $terms as $term_slug => $color_index ) {
                $term_slug   = sanitize_key( $term_slug );
                $color_index = (int) $color_index;
                if ( $color_index < 0 ) {
                    continue;
                }
                if ( $color_index >= $palette_size ) {
                    $color_index = $palette_size - 1;
                }
                $term_colors[ $taxo ][ $term_slug ] = $color_index;
            }
        }
    }

    // 4bis. Nettoyage des taxos fantômes (taxos qui n'existent plus mais traînent
    // en BDD : cas typique des plugins legacy désinstallés comme RésoNAnces)
    foreach ( array_keys( $term_colors ) as $taxo_slug ) {
        if ( ! taxonomy_exists( $taxo_slug ) ) {
            unset( $term_colors[ $taxo_slug ] );
        }
    }
    $opts['enabled_taxonomies'] = array_values( array_filter(
        $opts['enabled_taxonomies'],
        'taxonomy_exists'
    ) );

    $opts['term_colors'] = $term_colors;

    // 4ter. Marquage des termes touchés manuellement par l'utilisatrice via le POST
    //
    // À partir de la 1.4.0, on persiste un set des termes "touchés" (= une couleur
    // a été explicitement définie pour eux à un moment, soit par l'utilisatrice via
    // le formulaire, soit par l'auto-attribution). L'auto-attribution ne réécrira
    // jamais une couleur pour un terme touché : si l'utilisatrice retire manuellement
    // une couleur, elle ne sera pas réattribuée automatiquement au save suivant.
    $touched = isset( $opts['term_colors_touched'] ) && is_array( $opts['term_colors_touched'] ) ? $opts['term_colors_touched'] : array();

    // Marquer tous les termes qui ont été POSTés (l'utilisatrice les a vus dans le form)
    if ( isset( $_POST['pageribbon_term_colors'] ) && is_array( $_POST['pageribbon_term_colors'] ) ) {
        $posted_for_touched = wp_unslash( $_POST['pageribbon_term_colors'] );
        foreach ( $posted_for_touched as $taxo => $terms ) {
            $taxo = sanitize_key( $taxo );
            if ( ! taxonomy_exists( $taxo ) ) {
                continue;
            }
            foreach ( (array) $terms as $term_slug => $color_index ) {
                $term_slug = sanitize_key( $term_slug );
                if ( '' === $term_slug ) {
                    continue;
                }
                if ( ! isset( $touched[ $taxo ] ) ) {
                    $touched[ $taxo ] = array();
                }
                $touched[ $taxo ][ $term_slug ] = true;
            }
        }
    }

    // 5. Auto-attribution de couleur aux nouveaux termes JAMAIS touchés
    //
    // Pour chaque taxo activée, on regarde quels termes n'ont pas encore de couleur
    // ET n'ont jamais été touchés. Pour eux seulement, on attribue une couleur.
    // Cela évite à l'utilisatrice d'avoir à attribuer manuellement à chaque nouveau terme,
    // tout en respectant son choix si elle a explicitement retiré une couleur.
    foreach ( $opts['enabled_taxonomies'] as $taxo_slug ) {
        $terms = get_terms( array(
            'taxonomy'   => $taxo_slug,
            'hide_empty' => false,
        ) );
        if ( is_wp_error( $terms ) || empty( $terms ) ) {
            continue;
        }
        if ( ! isset( $opts['term_colors'][ $taxo_slug ] ) ) {
            $opts['term_colors'][ $taxo_slug ] = array();
        }
        $used = array_values( array_map( 'intval', $opts['term_colors'][ $taxo_slug ] ) );
        foreach ( $terms as $term ) {
            // Skip si déjà une couleur
            if ( isset( $opts['term_colors'][ $taxo_slug ][ $term->slug ] ) ) {
                continue;
            }
            // Skip si déjà touché manuellement (utilisatrice a délibérément retiré sa couleur)
            if ( isset( $touched[ $taxo_slug ][ $term->slug ] ) ) {
                continue;
            }
            // Première couleur libre, sinon cycle
            $chosen = null;
            for ( $i = 0; $i < $palette_size; $i++ ) {
                if ( ! in_array( $i, $used, true ) ) {
                    $chosen = $i;
                    break;
                }
            }
            if ( null === $chosen ) {
                $chosen = count( $used ) % $palette_size;
            }
            $opts['term_colors'][ $taxo_slug ][ $term->slug ] = $chosen;
            $used[] = $chosen;
            // Marquer comme touché : l'auto-attribution compte aussi comme une décision
            if ( ! isset( $touched[ $taxo_slug ] ) ) {
                $touched[ $taxo_slug ] = array();
            }
            $touched[ $taxo_slug ][ $term->slug ] = true;
        }
    }

    // Nettoyer le touched de toute taxo inexistante
    foreach ( array_keys( $touched ) as $taxo_slug ) {
        if ( ! taxonomy_exists( $taxo_slug ) ) {
            unset( $touched[ $taxo_slug ] );
        }
    }
    $opts['term_colors_touched'] = $touched;

    // 6. ÉCRITURE UNIQUE en base
    update_option( PAGERIBBON_OPTION_KEY, $opts );

    // 7. Redirection avec message de succès
    wp_safe_redirect( add_query_arg(
        array(
            'post_type'        => 'page',
            'page'             => 'pageribbon',
            'pageribbon_saved' => '1',
        ),
        admin_url( 'edit.php' )
    ) );
    exit;
}


/* ============================================================
 *  RENDU DE LA PAGE
 * ============================================================ */
function pageribbon_render_settings_page() {

    if ( ! current_user_can( 'manage_categories' ) ) {
        return;
    }

    // Bootstrap : active automatiquement la première taxo au premier accès
    pageribbon_bootstrap_first_taxonomy();

    $is_enabled       = pageribbon_is_enabled();
    $colorable_taxos  = pageribbon_get_colorable_taxonomies();
    $enabled_taxos    = (array) pageribbon_get_setting( 'enabled_taxonomies', array() );
    $term_colors      = (array) pageribbon_get_setting( 'term_colors', array() );
    $palette          = pageribbon_get_palette();
    $taxo_labels      = pageribbon_get_taxo_labels();
    $supported_pts    = pageribbon_get_supported_post_types();
    // Stats des pages non classées (pour le compteur discret)
    $unclassified_stats = pageribbon_get_unclassified_pages_stats();

    ?>
    <div class="wrap pageribbon-wrap">
        <h1><?php esc_html_e( 'PageRibbon', 'pageribbon' ); ?>
            <span style="font-size: 13px; color: #50575e; font-weight: normal;">v<?php echo esc_html( PAGERIBBON_VERSION ); ?></span>
        </h1>
        <p class="description" style="font-size: 14px;">
            <?php esc_html_e( 'La cartographie visuelle de vos pages pour mieux les gérer au quotidien. Désactivable en un clic.', 'pageribbon' ); ?>
        </p>

        <?php if ( ! empty( $_GET['pageribbon_saved'] ) ) : ?>
            <div class="notice notice-success is-dismissible">
                <p><?php esc_html_e( 'Réglages enregistrés.', 'pageribbon' ); ?></p>
            </div>
        <?php endif; ?>

        <form method="post" action="">
            <?php wp_nonce_field( 'pageribbon_save_settings' ); ?>

            <div class="pageribbon-minihero">
                <div class="pageribbon-minihero__ribbon" aria-hidden="true">
                    <span style="background:#7f77dd;"></span>
                    <span style="background:#d4537e;"></span>
                    <span style="background:#e08a5b;"></span>
                </div>
                <div class="pageribbon-minihero__body">
                    <h2 class="pageribbon-minihero__title"><?php esc_html_e( 'Rangez vos pages en couleur', 'pageribbon' ); ?></h2>
                    <p class="pageribbon-minihero__sub">
                        <?php
                        printf(
                            /* translators: 1: libellé modèle, 2: libellé famille */
                            esc_html__( 'PageRibbon range vos pages selon deux axes : le %1$s (la mise en page) et la %2$s (le groupe sémantique). Le modèle prime : quand une page a un modèle coloré, c\'est sa couleur qui s\'affiche ; la famille prend le relais sinon.', 'pageribbon' ),
                            '<strong>' . esc_html( $taxo_labels['modele']['singular'] ) . '</strong>',
                            '<strong>' . esc_html( $taxo_labels['famille']['singular'] ) . '</strong>'
                        );
                        ?>
                    </p>
                    <p class="pageribbon-minihero__cta"><?php esc_html_e( 'Donnez une couleur à chacun, puis classez vos pages.', 'pageribbon' ); ?></p>
                </div>
            </div>

            <?php /* === ALERTE : PAGES NON CLASSÉES (placée avant tout, comme une alerte) === */ ?>
            <?php if ( $is_enabled && $unclassified_stats['total'] > 0 && $unclassified_stats['no_famille_or_modele'] > 0 ) : ?>
                <div class="pageribbon-alert">
                    <span class="pageribbon-alert__icon" aria-hidden="true">!</span>
                    <div class="pageribbon-alert__body">
                        <strong class="pageribbon-alert__count"><?php echo esc_html( $unclassified_stats['no_famille_or_modele'] ); ?></strong>
                        <?php
                        printf(
                            /* translators: 1: nombre de pages non classées, 2: nombre total de pages */
                            esc_html( _n(
                                'page non classée sur %2$s (sans modèle ou sans famille).',
                                'pages non classées sur %2$s (sans modèle ou sans famille).',
                                $unclassified_stats['no_famille_or_modele'],
                                'pageribbon'
                            ) ),
                            (int) $unclassified_stats['no_famille_or_modele'],
                            (int) $unclassified_stats['total']
                        );
                        ?>
                    </div>
                    <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=page' ) ); ?>" class="button">
                        <?php esc_html_e( 'Aller à la liste des pages', 'pageribbon' ); ?>
                    </a>
                </div>
            <?php endif; ?>

            <?php /* === 1. KILL-SWITCH GLOBAL (interrupteur principal) === */ ?>
            <div class="pageribbon-card pageribbon-killswitch <?php echo $is_enabled ? '' : 'is-off'; ?>">
                <h2>
                    <?php if ( $is_enabled ) : ?>
                        🎨 <?php esc_html_e( 'PageRibbon : ACTIVÉ', 'pageribbon' ); ?>
                    <?php else : ?>
                        🚫 <?php esc_html_e( 'PageRibbon : DÉSACTIVÉ', 'pageribbon' ); ?>
                    <?php endif; ?>
                </h2>
                <p class="description" style="margin-bottom: 16px;">
                    <?php esc_html_e( 'Interrupteur principal de PageRibbon. Quand il est activé, vos pages affichent leurs couleurs de classement à deux endroits : dans la liste des pages de l\'administration, et sous forme de ruban coloré pendant l\'édition d\'une page. Le désactiver fait disparaître tous ces repères d\'un seul clic.', 'pageribbon' ); ?>
                </p>
                <div class="pageribbon-toggle">
                    <input type="checkbox" name="pageribbon_enabled" id="pageribbon_enabled" value="1" <?php checked( $is_enabled ); ?> />
                    <label for="pageribbon_enabled" style="font-size: 15px;">
                        <strong><?php esc_html_e( 'Activer PageRibbon sur ce site', 'pageribbon' ); ?></strong>
                    </label>
                </div>
            </div>

            <?php /* === 2. CONTEXTES D'AFFICHAGE (où afficher les marqueurs) === */ ?>
            <div class="pageribbon-card">
                <h2><?php esc_html_e( 'Où afficher les marqueurs ?', 'pageribbon' ); ?></h2>
                <p class="description">
                    <?php esc_html_e( 'Activez ou désactivez chaque contexte indépendamment.', 'pageribbon' ); ?>
                </p>

                <div class="pageribbon-toggle">
                    <input type="checkbox" name="pageribbon_show_in_admin_list" id="show_in_admin_list" value="1"
                        <?php checked( pageribbon_get_setting( 'show_in_admin_list', true ) ); ?> />
                    <label for="show_in_admin_list">
                        <strong><?php esc_html_e( 'Liste des pages (admin)', 'pageribbon' ); ?></strong> —
                        <?php esc_html_e( 'colore les lignes dans Pages → Toutes les pages.', 'pageribbon' ); ?>
                    </label>
                </div>

                <div class="pageribbon-sub-toggles">
                    <div class="pageribbon-toggle pageribbon-sub-toggle">
                        <input type="checkbox" name="pageribbon_admin_list_show_stripe" id="admin_list_show_stripe" value="1"
                            <?php checked( pageribbon_get_setting( 'admin_list_show_stripe', true ) ); ?> />
                        <label for="admin_list_show_stripe">
                            <?php esc_html_e( 'Filet vertical coloré à gauche de la ligne', 'pageribbon' ); ?>
                        </label>
                    </div>
                    <div class="pageribbon-toggle pageribbon-sub-toggle">
                        <input type="checkbox" name="pageribbon_admin_list_show_dot" id="admin_list_show_dot" value="1"
                            <?php checked( pageribbon_get_setting( 'admin_list_show_dot', true ) ); ?> />
                        <label for="admin_list_show_dot">
                            <?php esc_html_e( 'Pastille colorée devant le titre de la page', 'pageribbon' ); ?>
                        </label>
                    </div>
                    <div class="pageribbon-toggle pageribbon-sub-toggle">
                        <input type="checkbox" name="pageribbon_admin_list_show_bg" id="admin_list_show_bg" value="1"
                            <?php checked( pageribbon_get_setting( 'admin_list_show_bg', true ) ); ?> />
                        <label for="admin_list_show_bg">
                            <?php esc_html_e( 'Fond pastel sur toute la ligne', 'pageribbon' ); ?>
                        </label>
                    </div>
                </div>

                <div class="pageribbon-toggle">
                    <input type="checkbox" name="pageribbon_show_in_editor" id="show_in_editor" value="1"
                        <?php checked( pageribbon_get_setting( 'show_in_editor', true ) ); ?> />
                    <label for="show_in_editor">
                        <strong><?php esc_html_e( 'Éditeur de pages', 'pageribbon' ); ?></strong> —
                        <?php esc_html_e( 'pendant que vous travaillez une page, un bandeau coloré et un panneau latéral rappellent à quel modèle et à quelle famille elle appartient. Le bon repère, au bon moment, sans quitter l\'édition.', 'pageribbon' ); ?>
                    </label>
                </div>
            </div>

            <?php /* === LIBELLÉS DES TAXONOMIES (conservés en champs cachés) ===
                Le renommage n'est plus proposé dans l'UI (remplacé par le mini-hero
                en tête de page), mais on préserve les valeurs déjà enregistrées
                pour ne pas les écraser au moment du save. */ ?>
            <input type="hidden" name="pageribbon_taxo_labels[modele][singular]" value="<?php echo esc_attr( $taxo_labels['modele']['singular'] ); ?>" />
            <input type="hidden" name="pageribbon_taxo_labels[modele][plural]" value="<?php echo esc_attr( $taxo_labels['modele']['plural'] ); ?>" />
            <input type="hidden" name="pageribbon_taxo_labels[famille][singular]" value="<?php echo esc_attr( $taxo_labels['famille']['singular'] ); ?>" />
            <input type="hidden" name="pageribbon_taxo_labels[famille][plural]" value="<?php echo esc_attr( $taxo_labels['famille']['plural'] ); ?>" />


            <?php /* === 5. CHOIX DES TAXONOMIES À COLORER === */ ?>
            <div class="pageribbon-card">
                <h2><?php esc_html_e( 'Que voulez-vous distinguer ?', 'pageribbon' ); ?></h2>
                <p class="description">
                    <?php esc_html_e( 'Cochez les groupes de contenu à colorer. Cela inclut vos catégories d\'articles, vos taxonomies WooCommerce, et toute autre taxonomie hiérarchique présente sur votre site.', 'pageribbon' ); ?>
                </p>

                <?php if ( empty( $colorable_taxos ) ) : ?>
                    <div class="pageribbon-empty-state">
                        <p><strong><?php esc_html_e( 'Aucun groupe hiérarchique détecté sur ce site.', 'pageribbon' ); ?></strong></p>
                        <p><?php esc_html_e( 'PageRibbon colore les pages selon leurs catégories ou familles. Créez d\'abord une taxonomie hiérarchique attachée à vos pages.', 'pageribbon' ); ?></p>
                    </div>
                <?php else : ?>
                    <?php foreach ( $colorable_taxos as $taxo_slug => $taxo ) :
                        $is_enabled_taxo = in_array( $taxo_slug, $enabled_taxos, true );
                        $post_types_attached = pageribbon_get_taxonomy_post_types( $taxo_slug );
                        $pt_labels = array_map( function( $pt ) { return $pt->labels->name; }, $post_types_attached );
                    ?>
                        <div class="pageribbon-toggle">
                            <input
                                type="checkbox"
                                name="pageribbon_enabled_taxonomies[]"
                                id="taxo_<?php echo esc_attr( $taxo_slug ); ?>"
                                value="<?php echo esc_attr( $taxo_slug ); ?>"
                                <?php checked( $is_enabled_taxo ); ?>
                            />
                            <label for="taxo_<?php echo esc_attr( $taxo_slug ); ?>">
                                <strong><?php echo esc_html( $taxo->labels->name ); ?></strong>
                                <span style="color: #50575e;">
                                    (<?php echo esc_html( implode( ', ', $pt_labels ) ); ?>)
                                </span>
                            </label>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <?php /* === 6. COULEURS PAR TERME === */ ?>
            <?php if ( ! empty( $enabled_taxos ) ) : ?>
                <div class="pageribbon-card">
                    <h2><?php esc_html_e( 'Attribuez une couleur à chaque modèle et famille', 'pageribbon' ); ?></h2>
                    <p class="description">
                        <?php esc_html_e( 'Choisissez une couleur de la palette pour chaque terme, ou laissez « Aucune couleur » pour le garder neutre par défaut. Toutes les couleurs respectent les standards d\'accessibilité WCAG AA.', 'pageribbon' ); ?>
                    </p>
                    <p class="description" style="font-style: italic; margin-top: -4px;">
                        <?php esc_html_e( 'À noter : si une page a à la fois un modèle et une famille colorés, c\'est la couleur du modèle qui s\'affiche. Et si un contenu a plusieurs termes colorés dans un même groupe (par exemple deux catégories), c\'est le premier par ordre alphabétique qui est retenu.', 'pageribbon' ); ?>
                    </p>

                    <?php
                    // Réordonner pour cohérence avec la priorité de coloration (1.3.1) :
                    // Modèle d'abord, puis Famille, puis le reste.
                    $priority_order  = array( 'pageribbon_modele', 'pageribbon_famille' );
                    $ordered_enabled = array();
                    foreach ( $priority_order as $p ) {
                        if ( in_array( $p, $enabled_taxos, true ) ) {
                            $ordered_enabled[] = $p;
                        }
                    }
                    foreach ( $enabled_taxos as $t ) {
                        if ( ! in_array( $t, $ordered_enabled, true ) ) {
                            $ordered_enabled[] = $t;
                        }
                    }
                    ?>

                    <?php foreach ( $ordered_enabled as $taxo_slug ) :
                        if ( ! isset( $colorable_taxos[ $taxo_slug ] ) ) {
                            continue;
                        }
                        $taxo = $colorable_taxos[ $taxo_slug ];
                        $terms = get_terms( array(
                            'taxonomy'   => $taxo_slug,
                            'hide_empty' => false,
                            'orderby'    => 'name',
                            'order'      => 'ASC',
                        ) );
                    ?>
                        <div class="pageribbon-taxo-block">
                            <h3><?php echo esc_html( $taxo->labels->name ); ?></h3>

                            <?php if ( empty( $terms ) || is_wp_error( $terms ) ) : ?>
                                <p class="description"><?php esc_html_e( 'Aucun terme dans cette taxonomie pour l\'instant.', 'pageribbon' ); ?></p>
                            <?php else : ?>
                                <div class="pageribbon-terms-grid">
                                    <?php foreach ( $terms as $term ) :
                                        $current_index = isset( $term_colors[ $taxo_slug ][ $term->slug ] )
                                            ? (int) $term_colors[ $taxo_slug ][ $term->slug ]
                                            : -1;
                                        $current_color = $current_index >= 0 ? pageribbon_get_color( $current_index ) : null;
                                    ?>
                                        <div class="pageribbon-term-row">
                                            <span
                                                class="pageribbon-preview"
                                                style="<?php echo $current_color
                                                    ? 'background:' . esc_attr( $current_color['bg'] ) . ';border-color:' . esc_attr( $current_color['border'] ) . ';'
                                                    : 'background:#fff;border-color:#d1d5db;border-style:dashed;'; ?>"
                                                data-preview="<?php echo esc_attr( $taxo_slug . ':' . $term->slug ); ?>"
                                            ></span>
                                            <strong><?php echo esc_html( $term->name ); ?></strong>
                                            <select
                                                name="pageribbon_term_colors[<?php echo esc_attr( $taxo_slug ); ?>][<?php echo esc_attr( $term->slug ); ?>]"
                                                data-target="<?php echo esc_attr( $taxo_slug . ':' . $term->slug ); ?>"
                                                class="pageribbon-color-select"
                                            >
                                                <option value="-1" <?php selected( $current_index, -1 ); ?>>
                                                    — <?php esc_html_e( 'Aucune couleur', 'pageribbon' ); ?> —
                                                </option>
                                                <?php foreach ( $palette as $idx => $color ) : ?>
                                                    <option
                                                        value="<?php echo esc_attr( $idx ); ?>"
                                                        data-bg="<?php echo esc_attr( $color['bg'] ); ?>"
                                                        data-border="<?php echo esc_attr( $color['border'] ); ?>"
                                                        <?php selected( $current_index, $idx ); ?>
                                                    >
                                                        <?php echo esc_html( $color['label'] ); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>


            <p class="submit">
                <button type="submit" name="pageribbon_submit" value="1" class="button button-primary button-large">
                    <?php esc_html_e( 'Enregistrer les réglages', 'pageribbon' ); ?>
                </button>
            </p>
        </form>
    </div>
    <?php
}


/* ============================================================
 *  STATS : PAGES NON CLASSÉES
 *
 *  Compte les pages qui n'ont aucun terme dans pageribbon_famille
 *  ou pageribbon_modele. Aide l'utilisatrice à finir son classement.
 *
 *  Mis en cache pour ne pas faire 2 grosses requêtes à chaque load de la
 *  page de réglages. Le transient est invalidé quand on modifie une taxo.
 * ============================================================ */
function pageribbon_get_unclassified_pages_stats() {

    $cached = get_transient( 'pageribbon_unclassified_stats' );
    if ( false !== $cached && is_array( $cached ) ) {
        return $cached;
    }

    $stats = array(
        'total'             => 0,
        'no_famille'        => 0,
        'no_modele'         => 0,
        'no_famille_or_modele' => 0,
    );

    // Compte total de pages publiées + brouillons
    $total = wp_count_posts( 'page' );
    if ( $total ) {
        $stats['total'] = (int) $total->publish + (int) $total->draft + (int) $total->pending + (int) $total->private;
    }

    if ( 0 === $stats['total'] ) {
        set_transient( 'pageribbon_unclassified_stats', $stats, HOUR_IN_SECONDS );
        return $stats;
    }

    // Pages sans Famille
    if ( taxonomy_exists( 'pageribbon_famille' ) ) {
        $no_famille = new WP_Query( array(
            'post_type'      => 'page',
            'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'tax_query'      => array(
                array(
                    'taxonomy' => 'pageribbon_famille',
                    'operator' => 'NOT EXISTS',
                ),
            ),
            'no_found_rows'  => true,
        ) );
        $stats['no_famille'] = $no_famille->post_count;
    }

    // Pages sans Modèle
    if ( taxonomy_exists( 'pageribbon_modele' ) ) {
        $no_modele = new WP_Query( array(
            'post_type'      => 'page',
            'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'tax_query'      => array(
                array(
                    'taxonomy' => 'pageribbon_modele',
                    'operator' => 'NOT EXISTS',
                ),
            ),
            'no_found_rows'  => true,
        ) );
        $stats['no_modele'] = $no_modele->post_count;
    }

    // Pages sans Famille OU sans Modèle (union)
    if ( taxonomy_exists( 'pageribbon_famille' ) && taxonomy_exists( 'pageribbon_modele' ) ) {
        $either = new WP_Query( array(
            'post_type'      => 'page',
            'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'tax_query'      => array(
                'relation' => 'OR',
                array(
                    'taxonomy' => 'pageribbon_famille',
                    'operator' => 'NOT EXISTS',
                ),
                array(
                    'taxonomy' => 'pageribbon_modele',
                    'operator' => 'NOT EXISTS',
                ),
            ),
            'no_found_rows'  => true,
        ) );
        $stats['no_famille_or_modele'] = $either->post_count;
    }

    set_transient( 'pageribbon_unclassified_stats', $stats, HOUR_IN_SECONDS );
    return $stats;
}


/**
 * Invalide le cache des stats quand une taxo est modifiée sur un post.
 */
add_action( 'set_object_terms', 'pageribbon_invalidate_unclassified_stats_cache' );
add_action( 'deleted_term_relationships', 'pageribbon_invalidate_unclassified_stats_cache' );

function pageribbon_invalidate_unclassified_stats_cache() {
    delete_transient( 'pageribbon_unclassified_stats' );
}
