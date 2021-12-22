<?php
/**
 * WordPress の基本設定
 *
 * このファイルは、インストール時に wp-config.php 作成ウィザードが利用します。
 * ウィザードを介さずにこのファイルを "wp-config.php" という名前でコピーして
 * 直接編集して値を入力してもかまいません。
 *
 * このファイルは、以下の設定を含みます。
 *
 * * MySQL 設定
 * * 秘密鍵
 * * データベーステーブル接頭辞
 * * ABSPATH
 *
 * @link https://ja.wordpress.org/support/article/editing-wp-config-php/
 *
 * @package WordPress
 */

// 注意:
// Windows の "メモ帳" でこのファイルを編集しないでください !
// 問題なく使えるテキストエディタ
// (http://wpdocs.osdn.jp/%E7%94%A8%E8%AA%9E%E9%9B%86#.E3.83.86.E3.82.AD.E3.82.B9.E3.83.88.E3.82.A8.E3.83.87.E3.82.A3.E3.82.BF 参照)
// を使用し、必ず UTF-8 の BOM なし (UTF-8N) で保存してください。

// ** MySQL 設定 - この情報はホスティング先から入手してください。 ** //
// 新しい本番環境に合わせる。
/** WordPress のためのデータベース名 */
define( 'DB_NAME', 'genkansha_wp_pro' );

/** MySQL データベースのユーザー名 */
define( 'DB_USER', 'genkansha' );

/** MySQL データベースのパスワード */
define( 'DB_PASSWORD', 'T5tq3_wS' );

/** MySQL のホスト名 */
define( 'DB_HOST', 'mysql57.genkansha.sakura.ne.jp' );

/** データベースのテーブルを作成する際のデータベースの文字セット */
define( 'DB_CHARSET', 'utf8' );

/** データベースの照合順序 (ほとんどの場合変更する必要はありません) */
define( 'DB_COLLATE', '' );

/**#@+
 * 認証用ユニークキー
 *
 * それぞれを異なるユニーク (一意) な文字列に変更してください。
 * {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org の秘密鍵サービス} で自動生成することもできます。
 * 後でいつでも変更して、既存のすべての cookie を無効にできます。これにより、すべてのユーザーを強制的に再ログインさせることになります。
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',         '(jq4?G:$OWG^#DKSC>b6pd`B=36gm<Kq*#f@3mW2cPBnb]Pr0;2PW$p!z]^$3tng' );
define( 'SECURE_AUTH_KEY',  'dp^axYD!,neQ+siy;bK4^.5jNYIlM#;2el{LyBunF[GW}bGitm~S1<$t^d_{.O1W' );
define( 'LOGGED_IN_KEY',    'uqUiB5qwi?!aHcms4$v:*iqVfmd7c!s0o?wQ5vi|);G+nar[}de&N6R$rE2f_Id1' );
define( 'NONCE_KEY',        'rt4FaE)#rkQ4D(*u+#]&lzgBeu;)wt?fys4iUzZ7*iwc+nC&;NKAt/dOeKSsAhi[' );
define( 'AUTH_SALT',        '={GwxL+,V_(+[Y%sqFR66c1g*YP[ hVPT;iS~a?Ei>4jpaBUf$p<A7<Q4#+Jr776' );
define( 'SECURE_AUTH_SALT', 'O-`;xW-m,>y9wjRVY?Jl?|.av|!CR].;yxS{:VPk>#:k%8v%jrmlaJeL**F6$yV~' );
define( 'LOGGED_IN_SALT',   '/G3 [&ROg2IbOe%Lx`,& F&tZC$^Mz*uXt$M}&F$5e<]^,oe 4vxPZ,_MWWWW$5t' );
define( 'NONCE_SALT',       'rK |f5X^i7hJ;MIg,.@@:DZ7=5:Su)H^?AD;[:IU9{32CE$mFyA`K/k2y<0v|%kH' );

/**#@-*/

/**
 * WordPress データベーステーブルの接頭辞
 *
 * それぞれにユニーク (一意) な接頭辞を与えることで一つのデータベースに複数の WordPress を
 * インストールすることができます。半角英数字と下線のみを使用してください。
 */
$table_prefix = 'wp_';

/**
 * 開発者へ: WordPress デバッグモード
 *
 * この値を true にすると、開発中に注意 (notice) を表示します。
 * テーマおよびプラグインの開発者には、その開発環境においてこの WP_DEBUG を使用することを強く推奨します。
 *
 * その他のデバッグに利用できる定数についてはドキュメンテーションをご覧ください。
 *
 * @link https://ja.wordpress.org/support/article/debugging-in-wordpress/
 */
define( 'WP_DEBUG', false );


/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';

// ここを記載することによってサイトが正しく表示される。
update_option( 'siteurl', 'http://genkansha.sakura.ne.jp' );
update_option( 'home', 'http://genkansha.sakura.ne.jp' );