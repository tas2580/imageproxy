<?php
/**
*
* @package phpBB Extension - Image Proxy
* @copyright (c) 2016 tas2580 (https://tas2580.net)
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

namespace tas2580\imageproxy\event;

/**
* @ignore
*/
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
* Event listener
*/
class listener implements EventSubscriberInterface
{
	/** @var \phpbb\controller\helper */
	protected $helper;

	/** @var \phpbb\template\template */
	protected $template;

	/** @var \phpbb\user */
	protected $user;

	/**
	 * Constructor
	 *
	 * @param \phpbb\controller\helper		$helper
	 * @param \phpbb\user				$user
	 * @param \phpbb\template\template	$template
	 */
	public function __construct(\phpbb\controller\helper $helper, \phpbb\user $user, \phpbb\template\template $template)
	{
		$this->helper = $helper;
		$this->user = $user;
		$this->template = $template;
	}

	/**
	 * Assign functions defined in this class to event listeners in the core
	 *
	 * @return array
	 * @static
	 * @access public
	 */
	static public function getSubscribedEvents()
	{
		return array(
			'core.bbcode_cache_init_end'		=> 'modify_case_img',
			// phpBB 3.2
			'core.text_formatter_s9e_configure_after'	=> 'configure_textformatter',
		);
	}

	/**
	 * Configures the textformatter
	 *
	 * @param \phpbb\event\data $event
	 * @return null
	 * @access public
	 */
	public function configure_textformatter($event)
	{
		/** @var \s9e\TextFormatter\Configurator $configurator */
		$configurator = $event['configurator'];

		$bbcode_monkey = new \s9e\TextFormatter\Plugins\BBCodes\Configurator\BBCodeMonkey($configurator);

		// Unfortunately, this has to be hardcoded
		$parsed_img = $bbcode_monkey->create('[IMG src={IMAGEURL;useContent}]', '<img src="' . $this->helper->route('tas2580_imageproxy_main', array()) . '?img={IMAGEURL}" class="postimage" alt="{L_IMAGE}"/>');
		$configurator->tags['IMG'] = $parsed_img['tag'];
	}

	/**
	 * Changes the regex replacement for second pass
	 *
	 * @param \phpbb\event\data $event
	 * @return null
	 * @access public
	 */
	public function modify_case_img($event)
	{
		$bbcode_id = 4; // [img] has bbcode_id 4 hardcoded
		$bbcode_cache = $event['bbcode_cache'];

		if (!isset($bbcode_cache[$bbcode_id]) || !$this->user->optionget('viewimg'))
		{
			return;
		}

		$this->template->set_filenames(array('bbcode.html' => 'bbcode.html'));

		$bbcode = new \bbcode();
		// We need these otherwise we cannot use $bbcode->bbcode_tpl()
		$bbcode->template_bitfield = new \bitfield($this->user->style['bbcode_bitfield']);
		$bbcode->template_filename = $this->template->get_source_file_for_handle('bbcode.html');

		$extimgaslink_boardurl = generate_board_url() . '/';

		$url = $this->helper->route('tas2580_imageproxy_main', array());

		$bbcode_cache[$bbcode_id] = array(
			'preg' => array(
				// display only images from own board url
				'#\[img:$uid\]('. preg_quote($extimgaslink_boardurl, '#') . '.*?)\[/img:$uid\]#s'	=> $bbcode->bbcode_tpl('img', $bbcode_id),
				// every other external image will be replaced
				'#\[img:$uid\](.*?)\[/img:$uid\]#s' 	=> str_replace('$1', $url. '?img=$1', $bbcode->bbcode_tpl('img', $bbcode_id, true)),
			)
		);

		$event['bbcode_cache'] = $bbcode_cache;
	}
}
