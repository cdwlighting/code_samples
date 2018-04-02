<?php namespace PowerChalk\MediaConverter\Driver;

/**
 * Description of BaseMediaConverter
 *
 * @author chris
 */
abstract class BaseMediaConverter
{
	/**
	 *
	 * @var string
	 */
	protected $_flvDest = '';

	/**
	 *
	 * @var string
	 */
	protected $_mp4Dest = '';

	/**
	 *
	 * @var string
	 */
	protected $_thumbDest = '';

	/**
	 *
	 * @var string
	 */
	protected $_frameDest = '';

	/**
	 *
	 * @var string
	 */
	protected $_frameThumbDest = '';

	/**
	 *
	 * @var VideoInfo
	 */
	protected $_videoInfo = NULL;

	/**
	 *
	 * @var sfLogger
	 */
	protected $_logger = NULL;

	protected function _checkConversion($filename)
	{
		clearstatcache();
		$fileExists = file_exists($filename);
		$fileSize = filesize($filename);
		$fileSize = ($fileSize) ? $fileSize > 0 : $fileSize;

		return $fileSize && $fileExists;
	}

	protected function _doConversion($command)
	{
		$this->_logger->info('Executing: ' . $command . '...');
		exec($command);
	}

	protected function _doCopy($src, $dest)
	{
		copy($src, $dest);
	}

	/**
	 *
	 * @param VideoInfo $videoInfo
	 * @param Video $video
	 * @return iMediaConverter
	 */
	public static function MediaConverterFactory(VideoInfo $videoInfo, Video $video = null)
	{
		$pathInfo = pathinfo($videoInfo->getFilename());

		$extension = strtoupper($pathInfo['extension']);
		$videoFormat = strtoupper($videoInfo->getFormat());

		if($videoFormat == 'INTERMEDIATE CODEC')
			throw new MediaConverter_Exceptions_ICOD;
		if($extension == 'AVI' && $videoFormat == 'MPG')
			$extension = $videoFormat;

		switch($extension) {
			case '3G2':
			case '3GP':
			case 'M2T':
				return new FFmpeg3GP($videoInfo);
				break;

			case 'M2TS':
			case 'MTS':
				return new FFmpegMTS($videoInfo);
				break;

			case 'WMV':
			case 'ASF':
				return new FFmpegASF($videoInfo);
				break;

			case 'AVI':
				if(!is_null($video)) {
					if($video->getOrganizationId() == Organization::GOLFTEC and $video->isChalkTalk()) {
						if($video->isChalkTalk()) {
							return new FFmpegAVIGolfTec($videoInfo);
						}
					}
				}
			case 'MOV':
			case 'MP4':
				return new FFmpegAVI($videoInfo);
				break;

			case 'FLV':
				return new FFmpegFLV($videoInfo);
				break;

//			case 'MP4':
//				return new FFmpegMP4($videoInfo);
//				break;

			case 'MOD': //really MPG from JVC cameras
			case 'F4V': // Flash MPG4 - BATS system
			case 'M4V':
			case 'MPG':
			case 'MPEG':
			case 'VOB': //DVD files
				return new FFmpegVOD($videoInfo);
				break;
			case 'MSWMM':  //windows movie maker
			case 'PNG':
			case 'JPG':
			case 'BMP':
			case "GIF":
			case '':
			default:
				return false;
		}
	}

	public function __construct(VideoInfo $videoInfo)
	{
		$this->_videoInfo = $videoInfo;
		$this->_logger = sfContext::getInstance()->getLogger();
		$source = str_replace('.done', '', $this->_videoInfo->getFilename());

		$pathArray = pathinfo($source);
		$this->_flvDest = sfConfig::get('sf_web_dir') . sfConfig::get('app_flv_complete') . '/';
		$this->_flvDest .= $pathArray['filename'] . '.done.flv';

		$this->_thumbDest = sfConfig::get('sf_web_dir') . sfConfig::get('app_thumb_raw') . '/';
		$this->_thumbDest .= $pathArray['filename'] . '.done.jpeg';

		$this->_mp4Dest = sfConfig::get('sf_web_dir') . sfConfig::get('app_mp4_complete') . '/';
		$this->_mp4Dest .= $pathArray['filename'] . '.done.mp4';
	}

	public function rotate($angle)
	{
		if(!in_array($angle, array('90', '180', '270', '0')))
			throw new OutOfRangeException;

		if($angle == '0')
			return;

		$this->_videoInfo->setRotation($angle);
	}

}
