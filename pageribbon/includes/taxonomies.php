<?php
/**
 * PageRibbon — Création et enregistrement des taxonomies
 *
 * Crée 2 taxonomies hiérarchiques préfixées (pour éviter toute collision) :
 *   - pageribbon_famille (label personnalisable, défaut "Famille de page")
 *   - pageribbon_modele  (label personnalisable, défaut "Modèle de page")
 *
 * Chaque taxonomie peut être attachée à un ensemble de post types choisi
 * par l'utilisateur (par défaut "page").
 *
 * Compatibilité ascendante :
 *   - Si le site a déjà des taxos `famille_page` ou `modele_page` (cas des
 *     anciens plugins RésoNAnces), elles sont automatiquement ajoutées aux
 *     "taxos activées" pour la coloration au premier lancement.
 *
 * @package PageRibbon
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


/* ============================================================
 *  CONSTANTES DES SLUGS INTERNES
 *  Les slugs sont préfixés pour ne JAMAIS entrer en conflit
 *  avec d'autres plugins (CPT UI, ACF, etc.) ou d'anciennes taxos.
 *  Ils sont également non modifiables (seuls les labels le sont).
 * ============================================================ */
define( 'PAGERIBBON_TAXO_FAMILLE', 'pageribbon_famille' );
define( 'PAGERIBBON_TAXO_MODELE',  'pageribbon_modele' );


/* ============================================================
 *  HELPERS DE CONFIGURATION
 * ============================================================ */

/**
 * Retourne les libellés (labels) configurés par l'utilisateur, ou les défauts.
 *
 * @return array { famille: {singular, plural}, modele: {singular, plural} }
 */
function pageribbon_get_taxo_labels() {
    $defaults = array(
        'famille' => array(
            'singular' => __( 'Famille de page', 'pageribbon' ),
            'plural'   => __( 'Familles', 'pageribbon' ),
        ),
        'modele'  => array(
            'singular' => __( 'Modèle de page', 'pageribbon' ),
            'plural'   => __( 'Modèles', 'pageribbon' ),
        ),
    );

    $stored = pageribbon_get_setting( 'taxo_labels', array() );

    // Fusion défensive : si l'utilisateur n'a renseigné qu'un label, on garde le défaut pour l'autre.
    foreach ( $defaults as $key => $val ) {
        foreach ( array( 'singular', 'plural' ) as $part ) {
            if ( empty( $stored[ $key ][ $part ] ) ) {
                $stored[ $key ][ $part ] = $val[ $part ];
            }
        }
    }
    return $stored;
}


/**
 * Retourne les post types sur lesquels enregistrer une taxo donnée.
 *
 * À partir de la 1.4.1, PageRibbon se concentre sur les Pages uniquement par
 * défaut. Cette décision produit reflète le positionnement : le plugin est
 * conçu pour la cartographie de pages, pas pour étendre à d'autres CPT.
 *
 * Pour les devs tiers : le filter `pageribbon_taxo_post_types` permet d'étendre
 * à d'autres post types programmatiquement.
 *
 * Exemple :
 * add_filter( 'pageribbon_taxo_post_types', function( $post_types, $taxo_key ) {
 *     if ( 'famille' === $taxo_key ) {
 *         $post_types[] = 'mon_cpt_custom';
 *     }
 *     return $post_types;
 * }, 10, 2 );
 *
 * @param string $taxo_key 'famille' ou 'modele'.
 * @return array Liste de slugs de post types (toujours au moins 'page').
 */
function pageribbon_get_taxo_post_types( $taxo_key ) {
    /**
     * Filtre les post types ciblés par une taxo PageRibbon.
     *
     * @param array  $post_types Liste de slugs de post types (par défaut ['page']).
     * @param string $taxo_key   'famille' ou 'modele'.
     */
    $post_types = apply_filters(
        'pageribbon_taxo_post_types',
        array( 'page' ),
        $taxo_key
    );

    if ( ! is_array( $post_types ) || empty( $post_types ) ) {
        return array( 'page' );
    }

    // Sécurité : "page" est toujours présent (verrou métier)
    if ( ! in_array( 'page', $post_types, true ) ) {
        $post_types[] = 'page';
    }

    return array_values( array_unique( array_map( 'sanitize_key', $post_types ) ) );
}


/**
 * Retourne tous les post types publics sur lesquels on peut greffer les taxos
 * natives PageRibbon (Famille et Modèle).
 *
 * Exclut les post types techniques (attachment, revision, nav_menu_item).
 * "page" est inclus mais toujours forcé côté handler (verrou de sécurité).
 *
 * @return array Map [slug => WP_Post_Type]
 */
