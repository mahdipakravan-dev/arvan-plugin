<?php
/** @package ArvanCloudPartnerNetwork */
defined( 'ABSPATH' ) || exit;

$steps = array(
	'welcome'          => array( 'label' => __( 'خوش‌آمدگویی', 'arvancloud-partner-network' ), 'icon' => 'home' ),
	'business-profile' => array( 'label' => __( 'پروفایل کسب‌وکار', 'arvancloud-partner-network' ), 'icon' => 'user' ),
	'opportunities'    => array( 'label' => __( 'خدمات و فرصت‌ها', 'arvancloud-partner-network' ), 'icon' => 'briefcase' ),
	'connection'       => array( 'label' => __( 'اتصال و همکاری', 'arvancloud-partner-network' ), 'icon' => 'users' ),
);

$cards = array(
	array( 'number' => '۱', 'icon' => 'profile', 'title' => __( 'تکمیل پروفایل کسب‌وکار', 'arvancloud-partner-network' ), 'description' => __( 'اطلاعات و حوزه فعالیت خود را ثبت کنید', 'arvancloud-partner-network' ) ),
	array( 'number' => '۲', 'icon' => 'megaphone', 'title' => __( 'معرفی خدمات و فرصت‌ها', 'arvancloud-partner-network' ), 'description' => __( 'توانمندی‌ها و پیشنهادهای همکاری را منتشر کنید', 'arvancloud-partner-network' ) ),
	array( 'number' => '۳', 'icon' => 'handshake', 'title' => __( 'شروع همکاری', 'arvancloud-partner-network' ), 'description' => __( 'با شرکای مناسب ارتباط بگیرید', 'arvancloud-partner-network' ) ),
);
?>
<div class="wrap acpn-wrap" dir="rtl">
	<div class="acpn-app">
		<header class="acpn-header">
			<div class="acpn-brand">
				<span class="acpn-logo" aria-hidden="true">
					<svg viewBox="0 0 64 44" role="img"><path d="M17 39C8 39 2 33 2 25s6-14 14-14c3-7 9-10 17-10 10 0 18 7 19 16 6 1 10 5 10 11 0 7-5 11-12 11H17Z"/><path class="acpn-logo-facet" d="m16 12 17 27 19-22M9 34l20-13L41 3l9 36"/></svg>
				</span>
				<strong><?php echo esc_html__( 'شبکه شرکای تجاری آروان‌کلاد', 'arvancloud-partner-network' ); ?></strong>
			</div>
			<div class="acpn-version"><span><?php echo esc_html__( 'نسخه', 'arvancloud-partner-network' ); ?></span><bdi><?php echo esc_html( ACPN_VERSION ); ?></bdi></div>
		</header>

		<main class="acpn-main">
			<aside class="acpn-stepper" aria-label="<?php echo esc_attr__( 'مراحل راه‌اندازی', 'arvancloud-partner-network' ); ?>">
				<div class="acpn-mobile-progress"><span><?php echo esc_html__( 'مرحله ۱ از ۴', 'arvancloud-partner-network' ); ?></span><strong><?php echo esc_html__( 'خوش‌آمدگویی', 'arvancloud-partner-network' ); ?></strong></div>
				<ol>
					<?php foreach ( $steps as $step_key => $step_data ) : ?>
						<li class="<?php echo 'welcome' === $step_key ? 'is-active' : ''; ?>">
							<span class="acpn-step-icon" aria-hidden="true"><?php echo wp_kses( acpn_get_icon( $step_data['icon'] ), acpn_allowed_svg_html() ); ?></span>
							<span><?php echo esc_html( $step_data['label'] ); ?></span>
						</li>
					<?php endforeach; ?>
				</ol>
			</aside>

			<section class="acpn-content" aria-labelledby="acpn-welcome-title">
				<div class="acpn-hero">
					<div class="acpn-glow acpn-glow-cyan" aria-hidden="true"></div>
					<div class="acpn-glow acpn-glow-pink" aria-hidden="true"></div>
					<div class="acpn-hero-copy">
						<span class="acpn-eyebrow"><?php echo esc_html__( 'شبکه‌ای برای رشد مشترک', 'arvancloud-partner-network' ); ?></span>
						<h1 id="acpn-welcome-title"><?php echo esc_html__( 'به شبکه شرکای تجاری آروان‌کلاد خوش آمدید', 'arvancloud-partner-network' ); ?></h1>
						<p><?php echo esc_html__( 'همکاری‌ها، فرصت‌ها و مشتریان تجاری خود را از یک مسیر یکپارچه مدیریت کنید.', 'arvancloud-partner-network' ); ?></p>
					</div>
				</div>

				<div class="acpn-cards" aria-label="<?php echo esc_attr__( 'مسیر شروع همکاری', 'arvancloud-partner-network' ); ?>">
					<?php foreach ( $cards as $index => $card ) : ?>
						<article class="acpn-card">
							<span class="acpn-card-number"><?php echo esc_html( $card['number'] ); ?></span>
							<span class="acpn-card-icon" aria-hidden="true"><?php echo wp_kses( acpn_get_icon( $card['icon'] ), acpn_allowed_svg_html() ); ?></span>
							<h2><?php echo esc_html( $card['title'] ); ?></h2>
							<p><?php echo esc_html( $card['description'] ); ?></p>
							<?php if ( $index < count( $cards ) - 1 ) : ?><span class="acpn-card-connector" aria-hidden="true"></span><?php endif; ?>
						</article>
					<?php endforeach; ?>
				</div>

				<div class="acpn-actions">
					<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
						<input type="hidden" name="action" value="acpn_start_setup">
						<?php wp_nonce_field( 'acpn_start_setup' ); ?>
						<button class="acpn-button acpn-button-primary" type="submit"><?php echo esc_html__( 'شروع راه‌اندازی', 'arvancloud-partner-network' ); ?><span aria-hidden="true">←</span></button>
					</form>
					<a class="acpn-button acpn-button-link" href="https://www.arvancloud.ir/fa/support" target="_blank" rel="noopener noreferrer"><?php echo esc_html__( 'مشاهده راهنما', 'arvancloud-partner-network' ); ?><span aria-hidden="true">↗</span></a>
				</div>
			</section>
		</main>
	</div>
</div>

