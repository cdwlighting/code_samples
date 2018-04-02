<?php namespace PowerChalk\MediaConverter\Driver;

/**
 * Description of FFmpegFLV
 *
 * @author chris
 */
class FFmpegFLV extends BaseFFMpegConverter
{
	protected $_flvParameters = array (
		'0'=> '-i %s -vf yadif -y %s -vcodec flv -copyts -y -maxrate 4000k -bufsize 1835k -acodec libmp3lame -ar 22000 -ab 64k -ac 2 -qscale 5 %s %s',
		'90'=> '-i %s -vf \'yadif,transpose=1\' -y %s -vcodec flv -maxrate 4000k -bufsize 1835k -acodec libmp3lame -ar 22000 -ab 64k -ac 2 -copyts -y -qscale 5 %s %s',
		'180'=> '-i %s -vf \'yadif,hflip,vflip\' -y %s -vcodec flv -maxrate 4000k -bufsize 1835k -acodec libmp3lame -ar 22000 -ab 64k -ac 2 -copyts -y  -qscale 5 %s %s',
		'270'=> '-i %s -vf \'yadif,transpose=2\' -y %s -vcodec flv -maxrate 4000k -bufsize 1835k -acodec libmp3lame -ar 22000 -ab 64k -ac 2 -copyts -y  -qscale 5 %s %s'
		);

	protected function _makeFlvParameters($source, $crop, $scale, $dest)
	{
		return sprintf($this->_flvParameters[$this->_videoInfo->getRotation()], $source, $crop, $scale, $dest);
	}

	protected function _makeFrameImageParameters($source, $crop, $scale, $dest)
	{

	}

	protected function _makeFrameThumbNailParameters($source, $crop, $scale, $dest)
	{

	}

	protected function _makeThumbNailParameters($source, $crop, $scale, $dest)
	{

	}

	public function makeFlv()
	{
		if((strpos($this->_videoInfo->getFormat(), 'AVC') != false) ||
			(strpos($this->_videoInfo->getFormat(), 'VP6') != false))
		{
			$this->_logger->info($this->_videoInfo->getFormat() . ' was not (H/x)264');
			$this->_doCopy($this->_videoInfo->getFilename(), $this->_flvDest);
			return true;
		} else {
			return parent::makeFlv();
		}
	}

}