<?php namespace PowerChalk\MediaConverter\Driver;

/**
 * Description of FFmpeg3GP
 *
 * @author chris
 */
class FFmpeg3GP extends BaseFFMpegConverter
{
	protected $flvParams = array(
		'0'=> '-i %s %s -vf \'yadif\' -y -qscale 5 -qmin 5 -maxrate 4000k -bufsize 8000k -acodec libmp3lame -ar 22000 -ab 64k -ac 2 %s %s',
		'90'=> '-i %s %s -vf \'yadif,transpose=1\' -y -qscale 5 -qmin 5 -maxrate 4000k -bufsize 8000k -acodec libmp3lame -ar 22000 -ab 64k -ac 2 %s %s',
		'180'=> '-i %s %s -vf \'yadif,hflip,vflip\' -y -qscale 5 -qmin 5 -maxrate 4000k -bufsize 8000k -acodec libmp3lame -ar 22000 -ab 64k -ac 2 %s %s',
		'270'=> '-i %s %s -vf \'yadif,transpose=2\' -y -qscale 5 -qmin 5 -maxrate 4000k -bufsize 8000k -acodec libmp3lame -ar 22000 -ab 64k -ac 2 %s %s' );

	protected function _makeFlvParameters($source, $crop, $scale, $dest)
	{

		$command = sprintf($this->flvParams[$this->_videoInfo->getRotation()], $source, $crop, $scale, $dest);

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