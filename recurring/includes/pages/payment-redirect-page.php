<?php

defined( 'ABSPATH' ) || exit;

// The SDK's payment iframe can also return here. Only the parent should start polling.
// Parent handlers add pw=1, including when the shop itself is embedded in an iframe.
if ( ( $_SERVER['HTTP_SEC_FETCH_DEST'] ?? '' ) === 'iframe' && ( $_GET['pw'] ?? '' ) !== '1' ) {
	echo 'ok';
	return;
}

?>

<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">

	<?php wp_head(); ?>
</head>

<body>
<div id="vipps-mobilepay-recurring-app"></div>

<?php wp_footer(); ?>
</body>
