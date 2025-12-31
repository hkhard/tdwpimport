<?php

class CSS_Dashboard_Renderer
{
    private $config;
    private $theme_colors;

    public function __construct($config, $theme_colors = array())
    {
        $this->config = $config;
        $this->theme_colors = $theme_colors;
    }

    public function render()
    {
        $is_iframe = isset($_GET['css_dashboard_iframe']);
        ob_start();

        if ($is_iframe) {
            $this->render_iframe_header();
        }
        ?>
        <style>
            :root {
                <?php if (!empty($this->theme_colors['primary'])): ?>
                    --dashboard-primary:
                        <?php echo esc_attr($this->theme_colors['primary']); ?>
                    ;
                <?php endif; ?>
                <?php if (!empty($this->theme_colors['accent'])): ?>
                    --dashboard-accent:
                        <?php echo esc_attr($this->theme_colors['accent']); ?>
                    ;
                <?php endif; ?>
                <?php if (!empty($this->theme_colors['surface'])): ?>
                    --dashboard-bg-surface:
                        <?php echo esc_attr($this->theme_colors['surface']); ?>
                    ;
                    --dashboard-bg-body:
                        <?php echo esc_attr($this->theme_colors['surface']); ?>
                    ;
                    --dashboard-bg-alt: rgba(255, 255, 255, 0.05);
                    --dashboard-border: rgba(255, 255, 255, 0.1);
                <?php endif; ?>
                <?php if (!empty($this->theme_colors['text'])): ?>
                    --dashboard-text-main:
                        <?php echo esc_attr($this->theme_colors['text']); ?>
                    ;
                <?php endif; ?>
            }
        </style>
        <div class="css-dashboard-wrapper <?php echo $is_iframe ? 'css-dashboard--iframe' : ''; ?>">
            <?php if (!$is_iframe): ?>
                <header class="dashboard-header">
                    <h1><?php echo esc_html($this->config['title']); ?></h1>
                </header>
            <?php endif; ?>

            <div class="dashboard-grid">
                <?php foreach ($this->config['sections'] as $section): ?>
                    <?php
                    if ($is_iframe && isset($_GET['section_id']) && $_GET['section_id'] !== $section['id'])
                        continue;

                    $column_class = '';
                    if (!empty($section['columns']) && in_array(intval($section['columns']), array(1, 2, 3))) {
                        $column_class = 'component-grid--' . intval($section['columns']);
                    }
                    ?>
                    <section class="dashboard-section" id="section-<?php echo esc_attr($section['id']); ?>">
                        <?php if (!$is_iframe): ?>
                            <h2><?php echo esc_html($section['title']); ?></h2>
                        <?php endif; ?>
                        <div class="component-grid <?php echo esc_attr($column_class); ?>">
                            <?php foreach ($section['components'] as $component): ?>
                                <?php echo $this->render_component($component); ?>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endforeach; ?>
            </div>

            <?php // CSS-only Drill-down Modals ?>
            <?php foreach ($this->config['sections'] as $section): ?>
                <?php foreach ($section['components'] as $component): ?>
                    <?php if (isset($component['drill_down'])): ?>
                        <div class="drill-down-overlay" id="drill-down-<?php echo esc_attr($component['id']); ?>">
                            <div class="drill-down-modal">
                                <a href="#" class="drill-down-close">&times;</a>
                                <div class="drill-down-content">
                                    <h3><?php echo esc_html($component['drill_down']['title']); ?></h3>
                                    <div class="drill-down-body">
                                        <?php echo $component['drill_down']['content']; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </div>
        <?php
        if ($is_iframe) {
            $this->render_iframe_footer();
        }
        return ob_get_clean();
    }

    private function render_iframe_header()
    {
        $interval = isset($_GET['refresh']) ? intval($_GET['refresh']) : 0;
        ?>
        <!DOCTYPE html>
        <html <?php language_attributes(); ?>>

        <head>
            <meta charset="<?php bloginfo('charset'); ?>">
            <?php if ($interval > 0): ?>
                <meta http-equiv="refresh" content="<?php echo $interval; ?>">
            <?php endif; ?>
            <style>
                body {
                    margin: 0;
                    padding: 0;
                    background:
                        <?php echo !empty($this->theme_colors['surface']) ? esc_attr($this->theme_colors['surface']) : 'transparent'; ?>
                    ;
                    overflow: hidden;
                }

                .css-dashboard-wrapper {
                    padding: 0;
                    min-height: auto;
                    background: transparent !important;
                }
            </style>
            <?php
            wp_print_styles('dashicons');
            wp_print_styles('css-dashboard-core');
            wp_print_styles('css-dashboard-components');
            ?>
        </head>

        <body>
            <?php
    }

    private function render_iframe_footer()
    {
        ?>
        </body>

        </html>
        <?php
    }

