<?php
/**
 * Admin settings screen.
 *
 * @package MBD\CRM
 */

namespace MBD\CRM\Admin;

use MBD\CRM\Activator;
use MBD\CRM\Reminders\Scheduler;
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
		// Always-on: feed configured values into the module tuning filters.
		Config::register();

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

		add_settings_section(
			'mbd_crm_config',
			__( 'CRM configuration', 'mbd-crm' ),
			static function () {
				echo '<p>' . esc_html__( 'Tune the CRM business rules. Leave a field at its default to keep the built-in behaviour.', 'mbd-crm' ) . '</p>';
			},
			self::PAGE_SLUG
		);

		$fields = array(
			'sla_hours'          => __( 'Response SLA (hours)', 'mbd-crm' ),
			'discount_threshold' => __( 'Discount approval threshold (%)', 'mbd-crm' ),
			'service_areas'      => __( 'Service areas', 'mbd-crm' ),
			'reminders_enabled'  => __( 'Daily email reminders', 'mbd-crm' ),
		);
		foreach ( $fields as $key => $label ) {
			add_settings_field(
				$key,
				$label,
				array( $this, 'render_' . $key . '_field' ),
				self::PAGE_SLUG,
				'mbd_crm_config'
			);
		}
	}

	/**
	 * Render the response-SLA hours field.
	 *
	 * @return void
	 */
	public function render_sla_hours_field(): void {
		$value = (int) Config::get( Config::SLA_HOURS, 4 );
		printf(
			'<input type="number" min="1" max="720" name="%s[sla_hours]" value="%d" /> <span class="description">%s</span>',
			esc_attr( self::OPTION_KEY ),
			(int) $value,
			esc_html__( 'Hours sales has to make first contact before the SLA is breached.', 'mbd-crm' )
		);
	}

	/**
	 * Render the discount-threshold field.
	 *
	 * @return void
	 */
	public function render_discount_threshold_field(): void {
		$value = (float) Config::get( Config::DISCOUNT_THRESHOLD, 10 );
		printf(
			'<input type="number" min="0" max="100" step="0.5" name="%s[discount_threshold]" value="%s" /> <span class="description">%s</span>',
			esc_attr( self::OPTION_KEY ),
			esc_attr( (string) $value ),
			esc_html__( 'Offers discounted beyond this percentage need Owner/Admin approval before they can be sent.', 'mbd-crm' )
		);
	}

	/**
	 * Render the service-areas field.
	 *
	 * @return void
	 */
	public function render_service_areas_field(): void {
		$areas = (array) Config::get( Config::SERVICE_AREAS, array() );
		printf(
			'<textarea name="%s[service_areas]" rows="4" cols="40" placeholder="%s">%s</textarea><p class="description">%s</p>',
			esc_attr( self::OPTION_KEY ),
			esc_attr__( 'Jakarta Selatan&#10;Tangerang', 'mbd-crm' ),
			esc_textarea( implode( "\n", $areas ) ),
			esc_html__( 'One area per line. A lead whose project location matches an area scores full location-fit points.', 'mbd-crm' )
		);
	}

	/**
	 * Render the daily-reminders toggle.
	 *
	 * @return void
	 */
	public function render_reminders_enabled_field(): void {
		$checked = (bool) Config::get( Config::REMINDERS_ENABLED, true );
		printf(
			'<label><input type="checkbox" name="%s[reminders_enabled]" value="1" %s /> %s</label>',
			esc_attr( self::OPTION_KEY ),
			checked( $checked, true, false ),
			esc_html__( 'Email each operator a daily digest of leads needing their attention.', 'mbd-crm' )
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

		// Response SLA: clamp to a sane 1-720 hour window.
		$existing[ Config::SLA_HOURS ] = min( 720, max( 1, (int) ( $input['sla_hours'] ?? 4 ) ) );

		// Discount threshold: clamp to 0-100 percent.
		$existing[ Config::DISCOUNT_THRESHOLD ] = min( 100.0, max( 0.0, (float) ( $input['discount_threshold'] ?? 10 ) ) );

		// Service areas: one per line, trimmed, de-duplicated.
		$areas = array();
		foreach ( preg_split( '/[\r\n]+/', (string) ( $input['service_areas'] ?? '' ) ) as $line ) {
			$line = sanitize_text_field( trim( $line ) );
			if ( '' !== $line ) {
				$areas[] = $line;
			}
		}
		$existing[ Config::SERVICE_AREAS ] = array_values( array_unique( $areas ) );

		// Daily reminders toggle, kept in sync with the cron schedule.
		$reminders                             = ! empty( $input['reminders_enabled'] );
		$existing[ Config::REMINDERS_ENABLED ] = $reminders;
		if ( $reminders ) {
			Scheduler::schedule();
		} else {
			Scheduler::unschedule();
		}

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
