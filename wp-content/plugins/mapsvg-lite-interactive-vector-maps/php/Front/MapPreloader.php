<?php

namespace MapSVG;

/**
 * Server-rendered map preloader shown before JS/CSS bundles load.
 *
 * Keep the CSS string in sync with js/mapsvg/Map/mapPreloader.ts.
 */
class MapPreloader
{
	public const STYLE_ATTR = 'data-mapsvg-critical';
	public const STYLE_ATTR_VALUE = 'loading';

	/**
	 * Critical CSS: pill + spinner, centered in the height:0 + padding-bottom box.
	 *
	 * @return string
	 */
	public static function css(): string
	{
		return '.mapsvg{position:relative;overflow:hidden}'
			. '.mapsvg-loading{position:absolute;top:0;left:0;right:0;bottom:0;margin:auto;width:max-content;height:max-content;z-index:100;display:flex;align-items:center;justify-content:center;gap:6px;padding:7px 10px;border-radius:5px;border:1px solid #ccc;background:#f5f5f2;box-shadow:0 0 20px rgba(0,0,0,.2);line-height:1}'
			. '.mapsvg-loading-text{display:inline-block;font-size:12px!important;line-height:1;color:#999;font-family:Helvetica,sans-serif}'
			. '.mapsvg-loading-text:empty{display:none}'
			. '.mapsvg-loading .spinner-border{display:block;flex-shrink:0;box-sizing:border-box;width:12px;height:12px;margin:0;color:#888;border:2px solid currentColor;border-right-color:transparent;border-radius:50%;animation:mapsvg-spinner-border .75s linear infinite}'
			. '@keyframes mapsvg-spinner-border{to{transform:rotate(360deg)}}';
	}

	/**
	 * Preloader markup. $loadingText must already be sanitized (wp_kses_post).
	 *
	 * @param string $loadingText
	 * @return string
	 */
	public static function html(string $loadingText): string
	{
		return '<div class="mapsvg-loading">'
			. '<div class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></div>'
			. '<div class="mapsvg-loading-text">' . $loadingText . '</div>'
			. '</div>';
	}

	/**
	 * Style tag + preloader. Placed inside the map container so it follows the node into Shadow DOM.
	 *
	 * @param string $loadingText
	 * @return string
	 */
	public static function innerHtml(string $loadingText): string
	{
		return '<style ' . self::STYLE_ATTR . '="' . self::STYLE_ATTR_VALUE . '">'
			. self::css()
			. '</style>'
			. self::html($loadingText);
	}
}
