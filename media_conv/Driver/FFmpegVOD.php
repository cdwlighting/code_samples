<?php namespace PowerChalk\MediaConverter\Driver;

/**
 * Description of FFmpegVOD
 *
 * @author chris
 */
class FFmpegVOD extends BaseFFMpegConverter
{
	protected $flvParameters = array(
		'0' => '-i %s %s -vf \'yadif\' -y -vcodec flv -qscale 5 -maxrate 4000k -bufsize 1835k -acodec libmp3lame -ar 22000 -ab 64k -ac 2 %s -sameq %s',
		'90' => '-i %s %s -vf \'yadif,transpose=1\' -y -vcodec flv -qscale 5 -maxrate 4000k -bufsize 1835k -acodec libmp3lame -ar 22000 -ab 64k -ac 2 %s -sameq %s',
		'180' => '-i %s %s -vf \'yadif,hflip,vflip\' -y -vcodec flv -qscale 5 -maxrate 4000k -bufsize 1835k -acodec libmp3lame -ar 22000 -ab 64k -ac 2 %s -sameq %s',
		'270' => '-i %s %s -vf \'yadif,transpose=2\' -y -vcodec flv -qscale 5 -maxrate 4000k -bufsize 1835k -acodec libmp3lame -ar 22000 -ab 64k -ac 2 %s -sameq %s',
	);

	protected function _makeFlvParameters($source, $crop, $scale, $dest)
	{
		return sprintf($this->flvParameters[$this->_videoInfo->getRotation()], $source, $crop, $scale, $dest);
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
