<?php
/**
 * PageRibbon — Intégration Gutenberg
 *
 * Slot React natif :
 *  - PluginDocumentSettingPanel dans la sidebar Document
 *  - Bandeau persistant au-dessus de l'éditeur visuel (createRoot)
 *
 * Réutilise le pattern éprouvé du plugin RésoNAnces design-mode v2.0.0
 * (cf. changelog : refonte après instabilité du MutationObserver).
 *
 * Le bandeau lit en live les termes sélectionnés dans l'éditeur via
 * core/editor et core (store officiel), donc il se met à jour quand
 * l'utilisateur change la taxonomie depuis la sidebar.
 *
 * @package PageRibbon
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


/* ============================================================
 *  ENQUEUE DU SCRIPT GUTENBERG
 *  Vanilla wp.* (pas de build npm/webpack) pour rester portable.
 * ============================================================ */
add_action( 'enqueue_block_editor_assets', 'pageribbon_enqueue_editor_assets' );

function pageribbon_enqueue_editor_assets() {

    if ( ! pageribbon_is_enabled() ) {
        return;
    }

    if ( ! pageribbon_get_setting( 'show_in_editor', true ) ) {
        return;
    }

    $screen = get_current_screen();
    if ( ! $screen ) {
        return;
    }

    // On vérifie que le post type courant est concerné par au moins une taxo activée.
    $enabled_taxos = (array) pageribbon_get_setting( 'enabled_taxonomies', array() );
    if ( empty( $enabled_taxos ) ) {
        return;
    }

    $relevant = false;
    foreach ( $enabled_taxos as $taxo_slug ) {
        $taxo = get_taxonomy( $taxo_slug );
        if ( $taxo && in_array( $screen->post_type, (array) $taxo->object_type, true ) ) {
            $relevant = true;
            break;
        }
    }
    if ( ! $relevant ) {
        return;
    }

    // Enregistre le script (sans fichier externe : tout en inline)
    wp_register_script(
        'pageribbon-editor',
        '',
        array(
            'wp-plugins',
            'wp-edit-post',
            'wp-element',
            'wp-components',
            'wp-data',
            'wp-core-data',
            'wp-i18n',
        ),
        PAGERIBBON_VERSION,
        true
    );

    // Construit la "map" pour le JS : pour chaque taxo activée, un tableau {term_id: {name, slug, color}}.
    $taxonomies_data = array();

    $term_colors = (array) pageribbon_get_setting( 'term_colors', array() );

    foreach ( $enabled_taxos as $taxo_slug ) {
        $taxo = get_taxonomy( $taxo_slug );
        if ( ! $taxo ) {
            continue;
        }
        if ( ! in_array( $screen->post_type, (array) $taxo->object_type, true ) ) {
            continue;
        }

        $terms = get_terms( array(
            'taxonomy'   => $taxo_slug,
            'hide_empty' => false,
        ) );

        $terms_data = array();
        if ( ! is_wp_error( $terms ) ) {
            foreach ( $terms as $term ) {
                $color = null;
                if ( isset( $term_colors[ $taxo_slug ][ $term->slug ] ) ) {
                    $color = pageribbon_get_color( $term_colors[ $taxo_slug ][ $term->slug ] );
                }
                $terms_data[ $term->term_id ] = array(
                    'name'  => $term->name,
                    'slug'  => $term->slug,
                    'color' => $color, // null si pas de couleur attribuée
                );
            }
        }

        $taxonomies_data[ $taxo_slug ] = array(
            'label'    => $taxo->labels->singular_name,
            'rest_base'=> ! empty( $taxo->rest_base ) ? $taxo->rest_base : $taxo_slug,
            'terms'    => $terms_data,
        );
    }

    wp_localize_script( 'pageribbon-editor', 'PageRibbonEditor', array(
        'taxonomies' => $taxonomies_data,
        'strings'    => array(
            'mode'       => __( 'Mode visuel', 'pageribbon' ),
            'none'       => __( 'aucun', 'pageribbon' ),
            'noColor'    => __( 'pas de couleur', 'pageribbon' ),
            'panelTitle' => __( '🎨 Mode conception', 'pageribbon' ),
            'panelHint'  => __( 'Désactivez le plugin pour retirer tous les marqueurs visuels.', 'pageribbon' ),
        ),
    ) );

    wp_enqueue_script( 'pageribbon-editor' );

    // Le script Gutenberg lui-même (ES5 pour compatibilité maximale)
    $js = pageribbon_get_editor_js();
    wp_add_inline_script( 'pageribbon-editor', $js );
}


