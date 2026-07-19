<?php
/**
 * Remote version manifest + admin update status for digtiali-stockpik.
 *
 * Manifest URL (default: GitHub raw version.json). Override in wp-config.php:
 *   define( 'DIGTIALI_STOCKPIK_UPDATE_MANIFEST_URL', 'https://example.com/version.json' );
 *
 * @package Digtiali Stockpik
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
	exit;
}

const DIGTIALI_STOCKPIK_VERSION_TRANSIENT       = 'digtiali_stockpik_remote_manifest_v1';
const DIGTIALI_STOCKPIK_VERSION_ERROR_TRANSIENT = 'digtiali_stockpik_remote_manifest_error_v1';

function digtiali_stockpik_github_update_token(): string {
	if (defined('DIGTIALI_GITHUB_UPDATE_TOKEN') && DIGTIALI_GITHUB_UPDATE_TOKEN !== '') {
		return (string) DIGTIALI_GITHUB_UPDATE_TOKEN;
	}
	if (defined('DIGTIALI_STOCKPIK_UPDATE_GITHUB_TOKEN') && DIGTIALI_STOCKPIK_UPDATE_GITHUB_TOKEN !== '') {
		return (string) DIGTIALI_STOCKPIK_UPDATE_GITHUB_TOKEN;
	}
	return '';
}

function digtiali_stockpik_github_repo_slug(): string {
	$manifest = digtiali_stockpik_get_local_manifest();
	$repo     = (string) ($manifest['repository'] ?? '');
	if (preg_match('#github\.com/([^/]+/[^/\s]+)#i', $repo, $matches)) {
		return rtrim($matches[1], '.git');
	}
	return 'muhammedaslan34/digtiali-stockpik-plugin';
}

/**
 * @return array<string, mixed>|null
 */
function digtiali_stockpik_decode_github_contents_payload(array $payload): ?array {
	if (($payload['encoding'] ?? '') !== 'base64' || empty($payload['content'])) {
		return null;
	}
	$raw = base64_decode(str_replace(array("\n", "\r", ' '), '', (string) $payload['content']), true);
	if ($raw === false) {
		return null;
	}
	$decoded = json_decode($raw, true);
	return is_array($decoded) ? $decoded : null;
}

/**
 * @return array{manifest: array<string, mixed>|null, error: string|null, http_code: int|null}
 */
