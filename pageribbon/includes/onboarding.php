<?php
/**
 * PageRibbon — Écran d'accueil / onboarding
 *
 * Deux surfaces complémentaires sur le Tableau de bord :
 *
 *   1. HERO PLEINE LARGEUR (welcome_panel)
 *      Un panneau de présentation large, façon « page produit », affiché tout
 *      en haut du Tableau de bord, au-dessus de la grille des widgets. Logo,
 *      pitch, aperçu visuel de la coloration, et 3 features clés.
 *
 *   2. WIDGET COLONNE (wp_add_dashboard_widget)
 *      Le petit widget compact conservé dans la grille, pour un rappel rapide
 *      des liens utiles une fois le hero replié.
 *
 * Visibles pour les rôles qui gèrent les termes (manage_categories) : admin
 * ET éditeur, ce qui couvre le compte bêta-testeur du bac à sable.
 *
 * @package PageRibbon
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


/* ============================================================
 *  LIENS & DONNÉES PARTAGÉS
 * ============================================================ */
function pageribbon_onboarding_links() {
    return array(
        'settings' => admin_url( 'edit.php?post_type=page&page=pageribbon' ),
        'pages'    => admin_url( 'edit.php?post_type=page' ),
        'modeles'  => admin_url( 'edit-tags.php?taxonomy=' . PAGERIBBON_TAXO_MODELE . '&post_type=page' ),
        'familles' => admin_url( 'edit-tags.php?taxonomy=' . PAGERIBBON_TAXO_FAMILLE . '&post_type=page' ),
    );
}


/* ============================================================
 *  1. HERO PLEINE LARGEUR (en tête du Tableau de bord)
 *
 *  IMPORTANT : on n'utilise PAS le hook natif `welcome_panel` de WordPress.
 *  En effet, le welcome panel n'est rendu que pour les utilisateurs ayant la
 *  capacité `edit_theme_options` — capacité réservée aux administrateurs.
 *  Un éditeur (notre bêta-testeur) ne l'a pas, donc le panneau n'existe pas
 *  pour lui et notre hero ne s'afficherait jamais.
 *
 *  À la place, on injecte le hero directement en tête de l'écran du Tableau
 *  de bord via `in_admin_header`, sous garde `manage_categories`. Ce hook
 *  s'exécute pour tous les rôles ; on le restreint nous-mêmes à l'écran
 *  'dashboard' et à la bonne capacité. Résultat : hero pleine largeur visible
 *  pour l'admin ET l'éditeur.
 * ============================================================ */
add_action( 'in_admin_header', 'pageribbon_render_hero' );

/**
 * La case « Options de l'écran » du Tableau de bord est-elle cochée pour
 * l'utilisateur courant ? Affiché par défaut (true) tant qu'il ne l'a pas
 * explicitement masqué.
 *
 * @return bool
 */
function pageribbon_hero_is_visible_for_current_user() {
    $pref = get_user_meta( get_current_user_id(), 'pageribbon_hide_hero', true );
    // '1' = masqué explicitement ; toute autre valeur (vide) = affiché.
    return '1' !== $pref;
}


/* ============================================================
 *  CASE « OPTIONS DE L'ÉCRAN » POUR MASQUER LE HERO
 *
 *  Sur le welcome_panel natif, WordPress fournissait gratuitement une case
 *  de masquage. Comme on a migré vers in_admin_header (pour la compat éditeur),
 *  on recrée nous-mêmes cette case dans « Options de l'écran » du Tableau de
 *  bord. La préférence est stockée par utilisateur (user_meta), donc chacun
 *  gère son propre affichage.
 * ============================================================ */
add_filter( 'screen_settings', 'pageribbon_hero_screen_settings', 10, 2 );

function pageribbon_hero_screen_settings( $settings, $screen ) {

    if ( ! $screen || 'dashboard' !== $screen->id ) {
        return $settings;
    }
    if ( ! current_user_can( 'manage_categories' ) ) {
        return $settings;
    }

    $checked = pageribbon_hero_is_visible_for_current_user();

    ob_start();
    ?>
    <fieldset class="pageribbon-hero-screen-option">
        <legend class="screen-layout"><?php esc_html_e( 'PageRibbon', 'pageribbon' ); ?></legend>
        <label for="pageribbon_show_hero">
            <input
                type="checkbox"
                id="pageribbon_show_hero"
                value="1"
                <?php checked( $checked ); ?>
                data-nonce="<?php echo esc_attr( wp_create_nonce( 'pageribbon_hero_toggle' ) ); ?>"
            />
            <?php esc_html_e( 'Afficher le panneau d\'accueil PageRibbon', 'pageribbon' ); ?>
        </label>
    </fieldset>
    <?php
    return $settings . ob_get_clean();
}

