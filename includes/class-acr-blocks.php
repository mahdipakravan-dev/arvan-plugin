<?php
/**
 * Dynamic Gutenberg blocks for the storefront and customer profile.
 *
 * @package ArvanCloudReseller
 */

defined( 'ABSPATH' ) || exit;

final class ACR_Blocks {
	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'register' ) );
	}

	public static function register(): void {
		wp_register_style( 'acr-frontend', ACR_URL . 'assets/css/frontend.css', array(), (string) filemtime( ACR_PATH . 'assets/css/frontend.css' ) );
		wp_register_script(
			'acr-blocks-editor',
			ACR_URL . 'assets/js/blocks.js',
			array( 'wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-i18n' ),
			ACR_VERSION,
			true
		);

		register_block_type(
			'acr/product-catalog',
			array(
				'api_version'     => 2,
				'editor_script'   => 'acr-blocks-editor',
				'style'           => 'acr-frontend',
				'render_callback' => array( __CLASS__, 'render_catalog' ),
				'attributes'      => array(
					'title'           => array( 'type' => 'string', 'default' => 'محصولات ابری' ),
					'showSyncTime'    => array( 'type' => 'boolean', 'default' => true ),
					'showPolicyNotice'=> array( 'type' => 'boolean', 'default' => true ),
				),
			)
		);

		register_block_type(
			'acr/customer-profile',
			array(
				'api_version'     => 2,
				'editor_script'   => 'acr-blocks-editor',
				'style'           => 'acr-frontend',
				'render_callback' => array( __CLASS__, 'render_profile' ),
			)
		);
	}

	public static function render_catalog( array $attributes = array() ): string {
		wp_enqueue_style( 'acr-frontend' );
		wp_enqueue_style( 'dashicons' );
		$products = ACR_Catalog::get_products();
		if ( ! $products ) {
			ACR_Catalog::seed_defaults();
			$products = ACR_Catalog::get_products();
		}

		$title        = sanitize_text_field( (string) ( $attributes['title'] ?? __( 'محصولات ابری', 'arvancloud-reseller' ) ) );
		$show_sync    = ! isset( $attributes['showSyncTime'] ) || (bool) $attributes['showSyncTime'];
		$show_policy  = ! isset( $attributes['showPolicyNotice'] ) || (bool) $attributes['showPolicyNotice'];
		$last_sync    = (string) ACR_Settings::get( 'catalog_last_sync', '' );
		$profile_page = absint( ACR_Settings::get( 'portal_page_id', 0 ) );
		$profile_url  = $profile_page ? get_permalink( $profile_page ) : '#acr-customer-profile';
		$profile_login_url = str_contains( (string) $profile_url, '#' ) ? $profile_url : $profile_url . '#acr-customer-profile';

		ob_start();
		?>
		<section class="acr-storefront" dir="rtl" aria-labelledby="acr-catalog-title">
			<header class="acr-storefront__head"><div><span><?php esc_html_e( 'کاتالوگ همگام با آروان‌کلاد', 'arvancloud-reseller' ); ?></span><h2 id="acr-catalog-title"><?php echo esc_html( $title ); ?></h2><p><?php esc_html_e( 'قبل از سفارش وارد پروفایل شوید و کیف پول خود را شارژ کنید.', 'arvancloud-reseller' ); ?></p></div><?php if ( $show_sync ) : ?><small><span class="dashicons dashicons-update"></span><?php echo $last_sync ? esc_html( sprintf( __( 'آخرین بروزرسانی: %s', 'arvancloud-reseller' ), $last_sync ) ) : esc_html__( 'در انتظار اولین همگام‌سازی', 'arvancloud-reseller' ); ?></small><?php endif; ?></header>
			<div class="acr-storefront__grid">
				<?php foreach ( $products as $product ) : ?>
					<?php $order_url = 'cloud-server' === $product['slug'] && ! str_contains( (string) $profile_url, '#' ) ? $profile_url . '#acr-server-order' : $profile_login_url; ?>
					<article class="acr-store-product is-<?php echo esc_attr( sanitize_html_class( $product['slug'] ) ); ?>">
						<div class="acr-store-product__top"><span class="acr-store-product__icon"><span class="dashicons <?php echo esc_attr( $product['icon'] ); ?>"></span></span><span class="acr-store-product__source"><?php echo 'official' === $product['source_state'] ? esc_html__( 'قیمت رسمی', 'arvancloud-reseller' ) : esc_html__( 'قیمت پایه', 'arvancloud-reseller' ); ?></span></div>
						<h3><?php echo esc_html( $product['name'] ); ?></h3><p><?php echo esc_html( $product['description'] ); ?></p>
						<div class="acr-store-product__price"><small><?php esc_html_e( 'تعرفه', 'arvancloud-reseller' ); ?></small><strong><?php echo esc_html( $product['price_label'] ); ?></strong></div>
						<footer><a href="<?php echo esc_url( $product['source_url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'جزئیات قیمت', 'arvancloud-reseller' ); ?></a><?php if ( (int) $product['purchasable'] ) : ?><a class="acr-p-btn" href="<?php echo esc_url( $order_url ); ?>"><?php echo is_user_logged_in() && 'cloud-server' === $product['slug'] ? esc_html__( 'سرور جدید', 'arvancloud-reseller' ) : ( is_user_logged_in() ? esc_html__( 'رفتن به پروفایل و سفارش', 'arvancloud-reseller' ) : esc_html__( 'ورود با شماره موبایل', 'arvancloud-reseller' ) ); ?></a><?php else : ?><span class="acr-coming-soon"><?php esc_html_e( 'نمایش قیمت', 'arvancloud-reseller' ); ?></span><?php endif; ?></footer>
					</article>
				<?php endforeach; ?>
			</div>
			<?php if ( $show_policy ) : ?><div class="acr-policy-note"><span class="dashicons dashicons-warning"></span><div><strong><?php esc_html_e( 'سفارش فقط با موجودی کافی فعال می‌شود', 'arvancloud-reseller' ); ?></strong><p><?php esc_html_e( 'پس از کاهش موجودی، محدودیت سرویس مطابق سیاست کیف پول افزونه و شرایط رسمی قطع سرویس اعمال خواهد شد.', 'arvancloud-reseller' ); ?></p></div><a href="<?php echo esc_url( ACR_Catalog::TERMINATION_URL ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'مطالعه شرایط', 'arvancloud-reseller' ); ?></a></div><?php endif; ?>
		</section>
		<?php
		return (string) ob_get_clean();
	}

	public static function render_profile(): string {
		return ACR_Frontend::portal();
	}
}
