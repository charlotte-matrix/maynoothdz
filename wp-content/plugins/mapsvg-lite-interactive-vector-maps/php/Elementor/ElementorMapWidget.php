<?php

namespace MapSVG;

/**
 * Elementor widget: embed a MapSVG map with live editor preview.
 */
class ElementorMapWidget extends \Elementor\Widget_Base
{
	/**
	 * @return string
	 */
	public function get_name(): string
	{
		return 'mapsvg';
	}

	/**
	 * @return string
	 */
	public function get_title(): string
	{
		return 'MapSVG';
	}

	/**
	 * @return string
	 */
	public function get_icon(): string
	{
		return 'mapsvg-el-icon';
	}

	/**
	 * @return string[]
	 */
	public function get_categories(): array
	{
		return array('mapsvg', 'general');
	}

	/**
	 * @return string[]
	 */
	public function get_keywords(): array
	{
		return array('map', 'mapsvg', 'svg', 'interactive');
	}

	/**
	 * @return void
	 */
	protected function register_controls(): void
	{
		$this->start_controls_section(
			'section_map',
			array(
				'label' => __('Map', 'mapsvg'),
			)
		);

		$this->add_control(
			'map_id',
			array(
				'label'   => __('Map', 'mapsvg'),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'options' => $this->getMapOptions(),
				'default' => '0',
			)
		);

		$this->add_control(
			'selected',
			array(
				'label'       => __('Selected Region ID', 'mapsvg'),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => '',
				'label_block' => true,
			)
		);

		$this->add_control(
			'lazy',
			array(
				'label'        => __('Lazy load', 'mapsvg'),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => __('Yes', 'mapsvg'),
				'label_off'    => __('No', 'mapsvg'),
				'return_value' => 'yes',
				'default'      => '',
				'description'  => __('Load the map when it enters the viewport (front end only).', 'mapsvg'),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * @return array<string, string>
	 */
	private function getMapOptions(): array
	{
		$options = array(
			'0' => __('Select a map…', 'mapsvg'),
		);

		try {
			$mapsRepo = RepositoryFactory::get('map');
			$result   = $mapsRepo->find(new Query(array('perpage' => 0)));
			$items    = isset($result['items']) ? $result['items'] : array();
			foreach ($items as $map) {
				$id = (string) $map->id;
				$options[$id] = $map->title ? (string) $map->title : sprintf('Map #%s', $id);
			}
		} catch (\Throwable $e) {
			// Keep placeholder-only list if maps cannot be loaded.
		}

		return $options;
	}

	/**
	 * Frontend (and Elementor preview PHP render).
	 *
	 * @return void
	 */
	protected function render(): void
	{
		$settings = $this->get_settings_for_display();
		$mapId    = isset($settings['map_id']) ? absint($settings['map_id']) : 0;

		if ($mapId <= 0) {
			if ($this->isEditMode()) {
				echo '<div class="mapsvg-elementor-placeholder" style="padding:24px;border:1px dashed #ccc;text-align:center;color:#666;">';
				echo esc_html__('Choose a MapSVG map in the widget settings.', 'mapsvg');
				echo '</div>';
			}
			return;
		}

		$atts = array(
			'id' => (string) $mapId,
		);

		if (!empty($settings['selected'])) {
			$atts['selected'] = sanitize_text_field((string) $settings['selected']);
		}

		if (!empty($settings['lazy']) && $settings['lazy'] === 'yes') {
			$atts['lazy'] = 'true';
		}

		// In the editor, output a host without data-autoload so our JS mounts once.
		if ($this->isEditMode()) {
			$selectedAttr = !empty($atts['selected'])
				? ' selected="' . esc_attr(str_replace(' ', '_', $atts['selected'])) . '"'
				: '';
			$widgetId = $this->get_id();
			echo '<div class="mapsvg-elementor-preview-wrap" style="position:relative;width:100%;min-height:280px;">';
			echo '<div class="mapsvg-elementor-host"'
				. ' id="mapsvg-elementor-host-' . esc_attr($widgetId) . '"'
				. ' data-map-id="' . esc_attr((string) $mapId) . '"'
				. ' data-selected="' . esc_attr(isset($atts['selected']) ? (string) $atts['selected'] : '') . '"'
				. ' data-widget-id="' . esc_attr($widgetId) . '"'
				. $selectedAttr
				. ' style="width:100%;"></div>';
			echo '<div class="mapsvg-elementor-shield" style="position:absolute;inset:0;z-index:5;" aria-hidden="true"></div>';
			echo '</div>';
			return;
		}

		$front = new Front();
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- shortcode renderer returns safe HTML.
		echo $front->renderShortcode($atts);
	}

	/**
	 * @return bool
	 */
	private function isEditMode(): bool
	{
		return class_exists('\Elementor\Plugin')
			&& isset(\Elementor\Plugin::$instance->editor)
			&& \Elementor\Plugin::$instance->editor->is_edit_mode();
	}
}