function digtiali_stockpik_request_remote_manifest(bool $force = false): array {
	if (! $force) {
		$cached = get_transient(DIGTIALI_STOCKPIK_VERSION_TRANSIENT);
		if (is_array($cached) && ! empty($cached['version'])) {
			return array(
				'manifest'  => $cached,
				'error'     => null,
				'http_code' => 200,
			);
		}
	}

	$token = digtiali_stockpik_github_update_token();
	if ($token !== '') {
		$response = wp_remote_get(
			sprintf('https://api.github.com/repos/%s/contents/version.json?ref=main', digtiali_stockpik_github_repo_slug()),
			array(
				'timeout'   => 15,
				'headers'   => array(
					'Accept'        => 'application/vnd.github+json',
					'Authorization' => 'Bearer ' . $token,
					'User-Agent'    => 'Digtiali-WordPress-Plugin-Updater',
				),
				'sslverify' => true,
			)
		);
	} else {
		$response = wp_remote_get(
			digtiali_stockpik_update_manifest_url(),
			array(
				'timeout'   => 15,
				'headers'   => array(
					'Accept'     => 'application/json',
					'User-Agent' => 'Digtiali-WordPress-Plugin-Updater',
				),
				'sslverify' => true,
			)
		);
	}

	if (is_wp_error($response)) {
		$error = $response->get_error_message();
		set_transient(DIGTIALI_STOCKPIK_VERSION_ERROR_TRANSIENT, $error, 15 * MINUTE_IN_SECONDS);
		return array(
			'manifest'  => null,
			'error'     => $error,
			'http_code' => null,
		);
	}

	$code = (int) wp_remote_retrieve_response_code($response);
	$body = wp_remote_retrieve_body($response);

	if ($code < 200 || $code >= 300) {
		if (404 === $code && $token === '') {
			$error = __('GitHub returned 404. Your repository is probably private — add DIGTIALI_GITHUB_UPDATE_TOKEN to wp-config.php (read-only GitHub token), or make the repo public.', 'digtiali-stockpik');
		} elseif (404 === $code) {
			$error = __('GitHub returned 404. Check that version.json exists on main and the token has repo access.', 'digtiali-stockpik');
		} elseif (401 === $code || 403 === $code) {
			$error = __('GitHub rejected the token (401/403). Update DIGTIALI_GITHUB_UPDATE_TOKEN in wp-config.php.', 'digtiali-stockpik');
		} else {
			$error = sprintf(
				/* translators: %d: HTTP status code */
				__('Remote manifest HTTP error %d.', 'digtiali-stockpik'),
				$code
			);
		}
		set_transient(DIGTIALI_STOCKPIK_VERSION_ERROR_TRANSIENT, $error, 15 * MINUTE_IN_SECONDS);
		return array(
			'manifest'  => null,
			'error'     => $error,
			'http_code' => $code,
		);
	}

	if ($token !== '') {
		$api_payload = json_decode($body, true);
		$decoded     = is_array($api_payload) ? digtiali_stockpik_decode_github_contents_payload($api_payload) : null;
	} else {
		$decoded = json_decode($body, true);
	}

	if (! is_array($decoded) || empty($decoded['version'])) {
		$error = __('Remote version.json is missing or invalid.', 'digtiali-stockpik');
		set_transient(DIGTIALI_STOCKPIK_VERSION_ERROR_TRANSIENT, $error, 15 * MINUTE_IN_SECONDS);
		return array(
			'manifest'  => null,
			'error'     => $error,
			'http_code' => $code,
		);
	}

	$decoded['_fetched_at'] = time();
	$decoded['_source_url'] = $token !== ''
		? 'https://api.github.com/repos/' . digtiali_stockpik_github_repo_slug() . '/contents/version.json?ref=main'
		: digtiali_stockpik_update_manifest_url();

	delete_transient(DIGTIALI_STOCKPIK_VERSION_ERROR_TRANSIENT);
	set_transient(DIGTIALI_STOCKPIK_VERSION_TRANSIENT, $decoded, 12 * HOUR_IN_SECONDS);

	return array(
		'manifest'  => $decoded,
		'error'     => null,
		'http_code' => $code,
	);
}

function digtiali_stockpik_update_manifest_url(): string {
	if (defined('DIGTIALI_STOCKPIK_UPDATE_MANIFEST_URL') && DIGTIALI_STOCKPIK_UPDATE_MANIFEST_URL !== '') {
		return (string) DIGTIALI_STOCKPIK_UPDATE_MANIFEST_URL;
	}

	return 'https://raw.githubusercontent.com/muhammedaslan34/digtiali-stockpik-plugin/main/version.json';
}

/**
 * @return array<string, mixed>
 */
function digtiali_stockpik_get_local_manifest(): array {
	static $cached = null;

	if (is_array($cached)) {
		return $cached;
	}

	$cached = array(
		'slug'    => 'digtiali-stockpik',
		'name'    => 'Digtiali Stockpik',
		'version' => DIGTIALI_STOCKPIK_VERSION,
	);

	$path = DIGTIALI_STOCKPIK_PATH . 'version.json';
	if (! is_readable($path)) {
		return $cached;
	}

	$decoded = json_decode((string) file_get_contents($path), true);
	if (! is_array($decoded)) {
		return $cached;
	}

	$cached = array_merge($cached, $decoded);

	return $cached;
}

function digtiali_stockpik_version_compare(string $installed, string $remote): int {
	return version_compare($installed, $remote);
}

/**
 * @return array{
 *   installed: string,
 *   remote: string|null,
 *   update_available: bool,
 *   local: array<string, mixed>,
 *   remote_manifest: array<string, mixed>|null,
 *   last_checked: int|null,
 *   manifest_url: string,
 *   fetch_error: string|null,
 *   github_token_set: bool
 * }
 */
