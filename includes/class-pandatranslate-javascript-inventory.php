<?php
defined( 'ABSPATH' ) || exit;

/** Conservative JavaScript gettext lexer. Source is read, never evaluated. */
final class PandaTranslate_JavaScript_Inventory {
	const OPTION = 'pandatranslate_javascript_sources';
	public static function key( $entry ) { return hash( 'sha256', 'gettext|' . $entry['domain'] . '|' . $entry['context'] . '|' . $entry['text'] ); }
	public static function categories( $locale ) {
		static $data = null;
		if ( null === $data ) $data = json_decode( file_get_contents( dirname( __DIR__ ) . '/data/plural-categories.json' ), true ) ?: array();
		$locale = str_replace( '-', '_', preg_replace( '/_formal$/', '', $locale ) );
		if ( isset( $data[$locale] ) ) return $data[$locale];
		$base = strtolower( preg_split( '/[-_]/', $locale )[0] );
		return $data[$base] ?? array( 'other' => 2 );
	}
	public static function expand( array $entries, $locale ) {
		$result = array();
		foreach ( $entries as $entry ) {
			if ( ! isset( $entry['plural'] ) ) { $result[] = $entry; continue; }
			foreach ( self::categories( $locale ) as $category => $sample ) {
				$copy = $entry; $copy['singular'] = $entry['text']; $copy['original_context'] = $entry['context'];
				$copy['text'] = 'one' === $category ? $entry['text'] : $entry['plural'];
				$copy['category'] = $category; $copy['sample'] = $sample;
				$copy['context'] = 'js-plural:' . hash( 'sha256', json_encode( array( $entry['context'], $entry['text'], $entry['plural'] ) ) ) . ':' . $category;
				$result[] = $copy;
			}
		}
		return $result;
	}
	public static function remember( array $entries ) {
		$registry = (array) get_option( self::OPTION, array() ); $changed = false;
		foreach ( $entries as $entry ) {
			$key = self::key( $entry ); unset( $entry['sample'] );
			if ( ( $registry[$key] ?? null ) !== $entry ) { $registry[$key] = $entry; $changed = true; }
		}
		if ( $changed ) update_option( self::OPTION, $registry, false );
	}
	private static function string_token( $source, &$offset ) {
		$quote = $source[$offset++]; $text = ''; $valid = true; $length = strlen( $source );
		while ( $offset < $length ) {
			$c = $source[$offset++];
			if ( $c === $quote ) return array( $valid ? 'string' : 'opaque', $text );
			if ( '`' === $quote && '$' === $c && '{' === ( $source[$offset] ?? '' ) ) $valid = false;
			if ( '\\' !== $c ) { $text .= $c; continue; }
			$escape = $source[$offset++] ?? '';
			if ( "\n" === $escape ) continue;
			if ( "\r" === $escape ) { if ( "\n" === ( $source[$offset] ?? '' ) ) $offset++; continue; }
			if ( 'u' === $escape && preg_match( '/^[0-9a-f]{4}/i', substr( $source, $offset ), $match ) ) {
				$hex = $match[0]; $offset += 4;
				if ( hexdec( $hex ) >= 0xD800 && hexdec( $hex ) <= 0xDBFF && preg_match( '/^\\\\u[dD][c-fC-F][0-9a-fA-F]{2}/', substr( $source, $offset ), $pair ) ) { $hex .= $pair[0]; $offset += 6; }
				$value = json_decode( '"\\u' . $hex . '"' ); if ( ! is_string( $value ) ) $valid = false; else $text .= $value;
			} elseif ( 'x' === $escape && preg_match( '/^[0-9a-f]{2}/i', substr( $source, $offset ), $match ) ) { $text .= html_entity_decode( '&#' . hexdec( $match[0] ) . ';', ENT_NOQUOTES, 'UTF-8' ); $offset += 2; }
			else { $map = array( 'n'=>"\n", 'r'=>"\r", 't'=>"\t", 'b'=>"\x08", 'f'=>"\x0c", 'v'=>"\x0b" ); if ( ctype_digit( $escape ) ) $valid = false; $text .= $map[$escape] ?? $escape; }
		}
		return array( 'opaque', '' );
	}
	private static function tokens( $source ) {
		$previous = ''; $length = strlen( $source );
		for ( $i = 0; $i < $length; ) {
			$c = $source[$i];
			if ( ctype_space( $c ) ) { $i++; continue; }
			if ( '/' === $c && '/' === ( $source[$i + 1] ?? '' ) ) { $end = strpos( $source, "\n", $i + 2 ); $i = false === $end ? $length : $end + 1; continue; }
			if ( '/' === $c && '*' === ( $source[$i + 1] ?? '' ) ) { $end = strpos( $source, '*/', $i + 2 ); $i = false === $end ? $length : $end + 2; continue; }
			if ( in_array( $c, array( '"', "'", '`' ), true ) ) { $previous = 'value'; yield self::string_token( $source, $i ); continue; }
			if ( '/' === $c && in_array( $previous, array( '', '=', '(', '[', '{', ',', ':', ';', '!', '?', 'return', '>' ), true ) ) {
				$i++; $class = false;
				while ( $i < $length ) { $v = $source[$i++]; if ( '\\' === $v ) { $i++; continue; } if ( '[' === $v ) $class = true; if ( ']' === $v ) $class = false; if ( '/' === $v && ! $class ) break; }
				while ( $i < $length && ctype_alpha( $source[$i] ) ) $i++;
				$previous = 'value'; yield array( 'opaque', '' ); continue;
			}
			if ( preg_match( '/[a-zA-Z_$]/', $c ) ) { $start = $i++; while ( $i < $length && preg_match( '/[a-zA-Z0-9_$]/', $source[$i] ) ) $i++; $previous = substr( $source, $start, $i - $start ); yield array( 'id', $previous ); continue; }
			$previous = $c; yield array( 'punct', $c ); $i++;
		}
	}
	private static function entry( $args, $function ) {
		$plural = in_array( $function, array( '_n', '_nx' ), true ); $contextual = in_array( $function, array( '_x', '_nx' ), true );
		$domain_index = $plural ? ( $contextual ? 4 : 3 ) : ( $contextual ? 2 : 1 );
		$text = $args[0] ?? null; $domain = count( $args ) > $domain_index ? $args[$domain_index] : 'default'; $context = $contextual ? ( $args[$plural ? 3 : 1] ?? null ) : '';
		if ( ! is_string( $text ) || '' === trim( $text ) || strlen( $text ) > 4000 || ! is_string( $domain ) || ! is_string( $context ) || in_array( $domain, array( 'pandatranslate', 'pandatranslate-multilingual' ), true ) || ( $plural && ! is_string( $args[1] ?? null ) ) ) return null;
		$entry = array( 'text'=>$text, 'domain'=>$domain, 'context'=>$context ); if ( $plural ) $entry['plural'] = $args[1];
		return $entry;
	}
	public static function literals( $source ) {
		$aliases = array( '__'=>'__', '_x'=>'_x', '_n'=>'_n', '_nx'=>'_nx' ); $result = array(); $history = array(); $call = null;
		foreach ( self::tokens( $source ) as $token ) {
			$n = count( $history );
			if ( 'id' === $token[0] ) {
				if ( $n >= 2 && in_array( $history[$n-1][1], array( 'as', ':' ), true ) && isset( $aliases[$history[$n-2][1]] ) ) $aliases[$token[1]] = $aliases[$history[$n-2][1]];
				if ( isset( $aliases[$token[1]] ) && $n >= 4 && '.' === $history[$n-1][1] && '=' === $history[$n-3][1] && 'id' === $history[$n-4][0] ) $aliases[$history[$n-4][1]] = $aliases[$token[1]];
			}
			if ( null !== $call ) {
				$v = $token[1];
				if ( 'punct' === $token[0] && in_array( $v, array( '(', '[', '{' ), true ) ) $call['depth']++;
				if ( 'punct' === $token[0] && in_array( $v, array( ')', ']', '}' ), true ) ) $call['depth']--;
				if ( 0 === $call['depth'] || ( 1 === $call['depth'] && ',' === $v && 'punct' === $token[0] ) ) {
					$call['args'][] = 1 === $call['tokens'] && 'string' === ( $call['first'][0] ?? '' ) ? $call['first'][1] : null;
					$call['first'] = null; $call['tokens'] = 0;
					if ( 0 === $call['depth'] ) { $entry = self::entry( $call['args'], $call['function'] ); if ( $entry ) $result[hash( 'sha256', json_encode( $entry ) )] = $entry; $call = null; }
				} else { if ( 0 === $call['tokens'] ) $call['first'] = $token; $call['tokens']++; }
			} elseif ( 'punct' === $token[0] && '(' === $token[1] && $n ) {
				$name = $history[$n-1];
				if ( ')' === $name[1] && $n >= 2 ) $name = $history[$n-2];
				if ( 'id' === $name[0] && isset( $aliases[$name[1]] ) ) $call = array( 'function'=>$aliases[$name[1]], 'args'=>array(), 'depth'=>1, 'tokens'=>0, 'first'=>null );
			}
			$history[] = $token; if ( count( $history ) > 8 ) array_shift( $history );
		}
		return array_values( $result );
	}
}