/* ============================================================
 *  SCRIPT JS GUTENBERG
 *  Séparé dans une fonction pour la lisibilité.
 * ============================================================ */
function pageribbon_get_editor_js() {
    // ES5 strict pour passer dans tous les WP, pas de templates littéraux ni d'arrow functions.
    return <<<'JS'
( function( wp ) {
    if ( ! wp || ! wp.plugins || ! wp.editPost ) return;

    var el = wp.element.createElement;
    var Fragment = wp.element.Fragment;
    var registerPlugin = wp.plugins.registerPlugin;
    var PluginDocumentSettingPanel = wp.editPost.PluginDocumentSettingPanel;
    var useSelect = wp.data.useSelect;

    var data = window.PageRibbonEditor || { taxonomies: {}, strings: {} };

    /**
     * Pour le post en cours, retourne pour chaque taxo activée le premier terme attribué
     * avec sa couleur (ou null).
     *
     * @return Array de { taxoSlug, taxoLabel, termName, color }
     */
    function readCurrentTerms() {
        var coreEditor = wp.data.select( 'core/editor' );
        if ( ! coreEditor ) return [];

        var rows = [];

        for ( var taxoSlug in data.taxonomies ) {
            if ( ! Object.prototype.hasOwnProperty.call( data.taxonomies, taxoSlug ) ) continue;

            var taxoInfo = data.taxonomies[ taxoSlug ];
            // L'attribut peut être nommé selon le slug ou le rest_base — on tente les 2
            var termIds = coreEditor.getEditedPostAttribute( taxoSlug )
                || coreEditor.getEditedPostAttribute( taxoInfo.rest_base )
                || [];

            var row = {
                taxoSlug: taxoSlug,
                taxoLabel: taxoInfo.label,
                termName: null,
                color: null
            };

            if ( termIds.length > 0 ) {
                // On prend le premier terme attribué
                var firstId = termIds[0];
                var termInfo = taxoInfo.terms[ firstId ];
                if ( termInfo ) {
                    row.termName = termInfo.name;
                    row.color = termInfo.color;
                }
            }

            rows.push( row );
        }

        return rows;
    }


    /**
     * Composant : panneau dans la sidebar Document (PluginDocumentSettingPanel).
     */
    function PageRibbonPanel() {
        var rows = useSelect( function() {
            return readCurrentTerms();
        }, [] );

        // Prend la première couleur dispo pour styler le panneau
        var primary = null;
        for ( var i = 0; i < rows.length; i++ ) {
            if ( rows[i].color ) { primary = rows[i].color; break; }
        }

        var panelStyle = primary ? {
            background: primary.bg,
            borderLeft: '4px solid ' + primary.border,
            color: primary.text,
            padding: '12px',
            borderRadius: '4px',
            fontSize: '13px',
            lineHeight: '1.5'
        } : {
            background: '#f1f5f9',
            borderLeft: '4px solid #94a3b8',
            color: '#334155',
            padding: '12px',
            borderRadius: '4px',
            fontSize: '13px',
            lineHeight: '1.5'
        };

        var children = [];
        rows.forEach( function( row, idx ) {
            children.push(
                el( 'div', { key: 'row-' + idx, style: { marginBottom: idx < rows.length - 1 ? '6px' : 0 } },
                    el( 'strong', null, row.taxoLabel + ' : ' ),
                    row.termName ? row.termName : el( 'em', null, data.strings.none || 'aucun' )
                )
            );
        } );

        children.push(
            el( 'div', {
                key: 'hint',
                style: {
                    marginTop: '10px',
                    paddingTop: '8px',
                    borderTop: '1px solid rgba(0,0,0,0.08)',
                    fontSize: '11px',
                    opacity: 0.7
                }
            }, data.strings.panelHint || '' )
        );

        return el(
            PluginDocumentSettingPanel,
            {
                name: 'pageribbon-panel',
                title: data.strings.panelTitle || 'PageRibbon',
                className: 'pageribbon-panel'
            },
            el( 'div', { style: panelStyle }, children )
        );
    }


    /**
     * Composant : bandeau persistant en haut de l'éditeur visuel.
     */
    function PageRibbonBanner() {
        var rows = useSelect( function() {
            return readCurrentTerms();
        }, [] );

        // Couleur primaire = première dispo
        var primary = null;
        for ( var i = 0; i < rows.length; i++ ) {
            if ( rows[i].color ) { primary = rows[i].color; break; }
        }
        var colors = primary || { bg: '#f1f5f9', border: '#94a3b8', text: '#334155' };

        var pills = rows.map( function( row, idx ) {
            var pillBg = row.color ? row.color.border : '#94a3b8';
            return el( 'span', {
                key: 'p-' + idx,
                style: {
                    background: pillBg,
                    color: '#fff',
                    padding: '2px 9px',
                    borderRadius: '11px',
                    fontWeight: 600,
                    fontSize: '11px'
                }
            }, row.taxoLabel + ' : ' + ( row.termName || (data.strings.none || 'aucun') ) );
        } );

        return el( 'div', {
            style: {
                background: colors.bg,
                borderLeft: '6px solid ' + colors.border,
                color: colors.text,
                padding: '10px 16px',
                margin: '0 0 12px 0',
                fontSize: '13px',
                display: 'flex',
                alignItems: 'center',
                gap: '12px',
                flexWrap: 'wrap',
                borderRadius: '4px'
            }
        },
            [ el( 'strong', {
                key: 'tag',
                style: {
                    fontSize: '11px',
                    letterSpacing: '0.5px',
                    textTransform: 'uppercase'
                }
            }, '🎨 ' + (data.strings.mode || 'Mode conception') ) ].concat( pills )
        );
    }


    /**
     * Enregistrement du plugin Gutenberg : le panel dans la sidebar.
     */
    registerPlugin( 'pageribbon', {
        render: function() {
            return el( Fragment, null, el( PageRibbonPanel ) );
        },
        icon: null
    } );


    /**
     * Montage du bandeau en haut de l'éditeur via createRoot/render.
     * On retente plusieurs fois jusqu'à ce que l'éditeur soit prêt.
     */
    function mountBanner() {
        var target = document.querySelector( '.editor-visual-editor' )
            || document.querySelector( '.edit-post-visual-editor' )
            || document.querySelector( '.editor-styles-wrapper' );

        if ( ! target ) return false;
        if ( document.getElementById( 'pageribbon-banner-root' ) ) return true;

        var root = document.createElement( 'div' );
        root.id = 'pageribbon-banner-root';
        root.style.padding = '16px 16px 0 16px';
        target.parentNode.insertBefore( root, target );

        if ( wp.element.createRoot ) {
            var r = wp.element.createRoot( root );
            r.render( el( PageRibbonBanner ) );
        } else if ( wp.element.render ) {
            wp.element.render( el( PageRibbonBanner ), root );
        }
        return true;
    }

    var attempts = 0;
    var interval = setInterval( function() {
        attempts++;
        if ( mountBanner() || attempts > 60 ) clearInterval( interval );
    }, 200 );

} )( window.wp );
JS;
}
