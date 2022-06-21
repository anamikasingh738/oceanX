<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the web site, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://wordpress.org/support/article/editing-wp-config-php/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'classic' );

/** Database username */
define( 'DB_USER', 'root' );

/** Database password */
define( 'DB_PASSWORD', '' );

/** Database hostname */
define( 'DB_HOST', 'localhost' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8mb4' );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication unique keys and salts.
 *
 * Change these to different unique phrases! You can generate these using
 * the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}.
 *
 * You can change these at any point in time to invalidate all existing cookies.
 * This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',         'U/E)`|NBwAWg4nG1.#&Hf;>3)<,a,!S&aU^=J6&mGnmnI316f`Xj!4<tG`2;r3J6' );
define( 'SECURE_AUTH_KEY',  '%+RPn<&!3:P0n]sw8Z>OQxnWB81x/Rb&QDt%r},1##I4t^%Ryrs5@&x?9I_&nPve' );
define( 'LOGGED_IN_KEY',    'K[2ipCi1x:;+x%KV//]NuE:)3D.x]giwe.B~kEU}@XR(v8pRVr<?]l(%VTW}s~Ec' );
define( 'NONCE_KEY',        '$1.4Qx!V2rfCoD]EQgeH%(<8hCy2}D!y$|<Xats{lG?y>W.cW~q`4}7&0$4FMc)W' );
define( 'AUTH_SALT',        ' tE2(3kqwcx|j,VW$eWvkWDlm{z{1Mr_w<o.6JUJf-6|c8C5NCho?{[8Si)q3q9V' );
define( 'SECURE_AUTH_SALT', 'U&:J<1yz*C#/Vyj)/:Aq2/%N&ZGNX1UGJe6^^g;h|:`C X;+gbS~~1l(&.iUaX~s' );
define( 'LOGGED_IN_SALT',   '&lo7o?zRqDcgMg(9tidFA5$[12UY{!JAit/~]gj}<a-g+qTy]mCS--r3w8RA Y:%' );
define( 'NONCE_SALT',       'Ed3,>:(,*UXHKmz0~qq^`n9#2`QOV#VY!fRuS?Q8 C!!`%fO++Slh ;YSe%UpQ Y' );

/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'classic_';

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the documentation.
 *
 * @link https://wordpress.org/support/article/debugging-in-wordpress/
 */
define( 'WP_DEBUG', false );

/* Add any custom values between this line and the "stop editing" line. */



/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
