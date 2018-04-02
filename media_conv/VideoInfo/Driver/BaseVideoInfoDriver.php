<?php namespace PowerChalk\MediaConverter\VideoInfo\Driver;

/**
 * Description of VideoInfoDriver
 *
 * @author chris
 */
abstract class BaseVideoInfoDriver
{
	/**
	 *
	 * @var VideoInfo
	 */
	protected $_videoInfo = null;

	/**
	 *
	 * @var sfLogger
	 */
	protected $_logger = null;

	public function __construct($filename)
	{
		$this->_logger = sfContext::getInstance()->getLogger();

		$this->_videoInfo = new VideoInfo();
		$this->_LoadMediaInfo($filename);
	}

	/**
	 *
	 * @return VideoInfo
	 */
	public function getVideoInfo()
	{
		return $this->_videoInfo;
	}

	protected abstract function _loadMediaInfo($filename);
	/**
	 *
	 * @param type $filename
	 * @return \MediaInfoDriver
	 */
	public static function MediaInfoDriver($filename)
	{
		return new MediaInfoDriver($filename);
	}

}
