<?php

const UAP_KEY_PLATFORM = 'platform';
const UAP_KEY_BROWSER  = 'browser';
const UAP_KEY_VERSION  = 'version';

/**
 * Parses a user agent string into its important parts
 *
 * @author Jesse G. Donat <donatj@gmail.com>
 * @link https://github.com/donatj/PhpUserAgent
 * @link http://donatstudios.com/PHP-Parser-HTTP_USER_AGENT
 * @param string|null $u_agent User agent string to parse or null. Uses $_SERVER['HTTP_USER_AGENT'] on NULL
 * @throws \InvalidArgumentException on not having a proper user agent to parse.
 * @return string[] an array with browser, version and platform keys
 */
function parse_user_agent( $u_agent = null ) {
	if( is_null($u_agent) ) {
		if( isset($_SERVER['HTTP_USER_AGENT']) ) {
			$u_agent = $_SERVER['HTTP_USER_AGENT'];
		} else {
			throw new \InvalidArgumentException('parse_user_agent requires a user agent');
		}
	}

	$platform = null;
	$browser  = null;
	$version  = null;

	$empty = array( UAP_KEY_PLATFORM => $platform, UAP_KEY_BROWSER => $browser, UAP_KEY_VERSION => $version );

	if( !$u_agent ) {
		return $empty;
	}

	if( preg_match('/\((.*?)\)/im', $u_agent, $parent_matches) ) {
		preg_match_all('/(?P<platform>BB\d+;|Android|CrOS|Tizen|iPhone|iPad|iPod|Linux|(Open|Net|Free)BSD|Macintosh|Windows(\ Phone)?|Silk|linux-gnu|BlackBerry|PlayBook|X11|(New\ )?Nintendo\ (WiiU?|3?DS|Switch)|Xbox(\ One)?)
				(?:\ [^;]*)?
				(?:;|$)/imx', $parent_matches[1], $result, PREG_PATTERN_ORDER);

		$priority = array( 'Xbox One', 'Xbox', 'Windows Phone', 'Tizen', 'Android', 'FreeBSD', 'NetBSD', 'OpenBSD', 'CrOS', 'X11' );

		$result[UAP_KEY_PLATFORM] = array_unique($result[UAP_KEY_PLATFORM]);
		if( count($result[UAP_KEY_PLATFORM]) > 1 ) {
			if( $keys = array_intersect($priority, $result[UAP_KEY_PLATFORM]) ) {
				$platform = reset($keys);
			} else {
				$platform = $result[UAP_KEY_PLATFORM][0];
			}
		} elseif( isset($result[UAP_KEY_PLATFORM][0]) ) {
			$platform = $result[UAP_KEY_PLATFORM][0];
		}
	}

	if( $platform == 'linux-gnu' || $platform == 'X11' ) {
		$platform = 'Linux';
	} elseif( $platform == 'CrOS' ) {
		$platform = 'Chrome OS';
	}

	preg_match_all('%(?P<browser>Camino|Kindle(\ Fire)?|Firefox|Iceweasel|IceCat|Safari|MSIE|Trident|AppleWebKit|
				TizenBrowser|(?:Headless)?Chrome|YaBrowser|Vivaldi|IEMobile|Opera|OPR|Silk|Midori|Edge|CriOS|UCBrowser|Puffin|SamsungBrowser|
				Baiduspider|Googlebot|YandexBot|bingbot|Lynx|Version|Wget|curl|
				Valve\ Steam\ Tenfoot|
				NintendoBrowser|PLAYSTATION\ (\d|Vita)+)
				(?:\)?;?)
				(?:(?:[:/ ])(?P<version>[0-9A-Z.]+)|/(?:[A-Z]*))%ix',
		$u_agent, $result, PREG_PATTERN_ORDER);

	// If nothing matched, return null (to avoid undefined index errors)
	if( !isset($result[UAP_KEY_BROWSER][0]) || !isset($result[UAP_KEY_VERSION][0]) ) {
		if( preg_match('%^(?!Mozilla)(?P<browser>[A-Z0-9\-]+)(/(?P<version>[0-9A-Z.]+))?%ix', $u_agent, $result) ) {
			return array( UAP_KEY_PLATFORM => $platform ?: null, UAP_KEY_BROWSER => $result[UAP_KEY_BROWSER], UAP_KEY_VERSION => isset($result[UAP_KEY_VERSION]) ? $result[UAP_KEY_VERSION] ?: null : null );
		}

		return $empty;
	}

	if( preg_match('/rv:(?P<version>[0-9A-Z.]+)/si', $u_agent, $rv_result) ) {
		$rv_result = $rv_result[UAP_KEY_VERSION];
	}

	$browser = $result[UAP_KEY_BROWSER][0];
	$version = $result[UAP_KEY_VERSION][0];

	$lowerBrowser = array_map('strtolower', $result[UAP_KEY_BROWSER]);

	$find = function ( $search, &$key, &$value = null ) use ( $lowerBrowser ) {
		$search = (array)$search;

		foreach( $search as $val ) {
			$xkey = array_search(strtolower($val), $lowerBrowser);
			if( $xkey !== false ) {
				$value = $val;
				$key   = $xkey;

				return true;
			}
		}

		return false;
	};

	$key = 0;
	$val = '';
	if( $browser == 'Iceweasel' || strtolower($browser) == 'icecat' ) {
		$browser = 'Firefox';
	} elseif( $find('Playstation Vita', $key) ) {
		$platform = 'PlayStation Vita';
		$browser  = 'Browser';
	} elseif( $find(array( 'Kindle Fire', 'Silk' ), $key, $val) ) {
		$browser  = $val == 'Silk' ? 'Silk' : 'Kindle';
		$platform = 'Kindle Fire';
		if( !($version = $result[UAP_KEY_VERSION][$key]) || !is_numeric($version[0]) ) {
			$version = $result[UAP_KEY_VERSION][array_search('Version', $result[UAP_KEY_BROWSER])];
		}
	} elseif( $find('NintendoBrowser', $key) || $platform == 'Nintendo 3DS' ) {
		$browser = 'NintendoBrowser';
		$version = $result[UAP_KEY_VERSION][$key];
	} elseif( $find('Kindle', $key, $platform) ) {
		$browser = $result[UAP_KEY_BROWSER][$key];
		$version = $result[UAP_KEY_VERSION][$key];
	} elseif( $find('OPR', $key) ) {
		$browser = 'Opera Next';
		$version = $result[UAP_KEY_VERSION][$key];
	} elseif( $find('Opera', $key, $browser) ) {
		$find('Version', $key);
		$version = $result[UAP_KEY_VERSION][$key];
	} elseif( $find('Puffin', $key, $browser) ) {
		$version = $result[UAP_KEY_VERSION][$key];
		if( strlen($version) > 3 ) {
			$part = substr($version, -2);
			if( ctype_upper($part) ) {
				$version = substr($version, 0, -2);

				$flags = array( 'IP' => 'iPhone', 'IT' => 'iPad', 'AP' => 'Android', 'AT' => 'Android', 'WP' => 'Windows Phone', 'WT' => 'Windows' );
				if( isset($flags[$part]) ) {
					$platform = $flags[$part];
				}
			}
		}
	} elseif( $find('YaBrowser', $key, $browser) ) {
		$browser = 'Yandex';
		$version = $result['version'][$key];
	} elseif( $find(array( 'IEMobile', 'Edge', 'Midori', 'Vivaldi', 'SamsungBrowser', 'Valve Steam Tenfoot', 'Chrome', 'HeadlessChrome' ), $key, $browser) ) {
		$version = $result[UAP_KEY_VERSION][$key];
	} elseif( $rv_result && $find('Trident', $key) ) {
		$browser = 'MSIE';
		$version = $rv_result;
	} elseif( $find('UCBrowser', $key) ) {
		$browser = 'UC Browser';
		$version = $result[UAP_KEY_VERSION][$key];
	} elseif( $find('CriOS', $key) ) {
		$browser = 'Chrome';
		$version = $result[UAP_KEY_VERSION][$key];
	} elseif( $browser == 'AppleWebKit' ) {
		if( $platform == 'Android' ) {
			// $key = 0;
			$browser = 'Android Browser';
		} elseif( strpos($platform, 'BB') === 0 ) {
			$browser  = 'BlackBerry Browser';
			$platform = 'BlackBerry';
		} elseif( $platform == 'BlackBerry' || $platform == 'PlayBook' ) {
			$browser = 'BlackBerry Browser';
		} else {
			$find('Safari', $key, $browser) || $find('TizenBrowser', $key, $browser);
		}

		$find('Version', $key);
		$version = $result[UAP_KEY_VERSION][$key];
	} elseif( $pKey = preg_grep('/playstation \d/i', array_map('strtolower', $result[UAP_KEY_BROWSER])) ) {
		$pKey = reset($pKey);

		$platform = 'PlayStation ' . preg_replace('/[^\d]/i', '', $pKey);
		$browser  = 'NetFront';
	}

	return array( UAP_KEY_PLATFORM => $platform ?: null, UAP_KEY_BROWSER => $browser ?: null, UAP_KEY_VERSION => $version ?: null );
}