function digtiali_stockpik_get_update_status(bool $force = false): array {
	$local           = digtiali_stockpik_get_local_manifest();
	$installed       = (string) ($local['version'] ?? DIGTIALI_STOCKPIK_VERSION);
	$request         = digtiali_stockpik_request_remote_manifest($force);
	$remote_manifest = $request['manifest'];
	$remote          = is_array($remote_manifest) ? (string) ($remote_manifest['version'] ?? '') : null;
	$error           = $request['error'];
	if ($error === null || $error === '') {
		$cached_error = get_transient(DIGTIALI_STOCKPIK_VERSION_ERROR_TRANSIENT);
		$error        = is_string($cached_error) && $cached_error !== '' ? $cached_error : null;
	}

	return array(
		'installed'        => $installed,
		'remote'           => $remote !== '' ? $remote : null,
		'update_available' => $remote !== null && $remote !== '' && digtiali_stockpik_version_compare($installed, $remote) < 0,
		'local'            => $local,
		'remote_manifest'  => $remote_manifest,
		'last_checked'     => is_array($remote_manifest) ? (int) ($remote_manifest['_fetched_at'] ?? 0) : null,
		'manifest_url'     => digtiali_stockpik_update_manifest_url(),
		'fetch_error'      => $error,
		'github_token_set' => digtiali_stockpik_github_update_token() !== '',
	);
}

add_action('admin_menu', 'digtiali_stockpik_register_updates_submenu', 25);
function digtiali_stockpik_register_updates_submenu(): void {
	add_submenu_page(
		'options-general.php',
		__('Plugin updates', 'digtiali-stockpik'),
		__('Updates', 'digtiali-stockpik'),
		'manage_options',
		'digtiali-stockpik-updates',
		'digtiali_stockpik_render_updates_admin_page'
	);
}

add_action('admin_notices', 'digtiali_stockpik_update_available_admin_notice');
function digtiali_stockpik_update_available_admin_notice(): void {
	if (! current_user_can('manage_options')) {
		return;
	}

	$screen = function_exists('get_current_screen') ? get_current_screen() : null;
	if ($screen && 'settings_page_digtiali-stockpik-updates' === $screen->id) {
		return;
	}

	$status = digtiali_stockpik_get_update_status(false);
	if (empty($status['update_available']) || empty($status['remote'])) {
		return;
	}

	$url = admin_url('options-general.php?page=digtiali-stockpik-updates');
	echo '<div class="notice notice-warning is-dismissible"><p>';
	printf(
		/* translators: 1: remote version, 2: admin page link */
		esc_html__('Digtiali Stockpik update available: version %1$s. %2$s', 'digtiali-stockpik'),
		esc_html((string) $status['remote']),
		'<a href="' . esc_url($url) . '">' . esc_html__('View details', 'digtiali-stockpik') . '</a>'
	);
	echo '</p></div>';
}

add_filter('plugin_action_links_' . plugin_basename(DIGTIALI_STOCKPIK_PATH . 'digtiali-stockpik.php'), 'digtiali_stockpik_plugin_action_links');
/**
 * @param string[] $links
 * @return string[]
 */
function digtiali_stockpik_plugin_action_links(array $links): array {
	$links[] = '<a href="' . esc_url(admin_url('options-general.php?page=digtiali-stockpik-updates')) . '">' . esc_html__('Updates', 'digtiali-stockpik') . '</a>';

	return $links;
}

/**
 * @param array<string, mixed> $manifest
 * @return list<array{version: string, date: string, changes: list<string>}>
 */
function digtiali_stockpik_normalize_changelog(array $manifest): array {
	$entries = $manifest['changelog'] ?? array();
	if (! is_array($entries)) {
		return array();
	}

	$normalized = array();
	foreach ($entries as $entry) {
		if (! is_array($entry)) {
			continue;
		}
		$changes = $entry['changes'] ?? array();
		if (! is_array($changes)) {
			$changes = array();
		}
		$normalized[] = array(
			'version' => (string) ($entry['version'] ?? ''),
			'date'    => (string) ($entry['date'] ?? ''),
			'changes' => array_values(array_map('strval', $changes)),
		);
	}

	return $normalized;
}

