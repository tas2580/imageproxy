<?php
/**
*
* @package phpBB Extension - Image Proxy
* @copyright (c) 2015 tas2580 (https://tas2580.net)
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

namespace tas2580\imageproxy\controller;

class main
{
	/** @var \phpbb\cache\driver\driver_interface */
	protected $cache;
	/** @var \phpbb\request\request */
	protected $request;

	/**
	* Constructor
	*
	* @param \phpbb\request\request		$request				Request object
	*/
	public function __construct(\phpbb\cache\driver\driver_interface $cache, \phpbb\request\request $request)
	{
		$this->cache = $cache;
		$this->request = $request;
	}

	/**
	 * Read the remote image and return it as local image
	 */
	public function image()
	{
		$img = $this->request->variable('img', '', true);
		$cache_file = '_imageproxy_' . $img;
		$data = $this->cache->get($cache_file);
		if ($data === false)
		{
			$headers = @get_headers($img, true);
			if($headers[0] != 'HTTP/1.1 200 OK')
			{
				// 1x1px transparent png
				$data = array(
					'header'	=> 'image/png',
					'content'	=> 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAABnRSTlMAAAAAAABupgeRAAAADElEQVQImWNgYGAAAAAEAAGjChXjAAAAAElFTkSuQmCC',
				);
			}
			else
			{
				// Create a HTTP header with user agent
				$options = array(
					'http' => array(
						'method'	=> "GET",
						'header'	=> "Accept-language: en\r\nUser-Agent: Mozilla/5.0 (X11; Linux i686; rv:10.0) Gecko/20100101 Firefox/10.0\r\n"
					)
				);
				$context = stream_context_create($options);

				// Get the remote image
				$data = array(
					'header'	=>$headers['Content-Type'],
					'content'	=> base64_encode(file_get_contents($img, false, $context)),
				);
			}
			// Cache for 1 day
			$this->cache->put($cache_file, $data, 86400);
		}

		header ('Content-type: ' . $data['header']);
		exit(base64_decode($data['content']));
	}
}
