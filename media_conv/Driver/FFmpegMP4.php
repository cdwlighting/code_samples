<?php namespace PowerChalk\MediaConverter\Driver;

/**
 * Description of FFmpegMP4
 *
 * @author chris
 */
class FFmpegMP4 extends BaseFFMpegConverter
{
	protected $flvParameters = array(
		'-i %s %s -copyts -y -acodec copy -qscale 5 -qmin 5 %s %s',
		'-i %s -y %s -copyts -y -an -qscale 5 -qmin 5 %s %s'
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

		foreach($this->flvParameters as $parameter) {
			$this->_command = sprintf($parameter, $video->getFilename(), $crop, $scale, $this->_flvDest);
			$user = explode('_', $video->getFilename());
			$user_id = $user[0];
			if($user_id == 29) {
				$this->_command = str_replace('-qmin', '-g 1 -qmin', $this->_command);
			}

			if(empty($this->_command)) {
				$this->_logger->crit(__METHOD__ . ' - Empty command, unable to run conversion.');
				return false;
			}

			$this->_command = $this->_ffmpeg . $this->_command;
			$this->_doConversion($this->_command);

			if($this->_checkConversion($this->_flvDest)) {
				$this->_logger->info($this->_command . ' Rendered a video file.');
				return true;
			}

			$this->_logger->info($this->_command . 'Did not render a good video file.');
		}

		return false;
	}

}
