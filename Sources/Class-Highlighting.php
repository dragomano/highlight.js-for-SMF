<?php

/**
 * Class-Highlighting.php
 *
 * @package Code Highlighting
 * @link https://custom.simplemachines.org/mods/index.php?mod=2925
 * @author Bugo https://dragomano.ru/mods/code-highlighting
 * @copyright 2010-2022 Bugo
 * @license https://opensource.org/licenses/BSD-3-Clause BSD
 *
 * @version 1.1
 */

if (!defined('SMF'))
	die('Hacking attempt...');

final class Highlighting
{
	private const FONTSIZE_SET = [
		'x-small' => 'x-small',
		'small'   => 'small',
		'medium'  => 'medium',
		'large'   => 'large',
		'x-large' => 'x-large'
	];

	/**
	 * Подключаем используемые хуки
	 *
	 * @return void
	 */
	public function hooks()
	{
		add_integration_function('integrate_pre_css_output', __CLASS__ . '::preCssOutput#', false, __FILE__);
		add_integration_function('integrate_load_theme', __CLASS__ . '::loadTheme#', false, __FILE__);
		add_integration_function('integrate_bbc_codes', __CLASS__ . '::bbcCodes#', false, __FILE__);
		add_integration_function('integrate_post_parsebbc', __CLASS__ . '::postParseBbc#', false, __FILE__);
		add_integration_function('integrate_admin_areas', __CLASS__ . '::adminAreas#', false, __FILE__);
		add_integration_function('integrate_admin_search', __CLASS__ . '::adminSearch#', false, __FILE__);
		add_integration_function('integrate_modify_modifications', __CLASS__ . '::modifyModifications#', false, __FILE__);
		add_integration_function('integrate_credits', __CLASS__ . '::credits#', false, __FILE__);
	}

	/**
	 * @hook integrate_pre_css_output
	 */
	public function preCssOutput()
	{
		global $modSettings;

		if (! $this->shouldItWork() || empty($modSettings['ch_cdn_use']))
			return;

		echo "\n\t" . '<link rel="preload" href="https://cdn.jsdelivr.net/gh/highlightjs/cdn-release@latest/build/styles/' . ($modSettings['ch_style'] ?? 'default') . '.min.css" as="style" onload="this.onload=null;this.rel=\'stylesheet\'">';
	}

