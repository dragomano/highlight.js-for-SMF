<?php

/**
 * Class-Highlighting.php
 *
 * @package highlight.js for SMF
 * @link https://custom.simplemachines.org/mods/index.php?mod=2925
 * @author Bugo https://dragomano.ru/mods/highlight.js-for-smf
 * @copyright 2010-2024 Bugo
 * @license https://opensource.org/licenses/BSD-3-Clause BSD
 *
 * @version 1.3.4
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

	public function hooks(): void
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
	public function preCssOutput(): void
	{
		global $modSettings;

		if (! $this->shouldItWork() || empty($modSettings['ch_cdn_use']))
			return;

		echo "\n\t" . '<link rel="preload" href="https://cdn.jsdelivr.net/gh/highlightjs/cdn-release@latest/build/styles/' . ($modSettings['ch_style'] ?? 'default') . '.min.css" as="style" onload="this.onload=null;this.rel=\'stylesheet\'">';
	}

	public function loadTheme(): void
	{
		global $modSettings, $context, $settings, $txt;

		loadLanguage('Highlighting/');

		if (! $this->shouldItWork())
			return;

		if (empty($modSettings['ch_cdn_use'])) {
			$context['ch_jss_path'] = $settings['default_theme_url'] . '/scripts/highlight.min.js';
			$context['ch_dln_path'] = $settings['default_theme_url'] . '/scripts/highlightjs-line-numbers.min.js';
			$context['ch_clb_path'] = $settings['default_theme_url'] . '/scripts/clipboard.min.js';

			loadCSSFile('highlight/' . ($modSettings['ch_style'] ?? 'default') . '.min.css');
		} else {
			$context['ch_jss_path'] = 'https://cdn.jsdelivr.net/gh/highlightjs/cdn-release@latest/build/highlight.min.js';
			$context['ch_dln_path'] = 'https://cdn.jsdelivr.net/npm/highlightjs-line-numbers.js@2/dist/highlightjs-line-numbers.min.js';
			$context['ch_clb_path'] = 'https://cdn.jsdelivr.net/npm/clipboard@2/dist/clipboard.min.js';
		}

		loadCSSFile('highlight.css');

		$context['insert_after_template'] .= /** @lang text */ '
		<script src="' . $context['ch_jss_path'] . '"></script>' . (empty($modSettings['ch_line_numbers']) ? '' : '
		<script src="' . $context['ch_dln_path'] . '"></script>') . (empty($modSettings['ch_copy_button']) ? '' : '
		<script src="' . $context['ch_clb_path'] . '"></script>') . '
		<script>
			hljs.highlightAll();' . (empty($modSettings['ch_line_numbers']) ? '' : '
			hljs.initLineNumbersOnLoad();') . (empty($modSettings['ch_copy_button']) ? '' : '
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
			});') . '
		</script>';
	}

	public function bbcCodes(array &$codes): void
	{
		global $modSettings, $txt;

		if (! $this->shouldItWork())
			return;

		$codes = array_filter($codes, function ($code) {
			return $code['tag'] !== 'code';
		});

		$content = '';
		$class = 'block_code';
		if (! empty($modSettings['ch_legacy_links'])) {
			$content = '<div class="codeheader"><span class="code floatleft">' . $txt['code'] . '</span> %s<a class="codeoperation smf_select_text">' . $txt['code_select'] . '</a> <a class="codeoperation smf_expand_code hidden" data-shrink-txt="' . $txt['code_shrink'] . '" data-expand-txt="' . $txt['code_expand'] . '">' . $txt['code_expand'] . '</a></div>';
			$class .= ' bbc_code';
		}

		$fontSize = '';
		if (! empty($modSettings['ch_fontsize'])) {
			$fontSize = ' style="font-size: ' . $modSettings['ch_fontsize'] . '"';
		}

		$codes[] = 	[
			'tag' => 'code',
			'type' => 'unparsed_content',
			'parameters' => [
				'lang' => ['optional' => true, 'value' => ' class="language-$1"'],
				'start' => ['optional' => true, 'match' => '(\d+)', 'value' => ' data-ln-start-from="$1"'],
			],
			'content' => sprintf($content, '') . '<figure class="' . $class . '"' . $fontSize . '><pre><code{lang}{start}>$1</code></pre></figure>',
			'block_level' => true
		];

		$codes[] = [
			'tag' => 'code',
			'type' => 'unparsed_equals_content',
			'content' => sprintf($content, ' <span class="lang">$2</span> ') . '<figure class="' . $class . '"' . $fontSize . '>' . (empty($modSettings['ch_legacy_links']) ? '<figcaption class="codeheader">' . $txt['code'] . ': <span class="lang">$2</span></figcaption>' : '') . '<pre><code class="language-$2">$1</code></pre></figure>',
			'block_level' => true
		];
	}

	public function postParseBbc(string &$message): void
	{
		if (! $this->shouldItWork() || strpos($message, '<pre') === false)
			return;

		$message = preg_replace_callback('~<pre(.*?)>(.*?)</pre>~si', function ($matches) {
			$result = str_replace(['<br>', '<br />'], PHP_EOL, $matches[0]);
			$result = preg_replace('/\s*(<\/code>)/', PHP_EOL . '${1}', $result);
			return preg_replace('/(<code[^>]*>)\s*/', PHP_EOL . '${1}', $result);
		}, $message);
	}

	public function adminAreas(array &$admin_areas): void
	{
		global $txt;

		$admin_areas['config']['areas']['modsettings']['subsections']['highlight'] = [$txt['ch_title']];
	}

	public function adminSearch(array &$language_files, array &$include_files, array &$settings_search): void
	{
		$settings_search[] = [[$this, 'settings'], 'area=modsettings;sa=highlight'];
	}

	public function modifyModifications(array &$subActions): void
	{
		$subActions['highlight'] = [$this, 'settings'];
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
		updateSettings($addSettings);

		$styleList = array_merge(
			glob($settings['default_theme_dir'] . "/css/highlight/*.css"),
			glob($settings['default_theme_dir'] . "/css/highlight/base16/*.css")
		);

		$styleSet  = [];
		foreach ($styleList as $file) {
			$search          = [$settings['default_theme_dir'] . "/css/highlight/", '.css', '.min'];
			$replace         = ['', '', ''];
			$file            = str_replace($search, $replace, $file);
			$styleSet[$file] = ucwords(strtr($file, ['-' => ' ', '/' => ' - ']));
		}

		ksort($styleSet);

		$config_vars = [
			['check', 'ch_enable'],
			['check', 'ch_cdn_use'],
			['select', 'ch_style', $styleSet],
			['select', 'ch_fontsize', self::FONTSIZE_SET],
			['check', 'ch_line_numbers'],
			['check', 'ch_copy_button'],
			['check', 'ch_legacy_links']
		];

		if (! empty($modSettings['ch_enable'])) {
			$config_vars[] = ['callback', 'ch_example'];
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

	public function credits(): void
	{
		global $modSettings, $txt, $context;

		if (empty($modSettings['ch_enable']))
			return;

		$link = $txt['lang_dictionary'] === 'ru' ? 'https://dragomano.ru/mods/highlight.js-for-smf' : 'https://custom.simplemachines.org/mods/index.php?mod=2925';

		$context['credits_modifications'][] = '<a href="' . $link . '" target="_blank" rel="noopener">highlight.js for SMF</a> &copy; 2010&ndash;2024, Bugo';
	}

	private function shouldItWork(): bool
	{
		global $modSettings, $context;

		if (SMF === 'BACKGROUND' || SMF === 'SSI' || empty($modSettings['enableBBC']) || empty($modSettings['ch_enable']))
			return false;

		if (in_array($context['current_action'], ['helpadmin', 'printpage']) || $context['current_subaction'] === 'showoperations')
			return false;

		return empty($modSettings['disabledBBC']) || ! in_array('code', explode(',', $modSettings['disabledBBC']));
	}
}

function template_callback_ch_example(): void
{
	global $settings, $txt;

	if (file_exists($settings['default_theme_dir'] . '/css/admin.css'))	{
		$file = file_get_contents($settings['default_theme_dir'] . '/css/admin.css');
		$file = parse_bbc('[code]' . $file . '[/code]');

		echo '</dl><strong>' . $txt['ch_example'] . '</strong>' . $file . '<dl><dt></dt><dd></dd>';
	}
}
