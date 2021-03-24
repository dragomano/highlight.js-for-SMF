<?php

/**
 * Class-Highlighting.php
 *
 * @package Code Highlighting
 * @link https://custom.simplemachines.org/mods/index.php?mod=2925
 * @author Bugo https://dragomano.ru/mods/code-highlighting
 * @copyright 2010-2021 Bugo
 * @license https://opensource.org/licenses/BSD-3-Clause BSD
 *
 * @version 0.9
 */

if (!defined('SMF'))
	die('Hacking attempt...');

define('CH_VER', '10');

class Highlighting
{
	/**
	 * Подключаем используемые хуки
	 *
	 * @return void
	 */
	public static function hooks()
	{
		add_integration_function('integrate_load_theme', __CLASS__ . '::loadTheme', false, __FILE__);
		add_integration_function('integrate_buffer', __CLASS__ . '::buffer', false, __FILE__);
		add_integration_function('integrate_bbc_codes', __CLASS__ . '::bbcCodes', false, __FILE__);
		add_integration_function('integrate_prepare_display_context', __CLASS__ . '::prepareDisplayContext', false, __FILE__);
		add_integration_function('integrate_admin_areas', __CLASS__ . '::adminAreas', false, __FILE__);
		add_integration_function('integrate_admin_search', __CLASS__ . '::adminSearch', false, __FILE__);
		add_integration_function('integrate_modify_modifications', __CLASS__ . '::modifyModifications', false, __FILE__);
		add_integration_function('integrate_credits', __CLASS__ . '::credits', false, __FILE__);
	}

	/**
	 * Подключаем скрипты и стили
	 *
	 * @return void
	 */
	public static function loadTheme()
	{
		global $modSettings, $context, $settings, $txt;

		loadLanguage('Highlighting/');

		if (empty($modSettings['ch_enable']) || empty($modSettings['ch_style']))
			return;

		if (in_array($context['current_action'], array('helpadmin', 'printpage')))
			return;

		// Paths
		if (!empty($modSettings['ch_cdn_use'])) {
			$context['ch_jss_path'] = 'https://cdn.jsdelivr.net/gh/highlightjs/cdn-release@' . CH_VER . '/build/highlight.min.js';
			$context['ch_dln_path'] = 'https://cdn.jsdelivr.net/npm/highlightjs-line-numbers.js@2/dist/highlightjs-line-numbers.min.js';
			$context['ch_clb_path'] = 'https://cdn.jsdelivr.net/npm/clipboard@2/dist/clipboard.min.js';
			$context['ch_css_path'] = 'https://cdn.jsdelivr.net/gh/highlightjs/cdn-release@' . CH_VER . '/build/styles/' . $modSettings['ch_style'] . '.min.css';
		} else {
			$context['ch_jss_path'] = $settings['default_theme_url'] . '/scripts/highlight.pack.js';
			$context['ch_dln_path'] = $settings['default_theme_url'] . '/scripts/highlightjs-line-numbers.min.js';
			$context['ch_clb_path'] = $settings['default_theme_url'] . '/scripts/clipboard.min.js';
			$context['ch_css_path'] = $settings['default_theme_url'] . '/css/highlight/' . $modSettings['ch_style'] . '.css';
		}

		// Highlight
		$context['html_headers'] .= '
	<link rel="stylesheet" href="' . $context['ch_css_path'] . '">
	<link rel="stylesheet" href="' . $settings['default_theme_url'] . '/css/highlight.css">';

		$context['insert_after_template'] .= '
		<script src="' . $context['ch_jss_path'] . '"></script>' . (!empty($modSettings['ch_line_numbers']) ? '
		<script src="' . $context['ch_dln_path'] . '"></script>' : '') . '
		<script src="' . $context['ch_clb_path'] . '"></script>
		<script>
			hljs.highlightAll();' . (!empty($modSettings['ch_line_numbers']) ? '
			hljs.initLineNumbersOnLoad();' : '') . '
			window.addEventListener("load", function() {
				let pre = document.getElementsByTagName("code");
				for (let i = 0; i < pre.length; i++) {
					let divClipboard = document.createElement("div");
					divClipboard.className = "bd-clipboard";
					let button = document.createElement("span");
					button.className = "btn-clipboard";
					button.setAttribute("title", "' . $txt['ch_copy'] . '");
					divClipboard.appendChild(button);
					pre[i].parentElement.insertBefore(divClipboard,pre[i]);
				}
				let btnClipboard = new ClipboardJS(".btn-clipboard", {
					target: function(trigger) {
						trigger.clearSelection;
						return trigger.parentElement.nextElementSibling;
					}
				});
				btnClipboard.on("success", function(e) {
					e.clearSelection();
				});
			});
		</script>';
	}

