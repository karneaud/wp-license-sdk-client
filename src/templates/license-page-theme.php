<?php if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
} ?>
<div class="wrap">
	<div class="postbox" style="min-width: 500px; max-width: 65%; margin:25px auto">
		<div class="inside" style="margin: 50px;">
			<h2><?php echo esc_html( $title ); ?></h2>
			<?php  include __DIR__ . '/license-form.php'; // @codingStandardsIgnoreLine ?>
		</div>
	</div>
</div>