	/**
	 * Подключаем скрипты и стили
	 *
	 * @return void
	 */
	public function loadTheme()
	{
		global $modSettings, $context, $settings, $txt;

		loadLanguage('Highlighting/');

		if (! $this->shouldItWork())
			return;

		// Paths
		if (!empty($modSettings['ch_cdn_use'])) {
			$context['ch_jss_path'] = 'https://cdn.jsdelivr.net/gh/highlightjs/cdn-release@latest/build/highlight.min.js';
			$context['ch_dln_path'] = 'https://cdn.jsdelivr.net/npm/highlightjs-line-numbers.js@2/dist/highlightjs-line-numbers.min.js';
			$context['ch_clb_path'] = 'https://cdn.jsdelivr.net/npm/clipboard@2/dist/clipboard.min.js';
		} else {
			$context['ch_jss_path'] = $settings['default_theme_url'] . '/scripts/highlight.min.js';
			$context['ch_dln_path'] = $settings['default_theme_url'] . '/scripts/highlightjs-line-numbers.min.js';
			$context['ch_clb_path'] = $settings['default_theme_url'] . '/scripts/clipboard.min.js';

			loadCSSFile('highlight/' . ($modSettings['ch_style'] ?? 'default') . '.min.css');
		}

		// Load styles and scripts
		loadCSSFile('highlight.css');

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
	 * Изменяем оформление ББ-тега [code]
	 *
	 * @param array $codes
	 * @return void
	 */
	public function bbcCodes(&$codes)
	{
		global $modSettings, $context, $txt;

		if (! $this->shouldItWork())
			return;

		if (!empty($modSettings['ch_fontsize'])) {
			$fontSize = ' style="font-size: ' . $modSettings['ch_fontsize'] . '"';
		}

		$codes = array_filter($codes, function ($code) {
			return $code['tag'] !== 'code';
		});

		$codes[] = 	array(
			'tag' => 'code',
			'type' => 'unparsed_content',
			'content' => '<figure class="block_code"' . ($fontSize ?? '') . '><pre><code>$1</code></pre></figure>',
			'block_level' => true
		);

		$codes[] = array(
			'tag' => 'code',
			'type' => 'unparsed_equals_content',
			'content' => '<figure class="block_code"' . ($fontSize ?? '') . '><figcaption class="codeheader">' . $txt['code'] . ': $2</figcaption><pre><code class="language-$2">$1</code></pre></figure>',
			'block_level' => true
		);
	}

	/**
	 * @param string $message
	 * @return void
	 */
	public function postParseBbc(&$message)
	{
		global $modSettings;

		if (! $this->shouldItWork() || strpos($message, '<pre') === false)
			return;

		$message = preg_replace_callback('~<pre(.*?)>(.*?)<\/pre>~si', function ($matches) {
			return str_replace('<br>', "\n", $matches[0]);
		}, $message);
	}

	/**
	 * Создаем секцию с настройками мода в админке
	 *
	 * @param array $admin_areas
	 * @return void
	 */
	public function adminAreas(&$admin_areas)
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
	public function adminSearch(&$language_files, &$include_files, &$settings_search)
	{
		$settings_search[] = array(array($this, 'settings'), 'area=modsettings;sa=highlight');
	}

	/**
	 * Подключаем вкладку с настройками мода
	 *
	 * @param array $subActions
	 * @return void
	 */
	public function modifyModifications(&$subActions)
	{
		$subActions['highlight'] = array($this, 'settings');
	}

	/**
	 * Определяем настройки мода
	 *
	 * @return array|void
	 */
	public function settings($return_config = false)
	{
		global $context, $txt, $scripturl, $modSettings, $settings;

		$context['page_title']     = $txt['ch_title'];
		$context['settings_title'] = $txt['ch_settings'];
		$context['post_url']       = $scripturl . '?action=admin;area=modsettings;save;sa=highlight';
		$context[$context['admin_menu_name']]['tab_data']['tabs']['highlight'] = array('description' => $txt['ch_desc']);

		$addSettings = [];
		if (!isset($modSettings['ch_cdn_use']))
			$addSettings['ch_cdn_use'] = 1;
		if (!isset($modSettings['ch_style']))
			$addSettings['ch_style'] = 'default';
		if (!isset($modSettings['ch_fontsize']))
			$addSettings['ch_fontsize'] = 'medium';
		if (!empty($addSettings))
			updateSettings($addSettings);

		$style_list = array_merge(
			glob($settings['default_theme_dir'] . "/css/highlight/*.css"),
			glob($settings['default_theme_dir'] . "/css/highlight/base16/*.css")
		);
		$style_set  = array();
		foreach ($style_list as $file) {
			$search           = array($settings['default_theme_dir'] . "/css/highlight/", '.css', '.min');
			$replace          = array('', '', '');
			$file             = str_replace($search, $replace, $file);
			$style_set[$file] = ucwords(strtr($file, array('-' => ' ', '/' => ' - ')));
		}

		$config_vars = array(
			array('check', 'ch_enable'),
			array('check', 'ch_cdn_use'),
			array('select', 'ch_style', $style_set),
			array('select', 'ch_fontsize', self::FONTSIZE_SET),
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
	public function credits()
	{
		global $modSettings, $context;

		if (empty($modSettings['ch_enable']))
			return;

		$link = $context['user']['language'] == 'russian' ? 'https://dragomano.ru/mods/code-highlighting' : 'https://custom.simplemachines.org/mods/index.php?mod=2925';

		$context['credits_modifications'][] = '<a href="' . $link . '" target="_blank" rel="noopener">Code Highlighting</a> &copy; 2010&ndash;2021, Bugo';
	}

	/**
	 * @return bool
	 */
	private function shouldItWork()
	{
		global $modSettings, $context;

		if (SMF === 'BACKGROUND' || SMF === 'SSI' || empty($modSettings['enableBBC']) || empty($modSettings['ch_enable']))
			return false;

		if (in_array($context['current_action'], array('helpadmin', 'printpage')) || $context['current_subaction'] === 'showoperations')
			return false;

		return empty($modSettings['disabledBBC']) || !in_array('code', explode(',', $modSettings['disabledBBC']));
	}
}

/**
 * Область предпросмотра
 *
 * @return void
 */
function template_callback_ch_example()
{
	global $settings, $txt;

	if (file_exists($settings['default_theme_dir'] . '/css/admin.css'))	{
		$file = file_get_contents($settings['default_theme_dir'] . '/css/admin.css');
		$file = parse_bbc('[code]' . $file . '[/code]');

		echo '</dl><strong>' . $txt['ch_example'] . '</strong>' . $file . '<dl><dt></dt><dd></dd>';
	}
}
