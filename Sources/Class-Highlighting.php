<?php

/**
 * Class-Highlighting.php
 *
 * @package highlight.js for SMF
 * @link https://custom.simplemachines.org/mods/index.php?mod=2925
 * @author Bugo https://dragomano.ru/mods/highlight.js-for-smf
 * @copyright 2010-2022 Bugo
 * @license https://opensource.org/licenses/BSD-3-Clause BSD
 *
 * @version 1.2.3
 */

if (!defined('SMF'))
	die('No direct access...');

final class Highlighting
{
	private const FONTSIZE_SET = [
		'x-small' => 'x-small',
		'smaller' => 'smaller',
		'small'   => 'small',
		'medium'  => 'medium',
		'large'   => 'large',
		'x-large' => 'x-large'
	];

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

	public function loadTheme()
	{
		global $modSettings, $context, $settings, $txt;

		loadLanguage('Highlighting/');

		if (! $this->shouldItWork())
			return;

		if (! empty($modSettings['ch_cdn_use'])) {
			$context['ch_jss_path'] = 'https://cdn.jsdelivr.net/gh/highlightjs/cdn-release@latest/build/highlight.min.js';
			$context['ch_dln_path'] = 'https://cdn.jsdelivr.net/npm/highlightjs-line-numbers.js@2/dist/highlightjs-line-numbers.min.js';
			$context['ch_clb_path'] = 'https://cdn.jsdelivr.net/npm/clipboard@2/dist/clipboard.min.js';
		} else {
			$context['ch_jss_path'] = $settings['default_theme_url'] . '/scripts/highlight.min.js';
			$context['ch_dln_path'] = $settings['default_theme_url'] . '/scripts/highlightjs-line-numbers.min.js';
			$context['ch_clb_path'] = $settings['default_theme_url'] . '/scripts/clipboard.min.js';

			loadCSSFile('highlight/' . ($modSettings['ch_style'] ?? 'default') . '.min.css');
		}

		loadCSSFile('highlight.css');

		$context['insert_after_template'] .= '
		<script src="' . $context['ch_jss_path'] . '"></script>' . (! empty($modSettings['ch_line_numbers']) ? '
		<script src="' . $context['ch_dln_path'] . '"></script>' : '') . '
		<script src="' . $context['ch_clb_path'] . '"></script>
		<script>
			hljs.highlightAll();' . (! empty($modSettings['ch_line_numbers']) ? '
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

	public function bbcCodes(array &$codes)
	{
		global $modSettings, $txt;

		if (! $this->shouldItWork())
			return;

		if (! empty($modSettings['ch_fontsize'])) {
			$fontSize = ' style="font-size: ' . $modSettings['ch_fontsize'] . '"';
		}

		$codes = array_filter($codes, function ($code) {
			return $code['tag'] !== 'code';
		});

		$codes[] = 	array(
			'tag' => 'code',
			'type' => 'unparsed_content',
			'parameters' => array(
				'lang' => array('optional' => true, 'value' => ' class="language-$1"'),
				'start' => array('optional' => true, 'match' => '(\d+)', 'value' => ' data-ln-start-from="$1"'),
			),
			'content' => '<figure class="block_code"' . ($fontSize ?? '') . '><pre><code{lang}{start}>$1</code></pre></figure>',
			'block_level' => true
		);

		$codes[] = array(
			'tag' => 'code',
			'type' => 'unparsed_equals_content',
			'content' => '<figure class="block_code"' . ($fontSize ?? '') . '><figcaption class="codeheader">' . $txt['code'] . ': $2</figcaption><pre><code class="language-$2">$1</code></pre></figure>',
			'block_level' => true
		);
	}

	public function postParseBbc(string &$message)
	{
		if (! $this->shouldItWork() || strpos($message, '<pre') === false)
			return;

		$message = preg_replace_callback('~<pre(.*?)>(.*?)<\/pre>~si', function ($matches) {
			$result = str_replace(['<br>', '<br />'], PHP_EOL, $matches[0]);
			$result = preg_replace('/\s*(<\/code>)/', PHP_EOL . '${1}', $result);
			$result = preg_replace('/(<code[^>]*>)\s*/', PHP_EOL . '${1}', $result);
			return $result;
		}, $message);
	}

	public function adminAreas(array &$admin_areas)
	{
		global $txt;

		$admin_areas['config']['areas']['modsettings']['subsections']['highlight'] = array($txt['ch_title']);
	}

	public function adminSearch(array &$language_files, array &$include_files, array &$settings_search)
	{
		$settings_search[] = array(array($this, 'settings'), 'area=modsettings;sa=highlight');
	}

	public function modifyModifications(array &$subActions)
	{
		$subActions['highlight'] = array($this, 'settings');
	}

	/**
	 * @return array|void
	 */
	public function settings($return_config = false)
	{
		global $context, $txt, $scripturl, $modSettings, $settings;

		$context['page_title']     = $txt['ch_title'];
		$context['settings_title'] = $txt['ch_settings'];
		$context['post_url']       = $scripturl . '?action=admin;area=modsettings;save;sa=highlight';

		$addSettings = [];
		if (! isset($modSettings['ch_cdn_use']))
			$addSettings['ch_cdn_use'] = 1;
		if (! isset($modSettings['ch_style']))
			$addSettings['ch_style'] = 'default';
		if (! isset($modSettings['ch_fontsize']))
			$addSettings['ch_fontsize'] = 'medium';
		if (! empty($addSettings))
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

		ksort($style_set);

		$config_vars = array(
			array('check', 'ch_enable'),
			array('check', 'ch_cdn_use'),
			array('select', 'ch_style', $style_set),
			array('select', 'ch_fontsize', self::FONTSIZE_SET),
			array('check', 'ch_line_numbers')
		);

		if (! empty($modSettings['ch_enable']) && function_exists('file_get_contents')) {
			$config_vars[] = array('callback', 'ch_example');
			$config_vars[] = '<br>';
		}

		if ($return_config)
			return $config_vars;

		$context[$context['admin_menu_name']]['tab_data']['description'] = $txt['ch_desc'];

		// Saving?
		if (isset($_GET['save'])) {
			checkSession();

			$save_vars = $config_vars;
			saveDBSettings($save_vars);

			redirectexit('action=admin;area=modsettings;sa=highlight');
		}

		prepareDBSettingContext($config_vars);
	}

	public function credits()
	{
		global $modSettings, $context;

		if (empty($modSettings['ch_enable']))
			return;

		$link = $context['user']['language'] == 'russian' ? 'https://dragomano.ru/mods/highlight.js-for-smf' : 'https://custom.simplemachines.org/mods/index.php?mod=2925';

		$context['credits_modifications'][] = '<a href="' . $link . '" target="_blank" rel="noopener">highlight.js for SMF</a> &copy; 2010&ndash;2022, Bugo';
	}

	private function shouldItWork(): bool
	{
		global $modSettings, $context;

		if (SMF === 'BACKGROUND' || SMF === 'SSI' || empty($modSettings['enableBBC']) || empty($modSettings['ch_enable']))
			return false;

		if (in_array($context['current_action'], array('helpadmin', 'printpage')) || $context['current_subaction'] === 'showoperations')
			return false;

		return empty($modSettings['disabledBBC']) || ! in_array('code', explode(',', $modSettings['disabledBBC']));
	}
}

function template_callback_ch_example()
{
	global $settings, $txt;

	if (file_exists($settings['default_theme_dir'] . '/css/admin.css'))	{
		$file = file_get_contents($settings['default_theme_dir'] . '/css/admin.css');
		$file = parse_bbc('[code]' . $file . '[/code]');

		echo '</dl><strong>' . $txt['ch_example'] . '</strong>' . $file . '<dl><dt></dt><dd></dd>';
	}
}
