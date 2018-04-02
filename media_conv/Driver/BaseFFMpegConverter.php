<?php namespace PowerChalk\MediaConverter\Driver;
/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of BaseFFMpegConverter
 *
 * @author chris
 */
abstract class BaseFFMpegConverter extends BaseMediaConverter implements iMediaConverter
{
	protected $_ffmpeg = 'ffmpeg ';
	protected $_thumbnailParameters = ' -y -ss %s -i %s -vframes 1 -an -f mjpeg %s %s';
	protected $_mp4Parameters = array(
		'0'		=> ' -y -i %s -vf \'yadif\' %s -threads 0 -qscale 5 %s %s %s',
		'90'	=> ' -y -i %s -vf \'yadif,transpose=1\' -threads 0 %s -qscale 5 %s %s %s',
		'180'	=> ' -y -i %s -vf \'yadif,hflip,vflip\' -threads 0 %s -qscale 5 %s %s %s',
		'270'	=> ' -y -i %s -vf \'yadif,transpose=2\' -threads 0 %s -qscale 5 %s %s %s');
	// Start Time, Input filename, VFilters, End Time, output filename.
	protected $_editParameters = ' -y -ss %s -i %s %s -vframes %s -acodec copy -vcodec copy -sameq %s';
	protected $_command;

	protected function _scale($maxHeight = 480)
	{
		$videoInfo = $this->_videoInfo;
		$scale = '';
		$vHeight = (int) floor($videoInfo->getHeight());
		$vWidth = (int) floor($videoInfo->getWidth());
		$vAspect = $vWidth / $vHeight;

		$rotation = (int) $videoInfo->getRotation();

		if($rotation == 90 || $rotation == 270)
		{
			$width = $vHeight;
			$vHeight = $vWidth;
			$vWidth = $width;
			$vAspect = 1 / $vAspect;
		}

		if($vHeight > $maxHeight)
		{
			$newHeight = $maxHeight;
			$newWidth = (int) floor($newHeight * $vAspect);
			if($newWidth % 2)
			{
				$newWidth++;
			}
			if($rotation == 90 || $rotation == 270)
			{
				$scale = '-s ' . $newHeight . 'x' . $newWidth;
			}
			else
			{
				$scale = '-s ' . $newWidth . 'x' . $newHeight;
			}
		}
		return $scale;
	}

	protected function _scaleThumbNail()
	{
		$vWidth = $this->_videoInfo->getWidth();
		$vHeight = $this->_videoInfo->getHeight();

		if($vWidth < $vHeight)  //portrait movie
		{
			$w = (int) floor(90 / floor($vHeight) * $vWidth);
			if($w % 2) // 0 = even, 1 = odd
			{
				$w++;
			}
			$WxH = $w . "x90";
		}
		else
		{
			$WxH = '120x90';
		}

		return '-s ' . $WxH;
	}

	protected function _crop($startx, $starty, $width, $height)
	{
		$crop = '';
		return $crop;
	}

	abstract protected function _makeFlvParameters($source, $crop, $scale, $dest);
	abstract protected function _makeFrameImageParameters($source, $crop, $scale, $dest);
	abstract protected function _makeFrameThumbNailParameters($source, $crop, $scale, $dest);
	abstract protected function _makeThumbNailParameters($source, $crop, $scale, $dest);
	/**
	 *
	 * @param string $source
	 * @param string $crop
	 * @param string $scale
	 * @param string $dest
	 * @return string
	 */
	protected function _makeMp4Parameters($source, $crop, $scale, $dest)
	{
		if(strpos($this->_videoInfo->getFormat(), 'AVC') !== false)
		{
			$codecArgs = '-vcodec copy ';
		}
		else
		{
			$codecArgs = '-vcodec libx264 -vpre fast -crf 22 ';
		}

		$command = sprintf($this->_mp4Parameters[$this->_videoInfo->getRotation()], $source, $crop, $scale, $codecArgs, $dest);
		return $command;
	}

	/**
	 *
	 * @return boolean
	 */
	public function makeFlv()
	{
		$video = $this->_videoInfo;

		//Build the transformation parameters.
		$startx = $starty = $width = $height = 0;
		$crop = '';
		$scale = $this->_scale();

		//Build the ffmpeg command line.
		$this->_command = $this->_makeFlvParameters(
			$video->getFilename(), $crop, $scale, $this->_flvDest);

		if(empty($this->_command))
		{
			$this->_logger->crit(__METHOD__ . ' - Empty command, unable to run conversion.');
			return false;
		}

		$this->_command = $this->_ffmpeg . $this->_command;
		$this->_doConversion($this->_command);

		// Check the conversion has successfully completed.
		if(!$this->_checkConversion($this->_flvDest))
		{
			$this->_logger->info(__METHOD__ . ' - Did not return a valid video, command: ' . $this->_command);
			return false;
		}

		return true;
	}