/**
 * Charge le script de bascule AJAX sur l'écran du Tableau de bord.
 * La case « Options de l'écran » est enregistrée par WordPress via AJAX
 * (pas de rechargement de page), donc on écoute son changement et on
 * persiste notre préférence par AJAX, en masquant/affichant le hero en direct.
 */
add_action( 'admin_print_footer_scripts-index.php', 'pageribbon_hero_toggle_script' );

function pageribbon_hero_toggle_script() {
    if ( ! current_user_can( 'manage_categories' ) ) {
        return;
    }
    ?>
    <script>
    ( function () {
        var cb = document.getElementById( 'pageribbon_show_hero' );
        if ( ! cb ) { return; }
        cb.addEventListener( 'change', function () {
            var show = cb.checked ? 1 : 0;
            var wrap = document.querySelector( '.pageribbon-hero-wrap' );
            if ( wrap ) { wrap.style.display = show ? '' : 'none'; }
            var body = new URLSearchParams();
            body.append( 'action', 'pageribbon_toggle_hero' );
            body.append( 'show', show );
            body.append( 'nonce', cb.getAttribute( 'data-nonce' ) );
            fetch( ajaxurl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body.toString()
            } );
        } );
    } )();
    </script>
    <?php
}

/**
 * Endpoint AJAX : enregistre la préférence d'affichage du hero (par utilisateur).
 */
add_action( 'wp_ajax_pageribbon_toggle_hero', 'pageribbon_hero_ajax_save' );

function pageribbon_hero_ajax_save() {

    if ( ! current_user_can( 'manage_categories' ) ) {
        wp_send_json_error( 'forbidden', 403 );
    }
    if ( ! check_ajax_referer( 'pageribbon_hero_toggle', 'nonce', false ) ) {
        wp_send_json_error( 'bad_nonce', 400 );
    }

    // show=1 => visible => on retire la meta de masquage. show=0 => masqué.
    $show = isset( $_POST['show'] ) ? (int) $_POST['show'] : 1;
    if ( 0 === $show ) {
        update_user_meta( get_current_user_id(), 'pageribbon_hide_hero', '1' );
    } else {
        delete_user_meta( get_current_user_id(), 'pageribbon_hide_hero' );
    }

    wp_send_json_success( array( 'show' => $show ) );
}



