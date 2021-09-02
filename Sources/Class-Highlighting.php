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
 * @version 1.5
 */

if (!defined('SMF'))
	die('Hacking attempt...');

class Highlighting
{
	private const FONTSIZE_SET = [
		'x-small' => 'x-small',
		'small'   => 'small',
		'medium'  => 'medium',
		'large'   => 'large',
		'x-large' => 'x-large'
	];

	/**
	 * @return void
	 */
	public static function hooks()
	{
		add_integration_function('integrate_load_theme', __CLASS__ . '::loadTheme', false);
		add_integration_function('integrate_admin_areas', __CLASS__ . '::adminAreas', false);
		add_integration_function('integrate_modify_modifications', __CLASS__ . '::modifyModifications', false);
		add_integration_function('integrate_bbc_codes', __CLASS__ . '::bbcCodes', false);
		add_integration_function('integrate_ch_post_parsebbc', __CLASS__ . '::postParseBbc', false);
		add_integration_function('integrate_menu_buttons', __CLASS__ . '::addCredits', false);
	}

	/**
	 * @return void
	 */
	public static function loadTheme()
	{
		global $modSettings, $context, $settings, $txt;

		loadLanguage('Highlighting/');

		if (defined('WIRELESS') && WIRELESS)
			return;

		if (empty($modSettings['ch_enable']) || $context['current_subaction'] == 'showoperations')
			return;

		if (in_array($context['current_action'], array('helpadmin', 'printpage')))
			return;

		// Paths
		if (!empty($modSettings['ch_cdn_use'])) {
			$context['ch_jss_path'] = 'https://cdn.jsdelivr.net/gh/highlightjs/cdn-release@latest/build/highlight.min.js';
			$context['ch_dln_path'] = 'https://cdn.jsdelivr.net/npm/highlightjs-line-numbers.js@2/dist/highlightjs-line-numbers.min.js';
			$context['ch_clb_path'] = 'https://cdn.jsdelivr.net/npm/clipboard@2/dist/clipboard.min.js';
			$context['ch_css_path'] = 'https://cdn.jsdelivr.net/gh/highlightjs/cdn-release@latest/build/styles/' . ($modSettings['ch_style'] ?? 'default') . '.min.css';
		} else {
			$context['ch_jss_path'] = $settings['default_theme_url'] . '/scripts/highlight.min.js';
			$context['ch_dln_path'] = $settings['default_theme_url'] . '/scripts/highlightjs-line-numbers.min.js';
			$context['ch_clb_path'] = $settings['default_theme_url'] . '/scripts/clipboard.min.js';
			$context['ch_css_path'] = $settings['default_theme_url'] . '/css/highlight/' . ($modSettings['ch_style'] ?? 'default') . '.min.css';
		}

		// Highlight
		if (!empty($modSettings['ch_enable'])) {
			$context['html_headers'] .= '
	<link rel="stylesheet" type="text/css" href="' . $context['ch_css_path'] . '" />
	<link rel="stylesheet" type="text/css" href="' . $settings['default_theme_url'] . '/css/highlight.css" />';

			if (!in_array($context['current_action'], array('helpadmin', 'printpage')))
				$context['insert_after_template'] .= '
		<script type="text/javascript" src="' . $context['ch_jss_path'] . '"></script>' . (!empty($modSettings['ch_line_numbers']) ? '
		<script type="text/javascript" src="' . $context['ch_dln_path'] . '"></script>' : '') . '
		<script type="text/javascript" src="' . $context['ch_clb_path'] . '"></script>
		<script type="text/javascript">
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

		// Preview
		if (!empty($modSettings['ch_enable']) && in_array($context['current_action'], array('post', 'post2')))
			$context['insert_after_template'] .= '
			<script type="text/javascript">
				var previewPost = function() {
					if (document.forms.postmodify.elements["message"].value.lastIndexOf(\'[/code]\') != -1) {
						return submitThisOnce(document.forms.postmodify);
					}
				}
			</script>';
	}

	/**
	 * @param array $codes
	 * @return void
	 */
	public static function bbcCodes(&$codes)
	{
		global $modSettings, $context, $txt;

		if ((defined('WIRELESS') && WIRELESS) || empty($modSettings['ch_enable']) || $context['current_subaction'] == 'showoperations')
			return;

		$disabled = [];
		if (!empty($modSettings['disabledBBC'])) {
			foreach (explode(',', $modSettings['disabledBBC']) as $tag)
				$disabled[$tag] = true;
		}

		if (isset($disabled['code']))
			return;

		foreach ($codes as $tag => $dump) {
			if ($dump['tag'] == 'code')
				unset($codes[$tag]);
		}

		if (!empty($modSettings['ch_fontsize'])) {
			$fontSize = ' style="font-size: ' . $modSettings['ch_fontsize'] . '"';
		}

		$codes[] = 	array(
			'tag' => 'code',
			'type' => 'unparsed_content',
			'content' => '<div class="codeheader">' . $txt['code'] . '</div><div class="block_code"' . ($fontSize ?? '') . '><pre><code>$1</code></pre></div>',
			'block_level' => true
		);
		$codes[] = array(
			'tag' => 'code',
			'type' => 'unparsed_equals_content',
			'content' => '<div class="codeheader">' . $txt['code'] . ': $2</div><div class="block_code"' . ($fontSize ?? '') . '><pre><code class="language-$2">$1</code></pre></div>',
			'block_level' => true
		);
	}

	/**
	 * @param string $message
	 * @return void
	 */
	public static function postParseBbc(&$message)
	{
		global $modSettings;

		if (empty($modSettings['ch_enable']) || strpos($message, '<pre') === false)
			return;

		$message = preg_replace_callback('~<pre(.*?)>(.*?)<\/pre>~si', function ($matches) {
			return str_replace('<br />', "\n", $matches[0]);
		}, $message);
	}

	/**
	 * @param array $admin_areas
	 * @return void
	 */
	public static function adminAreas(&$admin_areas)
	{
		global $txt;

		$admin_areas['config']['areas']['modsettings']['subsections']['highlight'] = array($txt['ch_title']);
	}

	/**
	 * @param array $subActions
	 * @return void
	 */
	public static function modifyModifications(&$subActions)
	{
		$subActions['highlight'] = array(__CLASS__, 'settings');
	}

	/**
	 * @return void
	 */
	public static function settings()
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
			$search  = array($settings['default_theme_dir'] . "/css/highlight/", '.css', '.min');
			$replace = array('', '', '');
			$file    = str_replace($search, $replace, $file);
			$style_set[$file] = ucwords(strtr($file, array('-' => ' ', '/' => ' - ')));
		}

		$config_vars = array(
			array('check', 'ch_enable'),
			array('check', 'ch_cdn_use'),
			array('select', 'ch_style', $style_set),
			array('select', 'ch_fontsize', self::FONTSIZE_SET),
			array('check', 'ch_line_numbers')
		);

		if (!empty($modSettings['ch_enable']) && function_exists('file_get_contents'))
			$config_vars[] = array('callback', 'ch_example');

		// Saving?
		if (isset($_GET['save'])) {
			checkSession();
			saveDBSettings($config_vars);
			redirectexit('action=admin;area=modsettings;sa=highlight');
		}

		prepareDBSettingContext($config_vars);
	}

	/**
	 * @return void
	 */
	public static function addCredits()
	{
		global $modSettings, $context;

		if (empty($modSettings['ch_enable']))
			return;

		$link = in_array($context['user']['language'], array('russian','russian-utf8'))
			? 'https://dragomano.ru/mods/code-highlighting'
			: 'https://custom.simplemachines.org/mods/index.php?mod=2925';

		if ($context['current_action'] == 'credits')
			$context['copyrights']['mods'][] = '<a href="' . $link . '" target="_blank" rel="noopener">Code Highlighting</a> &copy; 2010&ndash;2021, Bugo';
	}
}

// Example
function template_callback_ch_example()
{
	global $settings, $txt, $modSettings;

	if (file_exists($settings['default_theme_dir'] . '/css/admin.css'))	{
		$file = file_get_contents($settings['default_theme_dir'] . '/css/admin.css');

		echo '</dl><strong>' . $txt['ch_example'] . '</strong><div class="block_code" style="font-size: ' . $modSettings['ch_fontsize'] . '"><pre><code>' . $file . '</code></pre></div><dl><dt></dt><dd></dd>';
	}
}