	public function getLastCommand()
	{
		return $this->_command;
	}

	public function makeFrameImages()
	{
		$video = $this->_videoInfo;
	}

	public function makeFrameThumbnails()
	{
		$video = $this->_videoInfo;
	}

	/**
	 *
	 * @return boolean
	 */
	public function makeMp4()
	{
		$video = $this->_videoInfo;
		$video = $this->_videoInfo;

		//Build the transformation parameters.
		$startx = $starty = $width = $height = 0;
		$crop = '';
		$scale = $this->_scale(720);

		//Build the ffmpeg command line.
		$command = $this->_makeMp4Parameters(
			$video->getFilename(), $crop, $scale, $this->_mp4Dest);

		if(empty($command))
		{
			$this->_logger->crit(__METHOD__ . ' - Empty command, unable to run conversion.');
			return false;
		}

		$command = $this->_ffmpeg . $command;
		$this->_doConversion($command);

		// Check the conversion has successfully completed.
		if(!$this->_checkConversion($this->_mp4Dest))
		{
			$this->_logger->info(__METHOD__ . ' - Did not return a valid video, command: ' . $command);
			return false;
		}

		return true;
	}

	public function makeThumbnail()
	{
		$frameCount = $this->_videoInfo->getFrameCount();

		$desiredFrames[] = round(ceil($frameCount * .20) / $this->_videoInfo->getFrameRate(), 3);
		$desiredFrames[] = 1.000;
		$desiredFrames[] = 0.100;

		$srcFileName = $this->_videoInfo->getFilename();
		$outputFileName = $this->_thumbDest;
//		$scale = $this->_scaleThumbNail();

		foreach($desiredFrames as $desiredFrame)
		{
			$command = sprintf($this->_thumbnailParameters, $desiredFrame, $srcFileName, '', $outputFileName);
			if(empty($command))
			{
				$this->_logger->crit(__METHOD__ . ' - Empty command, unable to run conversion.');
				return false;
			}

			$command = $this->_ffmpeg . $command;
			$this->_doConversion($command);
			$result = $this->_checkConversion($outputFileName);
			if($result)
			{
				break;
			}
			else
			{
				$this->_logger->warning(__METHOD__ . ' - The ffmpeg command did not render a valid thumbnail, command:
' . $command);
			}
		}

		return $result;
	}

	public function makeZip($start, $duration)
	{
		$video = $this->_videoInfo;
	}

	public function getFlv()
	{
		return $this->_flvDest;
	}

	public function getMP4()
	{
		return $this->_mp4Dest;
	}

	public function getThumbnail()
	{
		return $this->_thumbDest;
	}

	public function makeEdit($newFile, $inFrame, $outframe, $width, $height, $x, $y)
	{
		$inSeconds = $inMinutes = $inHours = 0;
		$outSeconds = $outMinutes = $outHours = 0;

		$inSeconds = $inFrame / 1000;
		$inMinutes = (int) floor($inSeconds / 60);
		$inSeconds = $inSeconds - ($inMinutes * 60);
		$inHours = (int) floor($inMinutes / 60);
		$inMinutes = $inMinutes - ($inMinutes * 60);

		$inTime = sprintf("%02d:%02d:%02d");

		$outSeconds = $outframe / 1000;
		$outMinutes = (int) floor($inSeconds / 60);
		$outSeconds = $outSeconds - ($outMinutes * 60);
		$outHours = (int) floor($outMinutes / 60);
		$outMinutes = $outMinutes - ($outHours * 60);

		$outTime = sprintf("%02d:%02d:%02d");

		$crop = $this->_crop($x, $y, $width, $height);

		$sourceFile = $this->_videoInfo->getFilename();
		$pathInfo = pathinfo($sourceFile);

		$destFile = $pathInfo['dirname'] . '/' . $newFile;

		$command = sprintf($this->_editParameters, $inTime, $sourcefile, $crop, $outTime, $destFile);
		if(empty($command))
		{
			$this->_logger->crit(__METHOD__ . ' - Empty command, unable to run conversion.');
			return false;
		}
		$command = $this->_ffmpeg . $command;
		$this->_doConversion($command);

		if($this->_checkConversion($destFile))
		{
			$this->_logger->warning('The ffmpeg Command: ' . $command .
				'did not render a valid edited file.');
		}
	}

}