function pageribbon_render_hero() {

    // Uniquement sur l'écran du Tableau de bord (index.php).
    $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
    if ( ! $screen || 'dashboard' !== $screen->id ) {
        return;
    }

    if ( ! current_user_can( 'manage_categories' ) ) {
        return;
    }

    // Préférence par utilisateur (case « Options de l'écran »). Affiché par défaut.
    if ( ! pageribbon_hero_is_visible_for_current_user() ) {
        return;
    }

    $links   = pageribbon_onboarding_links();
    $labels  = pageribbon_get_taxo_labels();
    $logo    = PAGERIBBON_URL . 'assets/img/logo.png';
    $enabled = pageribbon_is_enabled();

    // Couleurs de marque (cohérentes avec le logo et la palette du plugin)
    $brand = array(
        'violet' => '#7f77dd',
        'rose'   => '#d4537e',
        'orange' => '#e08a5b',
    );
    ?>
    <div class="pageribbon-hero-wrap">
    <div class="pageribbon-hero">

        <div class="pageribbon-hero__head">
            <div class="pageribbon-hero__pitch">
                <img src="<?php echo esc_url( $logo ); ?>" alt="PageRibbon" class="pageribbon-hero__logo" />
                <h2 class="pageribbon-hero__title"><?php esc_html_e( 'Rangez vos pages WordPress en couleur', 'pageribbon' ); ?></h2>
                <p class="pageribbon-hero__sub">
                    <?php esc_html_e( 'PageRibbon transforme votre liste de pages en une cartographie visuelle : chaque page prend une couleur selon son classement. Repérez en un coup d\'œil ce qui appartient à quoi.', 'pageribbon' ); ?>
                </p>
                <?php if ( ! $enabled ) : ?>
                    <p class="pageribbon-hero__badge-off"><?php esc_html_e( 'Mode visuel désactivé — réactivez-le dans les réglages pour voir les couleurs.', 'pageribbon' ); ?></p>
                <?php endif; ?>
                <div class="pageribbon-hero__cta">
                    <a href="<?php echo esc_url( $links['settings'] ); ?>" class="button button-primary button-hero"><?php esc_html_e( 'Configurer PageRibbon', 'pageribbon' ); ?></a>
                    <a href="<?php echo esc_url( $links['pages'] ); ?>" class="button button-hero"><?php esc_html_e( 'Voir mes pages', 'pageribbon' ); ?></a>
                </div>
                <p class="pageribbon-hero__voie">
                    <?php
                    printf(
                        /* translators: 1: lien vers les modèles, 2: lien vers les familles */
                        esc_html__( 'Créez et gérez vos %1$s et %2$s, puis donnez-leur une couleur. Vos pages sont classées.', 'pageribbon' ),
                        '<a href="' . esc_url( $links['modeles'] ) . '">' . esc_html( strtolower( $labels['modele']['plural'] ) ) . '</a>',
                        '<a href="' . esc_url( $links['familles'] ) . '">' . esc_html( strtolower( $labels['famille']['plural'] ) ) . '</a>'
                    );
                    ?>
                </p>
            </div>

            <?php /* Aperçu : reproduction du style « 3 cartes » de la marque, en SVG inline */ ?>
            <div class="pageribbon-hero__preview" aria-hidden="true">
                <svg viewBox="0 0 368 300" xmlns="http://www.w3.org/2000/svg" role="img">
                    <?php
                    $rows = array(
                        array( 'y' => 2,   'c' => $brand['violet'], 'w' => 200 ),
                        array( 'y' => 102, 'c' => $brand['rose'],   'w' => 160 ),
                        array( 'y' => 202, 'c' => $brand['orange'], 'w' => 180 ),
                    );
                    foreach ( $rows as $r ) :
                        $cy = $r['y'] + 48;
                    ?>
                        <rect x="2" y="<?php echo (int) $r['y']; ?>" width="364" height="92" rx="12" fill="#fff" stroke="#e6e3da" stroke-width="2"/>
                        <rect x="2" y="<?php echo (int) $r['y']; ?>" width="11" height="92" rx="5" fill="<?php echo esc_attr( $r['c'] ); ?>"/>
                        <circle cx="48" cy="<?php echo (int) $cy; ?>" r="15" fill="<?php echo esc_attr( $r['c'] ); ?>"/>
                        <rect x="80" y="<?php echo (int) ( $r['y'] + 34 ); ?>" width="<?php echo (int) $r['w']; ?>" height="13" rx="6" fill="#d7d4cb"/>
                        <rect x="80" y="<?php echo (int) ( $r['y'] + 56 ); ?>" width="<?php echo (int) ( $r['w'] - 60 ); ?>" height="11" rx="5" fill="#e7e4db"/>
                    <?php endforeach; ?>
                </svg>
            </div>
        </div>

        <div class="pageribbon-hero__features">
            <div class="pageribbon-hero__feature">
                <span class="pageribbon-hero__fcolor" style="background: <?php echo esc_attr( $brand['violet'] ); ?>;"></span>
                <h3><?php echo esc_html( $labels['modele']['plural'] ); ?> &amp; <?php echo esc_html( $labels['famille']['plural'] ); ?></h3>
                <p><?php esc_html_e( 'Deux axes pour ranger vos pages : la mise en page (modèle) et le groupe sémantique (famille).', 'pageribbon' ); ?></p>
            </div>
            <div class="pageribbon-hero__feature">
                <span class="pageribbon-hero__fcolor" style="background: <?php echo esc_attr( $brand['rose'] ); ?>;"></span>
                <h3><?php esc_html_e( 'Couleurs accessibles', 'pageribbon' ); ?></h3>
                <p><?php esc_html_e( 'Une palette pastel conforme WCAG AA. Filet, pastille et fond colorés directement dans la liste des pages.', 'pageribbon' ); ?></p>
            </div>
            <div class="pageribbon-hero__feature">
                <span class="pageribbon-hero__fcolor" style="background: <?php echo esc_attr( $brand['orange'] ); ?>;"></span>
                <h3><?php esc_html_e( 'Désactivable en 1 clic', 'pageribbon' ); ?></h3>
                <p><?php esc_html_e( 'Un interrupteur global fait disparaître tous les marqueurs. Aucun impact sur les visiteurs du site public.', 'pageribbon' ); ?></p>
            </div>
        </div>
    </div>
    </div>

    <style>
        /* ===== Hero PageRibbon — refined / premium =====
           Rendu via in_admin_header : on lui donne sa propre carte, ses marges,
           et on neutralise le décalage du wrap admin natif (margin négatif). */
        .pageribbon-hero-wrap { margin: 20px 20px 0; }
        @media (max-width: 782px) { .pageribbon-hero-wrap { margin: 12px 10px 0; } }
        .pageribbon-hero { --pr-violet:#7f77dd; --pr-rose:#d4537e; --pr-orange:#e08a5b; --pr-ink:#1f2328; --pr-muted:#5f5e5a; --pr-line:#e6e3da; --pr-cream:#f4f2ec;
            box-sizing: border-box; max-width: 1200px;
            background: #fff; border: 1px solid #c3c4c7; border-radius: 8px; box-shadow: 0 1px 2px rgba(0,0,0,.04);
            padding: 28px 32px;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; color: var(--pr-ink); }
        .pageribbon-hero * { box-sizing: border-box; }
        .pageribbon-hero__head { display: grid; grid-template-columns: minmax(0, 1fr) minmax(260px, 360px); gap: 36px; align-items: center; }
        @media (max-width: 1100px) { .pageribbon-hero__head { grid-template-columns: 1fr; gap: 24px; } .pageribbon-hero__preview { order: -1; max-width: 420px; } }
        .pageribbon-hero__pitch { min-width: 0; }
        .pageribbon-hero__logo { height: 48px; width: auto; margin: 0 0 16px; display: block; }
        .pageribbon-hero__title { font-size: 26px; line-height: 1.15; margin: 0 0 12px; font-weight: 800; letter-spacing: -0.02em; color: var(--pr-ink); }
        .pageribbon-hero__sub { font-size: 15px; line-height: 1.55; color: var(--pr-muted); margin: 0 0 18px; max-width: 56ch; }
        .pageribbon-hero__badge-off { display: inline-block; background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; border-radius: 999px; padding: 5px 14px; font-size: 12.5px; font-weight: 600; margin: 0 0 16px; }
        .pageribbon-hero__cta { display: flex; gap: 10px; flex-wrap: wrap; margin: 0; }
        .pageribbon-hero__voie { margin: 14px 0 0; font-size: 14px; line-height: 1.5; color: var(--pr-muted); }
        .pageribbon-hero__voie a { font-weight: 600; text-decoration: none; color: var(--pr-violet); }
        .pageribbon-hero__voie a:hover { text-decoration: underline; }
        .pageribbon-hero .button-primary { background: var(--pr-violet); border-color: var(--pr-violet); }
        .pageribbon-hero .button-primary:hover { background: #6a62cf; border-color: #6a62cf; }
        .pageribbon-hero__preview { min-width: 0; }
        .pageribbon-hero__preview svg { width: 100%; height: auto; display: block; filter: drop-shadow(0 8px 24px rgba(31,35,40,.10)); }
        .pageribbon-hero__features { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px; margin: 28px 0 0; padding-top: 24px; border-top: 1px solid var(--pr-line); }
        @media (max-width: 782px) { .pageribbon-hero__features { grid-template-columns: 1fr; } }
        .pageribbon-hero__feature { background: var(--pr-cream); border: 1px solid var(--pr-line); border-radius: 12px; padding: 18px 18px 16px; }
        .pageribbon-hero__fcolor { display: block; width: 36px; height: 6px; border-radius: 999px; margin-bottom: 12px; }
        .pageribbon-hero__feature h3 { margin: 0 0 6px; font-size: 15px; font-weight: 700; color: var(--pr-ink); }
        .pageribbon-hero__feature p { margin: 0; font-size: 13px; line-height: 1.5; color: var(--pr-muted); }
    </style>
    <?php
}


/* ============================================================
 *  2. WIDGET COLONNE (compact)
 * ============================================================ */
add_action( 'wp_dashboard_setup', 'pageribbon_register_dashboard_widget' );

function pageribbon_register_dashboard_widget() {

    if ( ! current_user_can( 'manage_categories' ) ) {
        return;
    }

    wp_add_dashboard_widget(
        'pageribbon_welcome',
        '🎨 ' . __( 'PageRibbon — accès rapide', 'pageribbon' ),
        'pageribbon_render_dashboard_widget'
    );
}

function pageribbon_render_dashboard_widget() {

    $links        = pageribbon_onboarding_links();
    $labels       = pageribbon_get_taxo_labels();
    $is_enabled   = pageribbon_is_enabled();

    $demo_a = pageribbon_get_color( 5 ); // Turquoise
    $demo_b = pageribbon_get_color( 6 ); // Corail
    ?>
    <div class="pageribbon-welcome">
        <p class="pageribbon-welcome-intro">
            <?php
            printf(
                /* translators: 1: libellé modèles, 2: libellé familles */
                esc_html__( 'Deux axes pour ranger vos pages : les %1$s et les %2$s. Donnez-leur une couleur, puis classez vos pages.', 'pageribbon' ),
                '<strong>' . esc_html( $labels['modele']['plural'] ) . '</strong>',
                '<strong>' . esc_html( $labels['famille']['plural'] ) . '</strong>'
            );
            ?>
        </p>

        <div class="pageribbon-welcome-demo">
            <span class="pageribbon-welcome-row" style="border-left-color: <?php echo esc_attr( $demo_a['border'] ); ?>; background: <?php echo esc_attr( $demo_a['bg'] ); ?>; color: <?php echo esc_attr( $demo_a['text'] ); ?>;">
                <span class="pageribbon-welcome-dot" style="background: <?php echo esc_attr( $demo_a['border'] ); ?>;"></span>
                <?php esc_html_e( 'Mentions légales', 'pageribbon' ); ?>
            </span>
            <span class="pageribbon-welcome-row" style="border-left-color: <?php echo esc_attr( $demo_b['border'] ); ?>; background: <?php echo esc_attr( $demo_b['bg'] ); ?>; color: <?php echo esc_attr( $demo_b['text'] ); ?>;">
                <span class="pageribbon-welcome-dot" style="background: <?php echo esc_attr( $demo_b['border'] ); ?>;"></span>
                <?php esc_html_e( 'Notre équipe', 'pageribbon' ); ?>
            </span>
        </div>

        <?php if ( ! $is_enabled ) : ?>
            <p class="pageribbon-welcome-warn">
                <?php esc_html_e( 'Le mode visuel est désactivé : aucune couleur ne s\'affiche tant qu\'il n\'est pas réactivé.', 'pageribbon' ); ?>
            </p>
        <?php endif; ?>

        <p class="pageribbon-welcome-cta">
            <a href="<?php echo esc_url( $links['settings'] ); ?>" class="button button-primary"><?php esc_html_e( 'Réglages', 'pageribbon' ); ?></a>
            <a href="<?php echo esc_url( $links['pages'] ); ?>" class="button"><?php esc_html_e( 'Mes pages', 'pageribbon' ); ?></a>
        </p>
    </div>

    <style>
        .pageribbon-welcome-intro { margin-top: 0; font-size: 13px; }
        .pageribbon-welcome-demo { display: flex; flex-direction: column; gap: 6px; margin: 14px 0; }
        .pageribbon-welcome-row { display: flex; align-items: center; gap: 8px; padding: 7px 10px; border-left: 4px solid; border-radius: 3px; font-weight: 600; font-size: 13px; }
        .pageribbon-welcome-dot { display: inline-block; width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
        .pageribbon-welcome-warn { background: #fef2f2; border-left: 4px solid #ef4444; padding: 8px 12px; font-size: 12px; border-radius: 3px; }
        .pageribbon-welcome-cta { margin: 14px 0 4px; display: flex; gap: 8px; flex-wrap: wrap; }
    </style>
    <?php
}
