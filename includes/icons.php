<?php
/** @package ArvanCloudPartnerNetwork */
defined( 'ABSPATH' ) || exit;

function acpn_allowed_svg_html(): array {
	return array(
		'svg'      => array( 'viewbox' => true, 'fill' => true, 'role' => true, 'aria-hidden' => true ),
		'path'     => array( 'd' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true, 'stroke-linecap' => true, 'stroke-linejoin' => true ),
		'circle'   => array( 'cx' => true, 'cy' => true, 'r' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true ),
		'rect'     => array( 'x' => true, 'y' => true, 'width' => true, 'height' => true, 'rx' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true ),
	);
}

function acpn_get_icon( string $name ): string {
	$paths = array(
		'home'      => '<path d="M3 11.5 12 4l9 7.5V21h-6v-6H9v6H3v-9.5Z"/>',
		'user'      => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.6-7 8-7H11c4.4 0 8 3 8 7"/>',
		'briefcase' => '<rect x="3" y="7" width="18" height="13" rx="2"/><path d="M8 7V5c0-1 1-2 2-2h4c1 0 2 1 2 2v2M3 12h18"/>',
		'users'     => '<path d="M16 21v-2c0-3-2-5-5-5H6c-3 0-5 2-5 5v2M8.5 10a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM17 11c2 0 4-2 4-4s-2-4-4-4M23 21v-2c0-3-2-5-5-5"/>',
		'profile'   => '<rect x="5" y="3" width="14" height="18" rx="2"/><circle cx="12" cy="9" r="2.5"/><path d="M8 17c.5-2 2-3 4-3s3.5 1 4 3"/>',
		'megaphone' => '<path d="m3 11 14-6v14L3 13v-2ZM17 9c2 0 4 1 4 3s-2 3-4 3M6 14l2 6h4l-2-5"/>',
		'handshake' => '<path d="m8 12 3-3c1-1 2-1 3 0l2 2M3 9l4-4 4 3M21 9l-4-4-4 3M7 16l3 3c1 1 2 1 3 0l5-5M4 11l-2 2 5 5 2-2M20 11l2 2-5 5-2-2"/>',
	);
	$path = $paths[ $name ] ?? $paths['home'];
	return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $path . '</svg>';
}
