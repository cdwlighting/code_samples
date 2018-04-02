<?php namespace PowerChalk\MediaConverter\Driver;

/**
 * Description of FFmpegASF
 *
 * @author chris
 */
class FFmpegASF extends BaseFFMpegConverter
{
	protected $flvParameters = array(
		'0' => '-i %s %s -vf yadif -ab 64 -qscale 5 -y -ar 44100 %s -sameq -maxrate 4000k -bufsize 1835k -acodec libmp3lame -ar 22000 -ab 64k -ac 2 %s',
		'90' => '-i %s %s -ab 64 -vf \'yadif,transpose=1\' -qscale 5 -y -ar 44100 %s -sameq -maxrate 4000k -bufsize 1835k -acodec libmp3lame -ar 22000 -ab 64k -ac 2 %s',
		'180' => '-i %s %s -vf \'yadif,hflip,vflip\' -ab 64 -qscale 5 -y -ar 44100 %s -sameq -maxrate 4000k -bufsize 1835k -acodec libmp3lame -ar 22000 -ab 64k -ac 2 %s',
		'270' => '-i %s %s -vf \'yadif,transpose=2\' -ab 64 -qscale 5 -y -ar 44100 %s -sameq -maxrate 4000k -bufsize 1835k -acodec libmp3lame -ar 22000 -ab 64k -ac 2 %s'
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
