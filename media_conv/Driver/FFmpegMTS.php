<?php namespace PowerChalk\MediaConverter\Driver;

/**
 * Description of FFmpegMTS
 *
 * @author chris
 */
class FFmpegMTS extends BaseFFMpegConverter
{
	protected $_flvParams = array(
		'0' => '-i %s %s -vf yadif -deinterlace -f flv -y -qscale 5 -acodec libmp3lame -ar 22000 -ab 64k -ac 2 %s -sameq %s',
		'90' => '-i %s %s -vf \'yadif,transpose=1\' -deinterlace -f flv -y -qscale 5 -acodec libmp3lame -ar 22000 -ab 64k -ac 2 %s -sameq %s',
		'180' => '-i %s %s -vf \'yadif,hflip,vflip\' -deinterlace -f flv -y -qscale 5 -acodec libmp3lame -ar 22000 -ab 64k -ac 2 %s -sameq %s',
		'270' => '-i %s %s -vf \'yadif,transpose=2\' -deinterlace -f flv -y -qscale 5 -acodec libmp3lame -ar 22000 -ab 64k -ac 2 %s -sameq %s'
	);

	protected function _makeFlvParameters($source, $crop, $scale, $dest)
	{
		$command = sprintf($this->_flvParams[$this->_videoInfo->getRotation()], $source, $crop, $scale, $dest);
		return $command;
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

}