function pageribbon_get_supported_post_types() {
    $excluded = apply_filters( 'pageribbon_excluded_post_types', array(
        'attachment',
        'revision',
        'nav_menu_item',
        'wp_block',
        'wp_template',
        'wp_template_part',
        'wp_navigation',
    ) );

    $pts = get_post_types( array( 'public' => true ), 'objects' );
    foreach ( $excluded as $ex ) {
        unset( $pts[ $ex ] );
    }
    return $pts;
}


/* ============================================================
 *  ENREGISTREMENT DES TAXONOMIES
 *  S'exécute au hook 'init', donc visible partout dans WP.
 * ============================================================ */
add_action( 'init', 'pageribbon_register_taxonomies', 0 );

function pageribbon_register_taxonomies() {

    $labels    = pageribbon_get_taxo_labels();
    $famille_pts = pageribbon_get_taxo_post_types( 'famille' );
    $modele_pts  = pageribbon_get_taxo_post_types( 'modele' );

    // === MODÈLE (enregistré en premier pour apparaître en première colonne admin) ===
    // Note 1.4.1 : on a inversé l'ordre famille/modele d'enregistrement par rapport aux versions
    // précédentes, parce que le Modèle est le critère principal (cf. priorité de coloration 1.3.1).
    // L'ordre d'enregistrement détermine l'ordre des colonnes "show_admin_column".
    register_taxonomy(
        PAGERIBBON_TAXO_MODELE,
        $modele_pts,
        array(
            'hierarchical'      => true,
            'public'            => true,
            'show_ui'           => true,
            'show_admin_column' => true,
            'show_in_rest'      => true,
            'show_in_menu'      => false,
            'show_in_nav_menus' => false,
            'rewrite'           => false,
            'labels'            => pageribbon_build_taxo_labels( $labels['modele']['singular'], $labels['modele']['plural'] ),
        )
    );

    // === FAMILLE (en deuxième colonne) ===
    register_taxonomy(
        PAGERIBBON_TAXO_FAMILLE,
        $famille_pts,
        array(
            'hierarchical'      => true,
            'public'            => true,
            'show_ui'           => true,
            'show_admin_column' => true, // Affiche la colonne dans la liste admin
            'show_in_rest'      => true, // Visible dans l'API REST (nécessaire pour Gutenberg)
            'show_in_menu'      => false, // On gère le menu manuellement (sous "Pages")
            'show_in_nav_menus' => false,
            'rewrite'           => false, // Pas d'URL publique : c'est un outil d'organisation, pas un type d'archive
            'labels'            => pageribbon_build_taxo_labels( $labels['famille']['singular'], $labels['famille']['plural'] ),
        )
    );
}


/**
 * Construit l'array de labels WordPress complet à partir des 2 mots-clés.
 *
 * @param string $singular Ex. "Famille de page"
 * @param string $plural   Ex. "Familles"
 * @return array
 */
function pageribbon_build_taxo_labels( $singular, $plural ) {
    /* translators: %s: nom singulier de la taxonomie (ex. 'Famille de page') */
    $add_new = sprintf( __( 'Ajouter une %s', 'pageribbon' ), $singular );
    /* translators: %s: nom singulier de la taxonomie */
    $edit = sprintf( __( 'Modifier la %s', 'pageribbon' ), $singular );
    /* translators: %s: nom singulier */
    $update = sprintf( __( 'Mettre à jour la %s', 'pageribbon' ), $singular );
    /* translators: %s: nom pluriel */
    $all = sprintf( __( 'Toutes les %s', 'pageribbon' ), $plural );
    /* translators: %s: nom pluriel */
    $search = sprintf( __( 'Rechercher dans les %s', 'pageribbon' ), $plural );

    return array(
        'name'              => $plural,
        'singular_name'     => $singular,
        'menu_name'         => $plural,
        'all_items'         => $all,
        'edit_item'         => $edit,
        'view_item'         => $singular,
        'update_item'       => $update,
        'add_new_item'      => $add_new,
        'new_item_name'     => $singular,
        'parent_item'       => sprintf( __( '%s parente', 'pageribbon' ), $singular ),
        'parent_item_colon' => sprintf( __( '%s parente :', 'pageribbon' ), $singular ),
        'search_items'      => $search,
        'not_found'         => __( 'Aucun élément trouvé.', 'pageribbon' ),
        'back_to_items'     => sprintf( __( '← Retour à %s', 'pageribbon' ), strtolower( $plural ) ),
    );
}


/* ============================================================
 *  AJOUT DES PAGES D'ADMINISTRATION DES TAXOS SOUS "PAGES"
 *  WordPress n'autorise pas show_in_menu => 'edit.php?...' avec
 *  des taxos custom de manière fiable. On ajoute donc manuellement
 *  les sous-menus sous "Pages" en pointant vers les écrans natifs.
 * ============================================================ */
