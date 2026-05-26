<?php
/**
 * Admin settings screen.
 *
 * @package MBD\CRM
 */

namespace MBD\CRM\Admin;

use MBD\CRM\Activator;
use MBD\CRM\View;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the "MBD CRM" admin menu and a settings skeleton.
 */
class Settings {

	/**
	 * Settings group / option name.
	 */
	public const OPTION_KEY = Activator::OPTION_KEY;

	/**
	 * Settings page slug.
	 */
	public const PAGE_SLUG = MBD_CRM_SLUG;

	/**
	 * Hook into WordPress.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Add the top-level "MBD CRM" admin menu.
	 *
	 * @return void
	 */
	public function add_menu(): void {
		add_menu_page(
			__( 'MBD CRM', 'mbd-crm' ),
			__( 'MBD CRM', 'mbd-crm' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' ),
			'dashicons-groups',
			58
		);
	}

	/**
	 * Register the settings, section, and fields.
	 *
	 * @return void
	 */
	public function register_settings(): void {
		register_setting(
			self::PAGE_SLUG,
			self::OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => array(),
			)
		);

		add_settings_section(
			'mbd_crm_general',
			__( 'General', 'mbd-crm' ),
			static function () {
				echo '<p>' . esc_html__( 'Foundational settings for the MBD CRM plugin.', 'mbd-crm' ) . '</p>';
			},
			self::PAGE_SLUG
		);

		add_settings_field(
			'remove_data',
			__( 'Remove data on uninstall', 'mbd-crm' ),
			array( $this, 'render_remove_data_field' ),
			self::PAGE_SLUG,
			'mbd_crm_general'
		);
	}

	/**
	 * Render the "remove data on uninstall" checkbox.
	 *
	 * @return void
	 */
	public function render_remove_data_field(): void {
		$settings = (array) get_option( self::OPTION_KEY, array() );
		$checked  = ! empty( $settings['remove_data'] );
		?>
		<label>
			<input
				type="checkbox"
				name="<?php echo esc_attr( self::OPTION_KEY ); ?>[remove_data]"
				value="1"
				<?php checked( $checked ); ?>
			/>
			<?php esc_html_e( 'Delete all plugin data when the plugin is uninstalled.', 'mbd-crm' ); ?>
		</label>
		<?php
	}

	/**
	 * Sanitize submitted settings.
	 *
	 * Merges incoming values onto existing settings so metadata such as
	 * the install timestamp is preserved.
	 *
	 * @param mixed $input Raw submitted values.
	 * @return array<string, mixed>
	 */
	public function sanitize( $input ): array {
		$existing = (array) get_option( self::OPTION_KEY, array() );
		$input    = is_array( $input ) ? $input : array();

		$existing['remove_data'] = ! empty( $input['remove_data'] );
		$existing['version']     = MBD_CRM_VERSION;

		return $existing;
	}

	/**
	 * Render the settings page.
	 *
	 * @return void
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		( new View() )->render(
			'admin/settings',
			array(
				'page_slug' => self::PAGE_SLUG,
				'title'     => __( 'MBD CRM', 'mbd-crm' ),
			)
		);
	}
}