/**
 * Download GitHub main-branch zipball to a local temp .zip path.
 *
 * @return string|\WP_Error Absolute path to .zip on success.
 */
function digtiali_stockpik_download_github_zipball() {
	$token   = digtiali_stockpik_github_update_token();
	$api_url = sprintf(
		'https://api.github.com/repos/%s/zipball/main',
		digtiali_stockpik_github_repo_slug()
	);

	$tmp = wp_tempnam( 'digtiali-stockpik-update-' );
	if ( ! is_string( $tmp ) || $tmp === '' ) {
		return new WP_Error(
			'digtiali_stockpik_temp',
			__( 'Could not create a temporary file for the plugin download.', 'digtiali-stockpik' )
		);
	}

	$headers = array(
		'Accept'     => 'application/vnd.github+json',
		'User-Agent' => 'Digtiali-WordPress-Plugin-Updater',
	);
	if ( $token !== '' ) {
		$headers['Authorization'] = 'Bearer ' . $token;
	}

	$response = wp_remote_get(
		$api_url,
		array(
			'timeout'     => 300,
			'headers'     => $headers,
			'stream'      => true,
			'filename'    => $tmp,
			'redirection' => 5,
			'sslverify'   => true,
		)
	);

	if ( is_wp_error( $response ) ) {
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- cleanup best-effort.
		@unlink( $tmp );
		return $response;
	}

	$code = (int) wp_remote_retrieve_response_code( $response );
	if ( $code < 200 || $code >= 300 ) {
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		@unlink( $tmp );
		if ( ( 401 === $code || 403 === $code || 404 === $code ) && $token === '' ) {
			return new WP_Error(
				'digtiali_stockpik_github_auth',
				__( 'GitHub blocked the download. Add DIGTIALI_GITHUB_UPDATE_TOKEN to wp-config.php for this private repo.', 'digtiali-stockpik' )
			);
		}
		return new WP_Error(
			'digtiali_stockpik_github_http',
			sprintf(
				/* translators: %d: HTTP status code */
				__( 'GitHub zipball download failed (HTTP %d).', 'digtiali-stockpik' ),
				$code
			)
		);
	}

	if ( ! is_readable( $tmp ) || (int) filesize( $tmp ) < 1000 ) {
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		@unlink( $tmp );
		return new WP_Error(
			'digtiali_stockpik_empty_zip',
			__( 'Downloaded plugin package is empty or unreadable.', 'digtiali-stockpik' )
		);
	}

	$zip = $tmp . '.zip';
	// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	if ( ! @rename( $tmp, $zip ) ) {
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		@unlink( $tmp );
		return new WP_Error(
			'digtiali_stockpik_rename_zip',
			__( 'Could not prepare the downloaded zip package.', 'digtiali-stockpik' )
		);
	}

	return $zip;
}

/**
 * Rename GitHub zipball folder (owner-repo-sha/) to the plugin directory slug.
 *
 * @param string      $source        Extracted source path.
 * @param string      $remote_source Parent extraction directory.
 * @param WP_Upgrader $upgrader      Upgrader instance.
 * @param array       $hook_extra    Extra hook data.
 * @return string|\WP_Error
 */
