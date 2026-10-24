<?php
/**
 * Privacy defaults: asking AI crawlers to stay away (Q26, design §16).
 *
 * @package Stubli
 */

namespace Stubli;

defined( 'ABSPATH' ) || exit;

/**
 * "Keep out of search engines" uses WordPress's own setting (blog_public).
 * "Ask AI crawlers to stay away" adds robots.txt rules for the known AI
 * training crawlers plus a noai robots meta tag. These are requests that
 * respectable bots honour, not a lock; the interface says so.
 */
class Privacy {

	const AI_CRAWLERS = array( 'GPTBot', 'ChatGPT-User', 'ClaudeBot', 'anthropic-ai', 'CCBot', 'Google-Extended', 'Applebot-Extended', 'PerplexityBot', 'Bytespider', 'Meta-ExternalAgent', 'Amazonbot', 'cohere-ai', 'Diffbot', 'omgili' );

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_filter( 'robots_txt', array( self::class, 'robots_txt' ), 20, 2 );
		add_filter( 'wp_robots', array( self::class, 'robots_meta' ) );
		add_filter( 'get_avatar_url', array( self::class, 'no_gravatar' ), 5 );
	}

	/**
	 * Never send visitors' browsers to Gravatar: a request there tells a
	 * third party who is looking at whose page. A neutral local silhouette
	 * stands in.
	 *
	 * @param string $url Avatar URL.
	 * @return string
	 */
	public static function no_gravatar( $url ) {
		if ( false === strpos( (string) $url, 'gravatar.com' ) ) {
			return $url;
		}
		return 'data:image/svg+xml,' . rawurlencode( '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 40 40"><rect width="40" height="40" fill="#e6e1d8"/><circle cx="20" cy="16" r="7" fill="#c9c1b4"/><path d="M6 38c2-8 8-12 14-12s12 4 14 12z" fill="#c9c1b4"/></svg>' );
	}

	/**
	 * @return bool
	 */
	public static function blocking_ai() {
		return (bool) get_option( 'stubli_block_ai', 1 );
	}

	/**
	 * Add AI crawler rules to robots.txt.
	 *
	 * @param string $output Current robots.txt.
	 * @param bool   $public Whether the site is visible to search engines.
	 * @return string
	 */
	public static function robots_txt( $output, $public ) {
		if ( ! self::blocking_ai() ) {
			return $output;
		}
		$rules = "\n# Stubli: please don't use this site to train AI.\n";
		foreach ( self::AI_CRAWLERS as $bot ) {
			$rules .= "User-agent: {$bot}\nDisallow: /\n\n";
		}
		return $output . $rules;
	}

	/**
	 * Add noai / noimageai to the robots meta tag.
	 *
	 * @param array $robots Robots directives.
	 * @return array
	 */
	public static function robots_meta( $robots ) {
		if ( self::blocking_ai() ) {
			$robots['noai']      = true;
			$robots['noimageai'] = true;
		}
		return $robots;
	}
}