add_action( 'admin_menu', 'pageribbon_add_taxonomy_submenus', 20 );

function pageribbon_add_taxonomy_submenus() {

    $labels = pageribbon_get_taxo_labels();

    add_submenu_page(
        'edit.php?post_type=page',
        $labels['famille']['plural'],
        $labels['famille']['plural'],
        'manage_categories',
        'edit-tags.php?taxonomy=' . PAGERIBBON_TAXO_FAMILLE . '&post_type=page'
    );

    add_submenu_page(
        'edit.php?post_type=page',
        $labels['modele']['plural'],
        $labels['modele']['plural'],
        'manage_categories',
        'edit-tags.php?taxonomy=' . PAGERIBBON_TAXO_MODELE . '&post_type=page'
    );
}


/* ============================================================
 *  AUTO-ATTRIBUTION DE COULEUR À CHAQUE NOUVEAU TERME
 *  Quand l'utilisateur crée un nouveau terme dans une de nos taxos
 *  natives (ou dans une taxo déjà activée pour la coloration), on
 *  lui attribue automatiquement la prochaine couleur de la palette
 *  qui n'est pas encore utilisée par les autres termes de cette taxo.
 *
 *  Comportement zero-config : l'utilisateur n'a pas à ouvrir les
 *  réglages pour voir ses nouveaux termes colorés.
 * ============================================================ */
add_action( 'created_term', 'pageribbon_auto_assign_color_on_term_creation', 10, 3 );

function pageribbon_auto_assign_color_on_term_creation( $term_id, $tt_id, $taxonomy ) {

    $enabled = (array) pageribbon_get_setting( 'enabled_taxonomies', array() );
    if ( ! in_array( $taxonomy, $enabled, true ) ) {
        return;
    }

    $term_colors = (array) pageribbon_get_setting( 'term_colors', array() );

    $term = get_term( $term_id, $taxonomy );
    if ( ! $term || is_wp_error( $term ) ) {
        return;
    }

    // Couleurs déjà utilisées dans cette taxo
    $used_indexes = ! empty( $term_colors[ $taxonomy ] )
        ? array_values( array_map( 'intval', $term_colors[ $taxonomy ] ) )
        : array();

    $palette_size = count( pageribbon_get_palette() );

    // On cherche la première couleur non utilisée. Si toutes sont prises, on cycle.
    $chosen = null;
    for ( $i = 0; $i < $palette_size; $i++ ) {
        if ( ! in_array( $i, $used_indexes, true ) ) {
            $chosen = $i;
            break;
        }
    }
    if ( null === $chosen ) {
        $chosen = count( $used_indexes ) % $palette_size;
    }

    $term_colors[ $taxonomy ][ $term->slug ] = $chosen;
    pageribbon_update_setting( 'term_colors', $term_colors );
}


/* ============================================================
 *  MIGRATION & ADOPTION DES TAXOS EXISTANTES
 *  Au premier lancement, on cherche si l'utilisateur a déjà
 *  `famille_page` ou `modele_page` (cas RésoNAnces) et on les ajoute
 *  automatiquement aux "taxos activées" pour la coloration.
 * ============================================================ */
add_action( 'admin_init', 'pageribbon_adopt_existing_taxonomies' );

function pageribbon_adopt_existing_taxonomies() {

    // Ne s'exécute qu'une fois par site (option drapeau)
    if ( get_option( 'pageribbon_adoption_done' ) ) {
        return;
    }

    $enabled = (array) pageribbon_get_setting( 'enabled_taxonomies', array() );
    $changed = false;

    // Liste des taxos "héritées" connues à adopter automatiquement
    $legacy_slugs = apply_filters( 'pageribbon_legacy_taxonomies', array(
        'famille_page',  // RésoNAnces
        'modele_page',   // RésoNAnces
    ) );

    foreach ( $legacy_slugs as $legacy ) {
        if ( taxonomy_exists( $legacy ) && ! in_array( $legacy, $enabled, true ) ) {
            $enabled[] = $legacy;
            $changed = true;
        }
    }

    // On ajoute aussi systématiquement nos 2 taxos natives
    foreach ( array( PAGERIBBON_TAXO_FAMILLE, PAGERIBBON_TAXO_MODELE ) as $native ) {
        if ( ! in_array( $native, $enabled, true ) ) {
            $enabled[] = $native;
            $changed = true;
        }
    }

    if ( $changed ) {
        pageribbon_update_setting( 'enabled_taxonomies', array_values( array_unique( $enabled ) ) );
    }

    update_option( 'pageribbon_adoption_done', 1 );
}