function digtiali_stockpik_upgrader_source_selection( $source, $remote_source, $upgrader, $hook_extra = array() ) {
	unset( $hook_extra );

	if ( empty( $GLOBALS['digtiali_stockpik_plugin_updating'] ) || ! $upgrader instanceof Plugin_Upgrader ) {
		return $source;
	}

	global $wp_filesystem;
	if ( ! $wp_filesystem instanceof WP_Filesystem_Base ) {
		return new WP_Error(
			'digtiali_stockpik_fs',
			__( 'WordPress filesystem is not available.', 'digtiali-stockpik' )
		);
	}

	$slug      = 'digtiali-stockpik';
	$corrected = trailingslashit( $remote_source ) . $slug;

	if ( trailingslashit( (string) $source ) === trailingslashit( $corrected ) ) {
		return $source;
	}

	if ( $wp_filesystem->is_dir( $corrected ) ) {
		return trailingslashit( $corrected );
	}

	if ( ! $wp_filesystem->move( $source, $corrected ) ) {
		return new WP_Error(
			'digtiali_stockpik_rename_plugin',
			__( 'Could not rename the GitHub zipball folder to the plugin slug.', 'digtiali-stockpik' )
		);
	}

	return trailingslashit( $corrected );
}

/**
 * Install/overwrite this plugin from the GitHub main zipball.
 *
 * @return true|\WP_Error
 */