	/**
	 * Подгружаем стили для оформления операций замен при установке/удалении модификаций
	 *
	 * @param string $buffer
	 * @return string
	 */
	public static function buffer($buffer)
	{
		global $context, $modSettings, $txt, $settings;

		if (empty($context['ch_css_path']))
			return $buffer;

		$search = $replace = '';

		if (!empty($modSettings['ch_enable']) && isset($txt['operation_title'])) {
			$css = "\n\t\t" . '<link rel="stylesheet" href="' . $context['ch_css_path'] . '">
		<link rel="stylesheet" href="' . $settings['default_theme_url'] . '/css/highlight.css">';
			$search = '<title>' . $txt['operation_title'] . '</title>';
			$replace = $search . $css;
		}

		return (isset($_REQUEST['xml']) ? $buffer : str_replace($search, $replace, $buffer));
	}

	/**
	 * Изменяем оформление ББ-тега [code]
	 *
	 * @param array $codes
	 * @return void
	 */
	public static function bbcCodes(&$codes)
	{
		global $modSettings, $txt;

		if (SMF === 'BACKGROUND' || empty($modSettings['ch_enable']))
			return;

		foreach ($codes as $tag => $dump) {
			if ($dump['tag'] == 'code')
				unset($codes[$tag]);
		}

		$codes[] = 	array(
			'tag' => 'code',
			'type' => 'unparsed_content',
			'content' => '<figure class="block_code"' . (!empty($modSettings['ch_fontsize']) ? ' style="font-size: ' . $modSettings['ch_fontsize'] . '"' : '') . '><pre><code>$1</code></pre></figure>',
			'validate' => function(&$tag, &$data, $disabled) {
				if (!isset($disabled['code'])) {
					$data = rtrim($data, "\n\r");
				}
			},
			'block_level' => true,
			'disabled_content' => '<pre>$1</pre>'
		);

		$codes[] = array(
			'tag' => 'code',
			'type' => 'unparsed_equals_content',
			'content' => '<figure class="block_code"' . (!empty($modSettings['ch_fontsize']) ? ' style="font-size: ' . $modSettings['ch_fontsize'] . '"' : '') . '><figcaption class="codeheader">' . $txt['code'] . ': $2</figcaption><pre><code class="language-$2">$1</code></pre></figure>',
			'validate' => function(&$tag, &$data, $disabled) {
				if (!isset($disabled['code'])) {
					$data[0] = rtrim($data[0], "\n\r");
					$data[1] = strtolower($data[1]);
				}
			},
			'block_level' => true,
			'disabled_content' => '<pre>$1</pre>'
		);
	}

	/**
	 * Творим колдовство в сообщениях форума
	 * Заменяем <br> на нормальный перенос строки, ради отображения нумерации строк
	 *
	 * @param array $output
	 * @return void
	 */
	public static function prepareDisplayContext(&$output)
	{
		global $modSettings;

		if (empty($modSettings['ch_enable']) || empty($modSettings['ch_line_numbers']))
			return;

		if (strpos($output['body'], '<pre>') !== false)
			$output['body'] = strtr($output['body'], array('<br>' => "\n"));
	}

	/**
	 * Создаем секцию с настройками мода в админке
	 *
	 * @param array $admin_areas
	 * @return void
	 */
	public static function adminAreas(&$admin_areas)
	{
		global $txt;

		$admin_areas['config']['areas']['modsettings']['subsections']['highlight'] = array($txt['ch_title']);
	}

