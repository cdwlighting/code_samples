<?php namespace PowerChalk\MediaConverter\Driver;

/**
 * Description of FFmpegAVI
 *
 * @author chris
 */
class FFmpegAVI extends BaseFFMpegConverter
{
	protected $_flvParameters = array(
		array(
			'0' => '-i %s %s -vf yadif -copyts -y -qscale 10 -acodec libmp3lame -ar 22000 -ab 64k -ac 2 %s %s',
			'90' => '-i %s %s  -vf \'yadif,transpose=1\' -copyts -y -qscale 10 -acodec libmp3lame -ar 22000 -ab 64k -ac 2 %s %s',
			'180' => '-i %s %s  -vf \'yadif,hflip,vflip\' -copyts -y -qscale 10 -acodec libmp3lame -ar 22000 -ab 64k -ac 2 %s %s',
			'270' => '-i %s %s  -vf \'yadif,transpose=2\' -copyts -y -qscale 10 -acodec libmp3lame -ar 22000 -ab 6k4 -ac 2 %s %s'),
		array(
			'0' => '-i %s %s -vf yadif -copyts -y -qscale 10 -acodec libmp3lame -ar 22000 -ab 64k -ac 2 %s %s',
			'90' => '-i %s %s -vf \'yadif,transpose=1\' -copyts -y -qscale 10 -acodec libmp3lame -ar 22000 -ab 64k -ac 2 %s %s',
			'180' => '-i %s %s -vf \'yadif,hflip,vflip\' -copyts -y -qscale 10 -acodec libmp3lame -ar 22000 -ab 64k -ac 2 %s %s',
			'270' => '-i %s %s -vf \'yadif,transpose=2\' -copyts -y -qscale 10 -acodec libmp3lame -ar 22000 -ab 64k -ac 2 %s %s')
	);

	protected function _makeFlvParameters($source, $crop, $scale, $dest)
	{

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
		$video = $this->_videoInfo;

		$startx = $starty = $width = $height = 0;
		$crop = $this->_crop($startx, $starty, $width, $height);
		$scale = $this->_scale();

		foreach($this->_flvParameters as $parameter) {
			$this->_command = sprintf($parameter[$video->getRotation()], $video->getFilename(), $crop, $scale, $this->_flvDest);

			if(empty($this->_command)) {
				$this->_logger->crit(__METHOD__ . ' - Empty command, unable to run conversion.');
				return false;
			}

			$this->_command = $this->_ffmpeg . $this->_command;
			$this->_doConversion($this->_command);
			if($this->_checkConversion($this->_flvDest)) {
				return true;
			}
			$this->_logger->crit(__METHOD__ . ' - Command did not return a valid video, command: ' . $this->_command);
		}

		if(!$this->_checkConversion($this->_flvDest))
			return false;

		return true;
	}

}