function digtiali_stockpik_install_plugin_from_github() {
	if ( ! current_user_can( 'update_plugins' ) ) {
		return new WP_Error(
			'digtiali_stockpik_cap',
			__( 'You do not have permission to update plugins.', 'digtiali-stockpik' )
		);
	}

	$status = digtiali_stockpik_get_update_status( true );
	if ( empty( $status['update_available'] ) || empty( $status['remote'] ) ) {
		return new WP_Error(
			'digtiali_stockpik_no_update',
			__( 'No plugin update is available.', 'digtiali-stockpik' )
		);
	}

	if ( ! function_exists( 'request_filesystem_credentials' ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
	}
	require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
	require_once ABSPATH . 'wp-admin/includes/misc.php';
	require_once ABSPATH . 'wp-admin/includes/plugin.php';

	ob_start();
	$creds = request_filesystem_credentials( '', '', false, false, null );
	ob_end_clean();

	if ( false === $creds ) {
		$ready = WP_Filesystem();
	} else {
		$ready = WP_Filesystem( $creds );
	}

	if ( ! $ready ) {
		return new WP_Error(
			'digtiali_stockpik_fs_init',
			__( 'Could not initialize the WordPress filesystem to install the plugin.', 'digtiali-stockpik' )
		);
	}

	$zip = digtiali_stockpik_download_github_zipball();
	if ( is_wp_error( $zip ) ) {
		return $zip;
	}

	$GLOBALS['digtiali_stockpik_plugin_updating'] = true;
	add_filter( 'upgrader_source_selection', 'digtiali_stockpik_upgrader_source_selection', 10, 4 );

	$skin     = new Automatic_Upgrader_Skin();
	$upgrader = new Plugin_Upgrader( $skin );
	$result   = $upgrader->install(
		$zip,
		array(
			'clear_update_cache' => true,
			'overwrite_package'  => true,
		)
	);

	remove_filter( 'upgrader_source_selection', 'digtiali_stockpik_upgrader_source_selection', 10 );
	unset( $GLOBALS['digtiali_stockpik_plugin_updating'] );

	// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	@unlink( $zip );

	if ( is_wp_error( $result ) ) {
		return $result;
	}
	if ( true !== $result ) {
		$messages = method_exists( $skin, 'get_upgrade_messages' ) ? $skin->get_upgrade_messages() : array();
		$detail   = is_array( $messages ) && $messages !== array()
			? implode( ' ', array_map( 'wp_strip_all_tags', $messages ) )
			: __( 'Plugin installation failed.', 'digtiali-stockpik' );
		return new WP_Error( 'digtiali_stockpik_install_failed', $detail );
	}

	delete_transient( DIGTIALI_STOCKPIK_VERSION_TRANSIENT );
	delete_transient( DIGTIALI_STOCKPIK_VERSION_ERROR_TRANSIENT );
	wp_clean_plugins_cache( true );

	// Keep the plugin active after overwrite install.
	$plugin_file = plugin_basename( DIGTIALI_STOCKPIK_PATH . 'digtiali-stockpik.php' );
	if ( ! is_plugin_active( $plugin_file ) ) {
		activate_plugin( $plugin_file, '', false, true );
	}

	return true;
}

/**
 * Handle Update now form POST before the admin page renders.
 */
function digtiali_stockpik_maybe_handle_plugin_update_request(): void {
	if ( ! is_admin() || ! isset( $_POST['digtiali_stockpik_do_update'] ) ) {
		return;
	}

	if ( ! current_user_can( 'update_plugins' ) ) {
		wp_die( esc_html__( 'You do not have permission to update plugins.', 'digtiali-stockpik' ) );
	}

	check_admin_referer( 'digtiali_stockpik_do_update' );

	$result = digtiali_stockpik_install_plugin_from_github();
	$url    = admin_url('options-general.php?page=digtiali-stockpik-updates');

	if ( is_wp_error( $result ) ) {
		set_transient(
			'digtiali_stockpik_update_flash',
			array(
				'type'    => 'error',
				'message' => $result->get_error_message(),
			),
			60
		);
		wp_safe_redirect( $url );
		exit;
	}

	set_transient(
		'digtiali_stockpik_update_flash',
		array(
			'type'    => 'success',
			'message' => __( 'Plugin updated successfully from GitHub.', 'digtiali-stockpik' ),
		),
		60
	);
	wp_safe_redirect( add_query_arg( 'updated', '1', $url ) );
	exit;
}
add_action( 'admin_init', 'digtiali_stockpik_maybe_handle_plugin_update_request' );


function digtiali_stockpik_render_updates_admin_page(): void {
	if (! current_user_can('manage_options')) {
		wp_die(esc_html__('You do not have permission to access this page.', 'digtiali-stockpik'));
	}

	if (
		isset($_GET['refresh'], $_GET['_wpnonce'])
		&& wp_verify_nonce(sanitize_text_field(wp_unslash((string) $_GET['_wpnonce'])), 'digtiali_stockpik_refresh_version')
	) {
		delete_transient(DIGTIALI_STOCKPIK_VERSION_TRANSIENT);
		delete_transient(DIGTIALI_STOCKPIK_VERSION_ERROR_TRANSIENT);
	}

	$status      = digtiali_stockpik_get_update_status(! empty($_GET['refresh']) || ! empty($_GET['updated']));
	$remote      = is_array($status['remote_manifest']) ? $status['remote_manifest'] : array();
	$changelog   = digtiali_stockpik_normalize_changelog($remote !== array() ? $remote : $status['local']);
	$repo        = (string) ($remote['repository'] ?? $status['local']['repository'] ?? '');
	$refresh_url = wp_nonce_url(
		admin_url('options-general.php?page=digtiali-stockpik-updates&refresh=1'),
		'digtiali_stockpik_refresh_version'
	);
	$flash = get_transient( 'digtiali_stockpik_update_flash' );
	if ( is_array( $flash ) ) {
		delete_transient( 'digtiali_stockpik_update_flash' );
	}
	$can_update = current_user_can( 'update_plugins' );

	?>
	<div class="wrap digi-stockpik-updates">
		<h1><?php esc_html_e('Digtiali Stockpik — Updates', 'digtiali-stockpik'); ?></h1>


		<?php if ( is_array( $flash ) && ! empty( $flash['message'] ) ) : ?>
			<div class="notice notice-<?php echo 'success' === ( $flash['type'] ?? '' ) ? 'success' : 'error'; ?> is-dismissible digi-stockpik-updates__notice">
				<p><?php echo esc_html( (string) $flash['message'] ); ?></p>
			</div>
		<?php endif; ?>
		<?php if (! empty($status['fetch_error'])) : ?>
			<div class="notice notice-error inline digi-stockpik-updates__notice">
				<p><strong><?php esc_html_e('Could not load remote version.json', 'digtiali-stockpik'); ?></strong></p>
				<p><?php echo esc_html((string) $status['fetch_error']); ?></p>
				<?php if (empty($status['github_token_set'])) : ?>
					<p><?php esc_html_e('Add this to wp-config.php (above “stop editing”), then click Check for updates again:', 'digtiali-stockpik'); ?></p>
					<pre class="digi-stockpik-updates__code">define( 'DIGTIALI_GITHUB_UPDATE_TOKEN', 'ghp_your_read_only_token' );</pre>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<div class="digi-stockpik-updates__grid">
			<div class="card">
				<h2><?php esc_html_e('Version status', 'digtiali-stockpik'); ?></h2>
				<table class="widefat striped">
					<tbody>
						<tr>
							<th><?php esc_html_e('Installed version', 'digtiali-stockpik'); ?></th>
							<td><code><?php echo esc_html($status['installed']); ?></code></td>
						</tr>
						<tr>
							<th><?php esc_html_e('Remote version (JSON)', 'digtiali-stockpik'); ?></th>
							<td>
								<?php if ($status['remote']) : ?>
									<code><?php echo esc_html((string) $status['remote']); ?></code>
								<?php else : ?>
									<span class="description"><?php esc_html_e('Could not load', 'digtiali-stockpik'); ?></span>
								<?php endif; ?>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e('Status', 'digtiali-stockpik'); ?></th>
							<td>
								<?php if ($status['update_available']) : ?>
									<span class="digi-stockpik-updates__badge digi-stockpik-updates__badge--warn"><?php esc_html_e('Update available', 'digtiali-stockpik'); ?></span>
								<?php elseif ($status['remote']) : ?>
									<span class="digi-stockpik-updates__badge digi-stockpik-updates__badge--ok"><?php esc_html_e('Up to date', 'digtiali-stockpik'); ?></span>
								<?php else : ?>
									<span class="digi-stockpik-updates__badge"><?php esc_html_e('Unknown', 'digtiali-stockpik'); ?></span>
								<?php endif; ?>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e('Last checked', 'digtiali-stockpik'); ?></th>
							<td>
								<?php
								if (! empty($status['last_checked'])) {
									echo esc_html(wp_date(get_option('date_format') . ' ' . get_option('time_format'), (int) $status['last_checked']));
								} else {
									esc_html_e('Never', 'digtiali-stockpik');
								}
								?>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e('GitHub token (wp-config)', 'digtiali-stockpik'); ?></th>
							<td>
								<?php if (! empty($status['github_token_set'])) : ?>
									<span class="digi-stockpik-updates__badge digi-stockpik-updates__badge--ok"><?php esc_html_e('Configured', 'digtiali-stockpik'); ?></span>
								<?php else : ?>
									<span class="digi-stockpik-updates__badge digi-stockpik-updates__badge--warn"><?php esc_html_e('Not set', 'digtiali-stockpik'); ?></span>
								<?php endif; ?>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e('Manifest URL', 'digtiali-stockpik'); ?></th>
							<td><code><?php echo esc_html($status['manifest_url']); ?></code></td>
						</tr>
					</tbody>
				</table>
				<p class="digi-stockpik-updates__actions">
					<?php if ( ! empty( $status['update_available'] ) && $can_update ) : ?>
						<form method="post" action="" style="display:inline;">
							<?php wp_nonce_field( 'digtiali_stockpik_do_update' ); ?>
							<button type="submit" name="digtiali_stockpik_do_update" value="1" class="button button-primary button-hero">
								<?php
								printf(
									/* translators: %s: remote plugin version */
									esc_html__( 'Update to %s now', 'digtiali-stockpik' ),
									esc_html( (string) $status['remote'] )
								);
								?>
							</button>
						</form>
					<?php elseif ( ! empty( $status['update_available'] ) && ! $can_update ) : ?>
						<span class="description"><?php esc_html_e( 'An update is available, but your user cannot install plugins (needs update_plugins capability).', 'digtiali-stockpik' ); ?></span>
					<?php endif; ?>
					<a class="button<?php echo empty( $status['update_available'] ) ? ' button-primary' : ''; ?>" href="<?php echo esc_url( $refresh_url ); ?>"><?php esc_html_e( 'Check for updates now', 'digtiali-stockpik' ); ?></a>
				</p>
			</div>

			<div class="card">
				<h2><?php esc_html_e('How to deploy', 'digtiali-stockpik'); ?></h2>
				<p><?php esc_html_e('Recommended: use “Update to … now” above. Or deploy manually on the server with git:', 'digtiali-stockpik'); ?></p>
				<pre class="digi-stockpik-updates__code">cd /var/www/digtialistore-9odr.1wp.site/wp-content/plugins/digtiali-stockpik
git pull origin main</pre>
				<?php if ($repo !== '') : ?>
					<p><a href="<?php echo esc_url($repo); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Open GitHub repository', 'digtiali-stockpik'); ?></a></p>
				<?php endif; ?>
			</div>
		</div>

		<div class="card digi-stockpik-updates__changelog">
			<h2><?php esc_html_e('Changelog', 'digtiali-stockpik'); ?></h2>
			<?php if ($changelog === array()) : ?>
				<p class="description"><?php esc_html_e('No changelog entries in version.json yet.', 'digtiali-stockpik'); ?></p>
			<?php else : ?>
				<?php foreach ($changelog as $entry) : ?>
					<h3>
						<?php echo esc_html(sprintf('v%s', $entry['version'])); ?>
						<?php if ($entry['date'] !== '') : ?>
							<small>(<?php echo esc_html($entry['date']); ?>)</small>
						<?php endif; ?>
					</h3>
					<ul>
						<?php foreach ($entry['changes'] as $line) : ?>
							<li><?php echo esc_html($line); ?></li>
						<?php endforeach; ?>
					</ul>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>

		<div class="card">
			<h2><?php esc_html_e('Workflow — when you edit the plugin', 'digtiali-stockpik'); ?></h2>
			<ol>
				<li><?php esc_html_e('Bump the version with: php scripts/bump-version.php X.Y.Z "Your change summary"', 'digtiali-stockpik'); ?></li>
				<li><?php esc_html_e('Commit and push to GitHub (main branch).', 'digtiali-stockpik'); ?></li>
				<li><?php esc_html_e('On production: open Updates and click “Update to … now” (or git pull).', 'digtiali-stockpik'); ?></li>
				<li><?php echo esc_html(sprintf(__('Open %s and click “Check for updates now”.', 'digtiali-stockpik'), 'Settings → Digtiali Stockpik → Updates')); ?></li>
			</ol>
		</div>
	</div>
	<style>
		.digi-stockpik-updates__notice { margin: 12px 0 16px; max-width: 960px; }
		.digi-stockpik-updates__grid { display: grid; gap: 16px; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); margin: 16px 0; }
		.digi-stockpik-updates .card { background: #fff; border: 1px solid #c3c4c7; border-radius: 8px; padding: 16px 20px; box-shadow: 0 1px 1px rgba(0,0,0,.04); max-width: 100%; }
		.digi-stockpik-updates__badge { display: inline-block; padding: 2px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; background: #f0f0f1; }
		.digi-stockpik-updates__badge--ok { background: #d1fae5; color: #065f46; }
		.digi-stockpik-updates__badge--warn { background: #fef3c7; color: #92400e; }
		.digi-stockpik-updates__code { direction: ltr; text-align: left; background: #1e1e1e; color: #d4d4d4; padding: 12px 14px; border-radius: 6px; overflow-x: auto; }
		.digi-stockpik-updates__changelog ul { margin-top: 0; }
		.digi-stockpik-updates__actions { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }

	</style>
	<?php
}

add_action('rest_api_init', 'digtiali_stockpik_register_version_rest_route');
function digtiali_stockpik_register_version_rest_route(): void {
	register_rest_route(
		'digtiali/v1',
		'/stockpik-version',
		array(
			'methods'             => 'GET',
			'permission_callback' => static function (): bool {
				return current_user_can('manage_options');
			},
			'callback'            => static function (): WP_REST_Response {
				$status = digtiali_stockpik_get_update_status(false);

				return new WP_REST_Response(
					array(
						'installed'        => $status['installed'],
						'remote'           => $status['remote'],
						'update_available' => $status['update_available'],
						'manifest_url'     => $status['manifest_url'],
						'last_checked'     => $status['last_checked'],
					),
					200
				);
			},
		)
	);
}