	/**
	 * Легкий доступ к настройкам мода через быстрый поиск в админке
	 *
	 * @param array $language_files
	 * @param array $include_files
	 * @param array $settings_search
	 * @return void
	 */
	public static function adminSearch(&$language_files, &$include_files, &$settings_search)
	{
		$settings_search[] = array(__CLASS__ . '::settings', 'area=modsettings;sa=highlight');
	}

	/**
	 * Подключаем вкладку с настройками мода
	 *
	 * @param array $subActions
	 * @return void
	 */
	public static function modifyModifications(&$subActions)
	{
		$subActions['highlight'] = array(__CLASS__, 'settings');
	}

	/**
	 * Определяем настройки мода
	 *
	 * @return array|void
	 */
	public static function settings($return_config = false)
	{
		global $context, $txt, $scripturl, $modSettings, $settings;

		$context['page_title']     = $txt['ch_title'];
		$context['settings_title'] = $txt['ch_settings'];
		$context['post_url']       = $scripturl . '?action=admin;area=modsettings;save;sa=highlight';
		$context[$context['admin_menu_name']]['tab_data']['tabs']['highlight'] = array('description' => $txt['ch_desc']);

		$addSettings = [];
		if (!isset($modSettings['ch_enable']))
			$addSettings['ch_enable'] = 1;
		if (!isset($modSettings['ch_cdn_use']))
			$addSettings['ch_cdn_use'] = 1;
		if (!isset($modSettings['ch_style']))
			$addSettings['ch_style'] = 'default';
		if (!isset($modSettings['ch_fontsize']))
			$addSettings['ch_fontsize'] = 'medium';
		if (!empty($addSettings))
			updateSettings($addSettings);

		$style_list = glob($settings['default_theme_dir'] . "/css/highlight/*.css");
		$style_set  = array();
		foreach ($style_list as $file) {
			$search           = array($settings['default_theme_dir'] . "/css/highlight/", '.css');
			$replace          = array('', '');
			$file             = str_replace($search, $replace, $file);
			$style_set[$file] = ucwords(str_replace('-', ' ', $file));
		}

		$config_vars = array(
			array('check', 'ch_enable'),
			array('check', 'ch_cdn_use'),
			array('select', 'ch_style', $style_set),
			array(
				'select',
				'ch_fontsize',
				array(
					'x-small' => 'x-small',
					'small'   => 'small',
					'medium'  => 'medium',
					'large'   => 'large',
					'x-large' => 'x-large'
				)
			),
			array('check', 'ch_line_numbers')
		);

		if (!empty($modSettings['ch_enable']) && function_exists('file_get_contents')) {
			$config_vars[] = array('callback', 'ch_example');
			$config_vars[] = '<br>';
		}

		if ($return_config)
			return $config_vars;

		// Saving?
		if (isset($_GET['save'])) {
			checkSession();

			$save_vars = $config_vars;
			saveDBSettings($save_vars);

			redirectexit('action=admin;area=modsettings;sa=highlight');
		}

		prepareDBSettingContext($config_vars);
	}

	/**
	 * Подключаем копирайты мода
	 *
	 * @return void
	 */
	public static function credits()
	{
		global $modSettings, $context;

		if (empty($modSettings['ch_enable']))
			return;

		$link = $context['user']['language'] == 'russian' ? 'https://dragomano.ru/mods/code-highlighting' : 'https://custom.simplemachines.org/mods/index.php?mod=2925';

		$context['credits_modifications'][] = '<a href="' . $link . '" target="_blank" rel="noopener">Code Highlighting</a> &copy; 2010&ndash;2021, Bugo';
	}
}

/**
 * Область предпросмотра
 *
 * @return void
 */
function template_callback_ch_example()
{
	global $settings, $modSettings, $txt;

	if (file_exists($settings['default_theme_dir'] . '/css/admin.css'))	{
		$file = file_get_contents($settings['default_theme_dir'] . '/css/admin.css');
		$file = parse_bbc('[code]' . $file . '[/code]');

		if (!empty($modSettings['ch_line_numbers']))
			$file = strtr($file, array('<br>' => "\n"));

		echo '</dl><strong>' . $txt['ch_example'] . '</strong>' . $file . '<dl><dt></dt><dd></dd>';
	}
}
