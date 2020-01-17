<?php

/**
 * Class-Highlighting.php
 *
 * @package Code Highlighting
 * @link https://custom.simplemachines.org/mods/index.php?mod=2925
 * @author Bugo https://dragomano.ru/mods/code-highlighting
 * @copyright 2010-2020 Bugo
 * @license https://opensource.org/licenses/BSD-3-Clause BSD
 *
 * @version 0.4
 */

if (!defined('SMF'))
	die('Hacking attempt...');

define('CH_VER', '9.15.9');

class Code_Highlighting
{
	/**
	 * Подключаем используемые хуки
	 *
	 * @return void
	 */
	public static function hooks()
	{
		add_integration_function('integrate_load_theme', __CLASS__ . '::loadTheme', false, __FILE__);
		add_integration_function('integrate_admin_areas', __CLASS__ . '::adminAreas', false, __FILE__);
		add_integration_function('integrate_admin_search', __CLASS__ . '::adminSearch', false, __FILE__);
		add_integration_function('integrate_modify_modifications', __CLASS__ . '::modifyModifications', false, __FILE__);
		add_integration_function('integrate_bbc_codes', __CLASS__ . '::bbcCodes', false, __FILE__);
		add_integration_function('integrate_buffer', __CLASS__ . '::buffer', false, __FILE__);
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

		$addSettings = [];
		if (!isset($modSettings['ch_enable']))
			$addSettings['ch_enable'] = 1;
		if (!isset($modSettings['ch_cdn_use']))
			$addSettings['ch_cdn_use'] = 1;
		if (!isset($modSettings['ch_style']))
			$addSettings['ch_style'] = 'default';
		if (!isset($modSettings['ch_tab']))
			$addSettings['ch_tab'] = 4;
		if (!isset($modSettings['ch_fontsize']))
			$addSettings['ch_fontsize'] = 'medium';
		if (!empty($addSettings))
			updateSettings($addSettings);

		// Paths
		$context['ch_jss_path'] = !empty($modSettings['ch_cdn_use']) ? '//cdnjs.cloudflare.com/ajax/libs/highlight.js/' . CH_VER . '/highlight.min.js' : $settings['default_theme_url'] . '/scripts/highlight.pack.js';
		$context['ch_clb_path'] = !empty($modSettings['ch_cdn_use']) ? '//cdnjs.cloudflare.com/ajax/libs/clipboard.js/1.7.1/clipboard.min.js' : $settings['default_theme_url'] . '/scripts/clipboard.min.js';
		$context['ch_css_path'] = !empty($modSettings['ch_cdn_use']) ? '//cdnjs.cloudflare.com/ajax/libs/highlight.js/' . CH_VER . '/styles/' . $modSettings['ch_style'] . '.min.css' : $settings['default_theme_url'] . '/css/highlight/' . $modSettings['ch_style'] . '.css';

		if (isset($_REQUEST['sa']) && $_REQUEST['sa'] == 'showoperations')
			return;

		// Highlight
		if (!empty($modSettings['ch_enable'])) {
			$i = 0;
			$tab = '';
			if (!empty($modSettings['ch_tab'])) {
				while ($i < $modSettings['ch_tab']) {
					$tab .= ' ';
					$i++;
				}
			}

			$context['html_headers'] .= '
	<link rel="stylesheet" href="' . $context['ch_css_path'] . '">
	<link rel="stylesheet" href="' . $settings['default_theme_url'] . '/css/highlight.css">';

			if (!in_array($context['current_action'], array('helpadmin', 'printpage')))
				$context['insert_after_template'] .= '
		<script src="' . $context['ch_jss_path'] . '"></script>
		<script src="' . $context['ch_clb_path'] . '"></script>
		<script>
			hljs.tabReplace = "' . $tab . '";
			hljs.initHighlightingOnLoad();
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
				let btnClipboard = new Clipboard(".btn-clipboard", {
					target: function(trigger) {
						console.log(trigger.parentElement.nextElementSibling);
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

		// Preview
		if (!empty($modSettings['ch_enable']) && in_array($context['current_action'], array('post', 'post2')))
			$context['insert_after_template'] .= '
			<script>
				let previewPost = function() {
					if (document.forms.postmodify.elements["message"].value.lastIndexOf(\'[/code]\') != -1)
						return submitThisOnce(document.forms.postmodify);
				}
			</script>';
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
	 * @param actions $subActions
	 * @return void
	 */
	public static function modifyModifications(&$subActions)
	{
		$subActions['highlight'] = array('Code_Highlighting', 'settings');
	}

	/**
	 * Определяем настройки мода
	 *
	 * @return array|void
	 */
	public static function settings($return_config = false)
	{
		global $context, $txt, $scripturl, $settings, $modSettings;

		$context['page_title'] = $txt['ch_title'];
		$context['settings_title'] = $txt['ch_settings'];
		$context['post_url'] = $scripturl . '?action=admin;area=modsettings;save;sa=highlight';
		$context[$context['admin_menu_name']]['tab_data']['tabs']['highlight'] = array('description' => $txt['ch_desc']);

		$style_list = glob($settings['default_theme_dir'] . "/css/highlight/*.css");
		$style_set  = array();
		foreach ($style_list as $file) {
			$search = array($settings['default_theme_dir'] . "/css/highlight/", '.css');
			$replace = array('', '');
			$file = str_replace($search, $replace, $file);
			$style_set[$file] = ucwords(str_replace('-', ' ', $file));
		}

		$config_vars = array(
			array('check', 'ch_enable'),
			array('check', 'ch_cdn_use'),
			array('select', 'ch_style', $style_set),
			array('int', 'ch_tab'),
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
			)
		);

		if (!empty($modSettings['ch_enable']) && function_exists('file_get_contents'))
			$config_vars[] = array('callback', 'ch_example');

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
	 * Изменяем оформление ББ-тега [code]
	 *
	 * @param array $codes
	 * @return void
	 */
	public static function bbcCodes(&$codes)
	{
		global $modSettings, $txt, $context;

		if (!empty($modSettings['ch_enable'])) {
			foreach ($codes as $tag => $dump) {
				if ($dump['tag'] == 'code')
					unset($codes[$tag]);
			}

			$codes[] = 	array(
				'tag' => 'code',
				'type' => 'unparsed_content',
				'content' => '<div class="block_code" style="font-size: ' . $modSettings['ch_fontsize'] . '"><pre><code>$1</code></pre></div>',
				'validate' => function(&$tag, &$data, $disabled)
				{
					if (!isset($disabled['code']))
						$data = rtrim($data, "\n\r");
				},
				'block_level' => true,
				'disabled_content' => '<pre>$1</pre>'
			);
			$codes[] = array(
				'tag' => 'code',
				'type' => 'unparsed_equals_content',
				'validate' => function(&$tag, &$data, $disabled) use ($txt, $modSettings)
				{
					$tag['content'] = '<div class="codeheader">' . $txt['code'] . ': ' . $data[1] . '</div><div class="block_code" style="font-size: ' . $modSettings['ch_fontsize'] . '"><pre><code class="' . $data[1] . '">' . rtrim($data[0], "\n\r") . '</code></pre></div>';
				},
				'block_level' => true,
				'disabled_content' => '<pre>$1</pre>'
			);
		}

		// Copyright Info
		if (isset($context['current_action']) && $context['current_action'] == 'credits')
			$context['copyrights']['mods'][] = '<a href="https://dragomano.ru/mods/code-highlighting" target="_blank" rel="noopener">Code Highlighting</a> &copy; 2010&ndash;2020, Bugo';
	}

	/**
	 * Производим замены в буфере страницы
	 *
	 * @param array $buffer
	 * @return void
	 */
	public static function buffer($buffer)
	{
		global $modSettings, $txt, $context, $settings;

		$search = $replace = '';

		if (!empty($modSettings['ch_enable']) && isset($txt['operation_title'])) {
			$css = "\n\t\t" . '<link rel="stylesheet" href="' . $context['ch_css_path'] . '">
			<link rel="stylesheet" href="' . $settings['default_theme_url'] . '/css/highlight.css">';
			$js = "\n\t\t" . '<script src="' . $context['ch_jss_path'] . '"></script>
			<script>hljs.initHighlightingOnLoad();</script>';
			$search = '<title>' . $txt['operation_title'] . '</title>';
			$replace = $search . $css . $js;
		}

		return (isset($_REQUEST['xml']) ? $buffer : str_replace($search, $replace, $buffer));
	}
}

// Область предпросмотра
function template_callback_ch_example()
{
	global $settings, $txt;

	if (file_exists($settings['default_theme_dir'] . '/css/admin.css'))	{
		$file = file_get_contents($settings['default_theme_dir'] . '/css/admin.css');
		$file = parse_bbc('[code]' . $file . '[/code]');
		echo '</dl><strong>' . $txt['ch_example'] . '</strong>' . $file . '<dl><dt></dt><dd></dd>';
	}
}