    private function render_component($component)
    {
        $output = '';

        // If component has a specific refresh, wrap it in an iframe
        if (isset($component['refresh']) && !isset($_GET['css_dashboard_iframe'])) {
            $iframe_args = array(
                'page' => 'css-dashboard',
                'css_dashboard_iframe' => 1,
                'section_id' => $component['parent_section_id'],
                'component_id' => $component['id'],
                'refresh' => $component['refresh']
            );

            // Pass theme colors to iframe
            if (!empty($this->theme_colors['primary']))
                $iframe_args['theme_primary'] = ltrim($this->theme_colors['primary'], '#');
            if (!empty($this->theme_colors['accent']))
                $iframe_args['theme_accent'] = ltrim($this->theme_colors['accent'], '#');
            if (!empty($this->theme_colors['surface']))
                $iframe_args['theme_surface'] = ltrim($this->theme_colors['surface'], '#');
            if (!empty($this->theme_colors['text']))
                $iframe_args['theme_text'] = ltrim($this->theme_colors['text'], '#');

            $iframe_url = add_query_arg($iframe_args, admin_url('admin.php'));

            return sprintf(
                '<div class="component-iframe-wrapper" style="height: %s;">
					<iframe src="%s" scrolling="no" frameborder="0" style="width: 100%%; height: 100%%; border: none;"></iframe>
				</div>',
                isset($component['height']) ? $component['height'] : '150px',
                esc_url($iframe_url)
            );
        }

        switch ($component['type']) {
            case 'stat':
                $drill_down_link = isset($component['drill_down']) ? '#drill-down-' . $component['id'] : '#';
                $output = sprintf(
                    '<a href="%s" class="stat-card stat-card--%s %s">
						<div class="stat-card__icon"><span class="dashicons %s"></span></div>
						<div class="stat-card__content">
							<div class="stat-card__label">%s</div>
							<div class="stat-card__value">%s</div>
						</div>
					</a>',
                    esc_url($drill_down_link),
                    esc_attr($component['color']),
                    isset($component['drill_down']) ? 'stat-card--drillable' : '',
                    esc_attr($component['icon']),
                    esc_html($component['title']),
                    esc_html($component['value'])
                );
                break;
            case 'table':
                $output = $this->render_table_component($component);
                break;
            case 'custom':
                // Custom HTML component (for filters, etc.)
                if (isset($component['html'])) {
                    $output = $component['html'];
                }
                break;
        }

        return $output;
    }

    private function render_table_component($component)
    {
        $headers = isset($component['headers']) ? $component['headers'] : array();
        $rows = isset($component['rows']) ? $component['rows'] : array();
        $sortable = isset($component['sortable']) ? $component['sortable'] : false;
        $row_clickable = isset($component['row_clickable']) ? $component['row_clickable'] : false;
        $per_page = isset($component['per_page']) ? intval($component['per_page']) : 50;

        ob_start();
        ?>
        <div class="data-table-wrapper" data-table-id="<?php echo esc_attr($component['id']); ?>">
            <?php if ($sortable): ?>
                <div class="table-sort-controls">
                    <?php foreach ($headers as $i => $header): ?>
                        <label>
                            <span><?php echo esc_html($header); ?></span>
                            <input type="radio" name="sort-<?php echo esc_attr($component['id']); ?>"
                                   value="<?php echo $i; ?>" <?php echo $i === 0 ? 'checked' : ''; ?>>
                        </label>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="data-table">
                <div class="table-header">
                    <div class="table-row">
                        <?php foreach ($headers as $header): ?>
                            <div class="table-cell"><?php echo esc_html($header); ?></div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="table-body">
                    <?php
                    $row_index = 0;
                    $page = isset($_GET['table_page_' . $component['id']]) ? intval($_GET['table_page_' . $component['id']]) : 1;
                    $start = ($page - 1) * $per_page;
                    $paged_rows = array_slice($rows, $start, $per_page);
                    ?>
                    <?php foreach ($paged_rows as $row_data): ?>
                        <?php
                        $row_id = $component['id'] . '-row-' . $row_index;
                        $drill_link = $row_clickable && isset($row_data['drill_down']) ? '#drill-down-' . $row_id : '#';
                        ?>
                        <div class="table-row" data-row-index="<?php echo $row_index; ?>">
                            <?php foreach ($row_data['cells'] as $cell): ?>
                                <div class="table-cell">
                                    <?php if ($row_clickable && isset($row_data['drill_down'])): ?>
                                        <a href="<?php echo esc_url($drill_link); ?>" class="table-cell-link">
                                            <?php echo wp_kses_post($cell); ?>
                                        </a>
                                    <?php else: ?>
                                        <?php echo wp_kses_post($cell); ?>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php
                        if ($row_clickable && isset($row_data['drill_down'])):
                            ?>
                            <div class="drill-down-overlay" id="drill-down-<?php echo esc_attr($row_id); ?>">
                                <div class="drill-down-modal drill-down-modal--wide">
                                    <a href="#" class="drill-down-close">&times;</a>
                                    <div class="drill-down-content">
                                        <h3><?php echo esc_html($row_data['drill_down']['title']); ?></h3>
                                        <div class="drill-down-body">
                                            <?php echo $row_data['drill_down']['content']; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif;
                        $row_index++;
                        ?>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php if (count($rows) > $per_page): ?>
                <div class="table-pagination">
                    <?php
                    $total_pages = ceil(count($rows) / $per_page);
                    for ($i = 1; $i <= $total_pages; $i++):
                        $url = add_query_arg('table_page_' . $component['id'], $i);
                        ?>
                        <a href="<?php echo esc_url($url); ?>"
                           class="pagination-link <?php echo $i === $page ? 'pagination-link--active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}
