<?php namespace PowerChalk\MediaConverter\Conversion;

use PowerChalk\MediaConverter\VideoInfo\Driver\MediaInfoDriver;

/**
 * Description of ConversionDriverFFMPEG
 *
 * @author chris
 */
class ConversionDriverFFMPEG extends BaseConversionDriver implements iVideoConverter
{
	protected $ffmpeg = 'ffmpeg';
	protected $ffmpeg_command = array(
		// input filename, ffmpeg scale, output filename
		'3GP' => '-i %s -an -y -qscale 5 -sameq %s %s',
		// input_filename, ffmpeg_scale, output_filename
		'GIF' => '-r 1 -f gif -i %s -an -y %s %s',
		// input_filename,  ffmpeg_crop, output_Filename
		'MTS' => '-i %s %s -f flv -an -y -ab 64 -qscale 5 -s 640x480 -qscale 1 -r 15 -sameq %s',
		// input_filename, ffmpeg_crop, ffmpeg_scale, output_Filename
		'ASF' => '-i %s %s -ab 64 -qscale 5 -y -ar 44100 -b 300k -r 30 %s -sameq %s',
		'AVI' => array(
			// input_filename, ffmpeg_crop, ffmpeg_scale, output_filename
			array(
				'' => '-i %s %s -copyts -y -acodec copy -qscale 5 %s %s',
				'90' => '-i %s %s  -vf \'transpose=1\' -copyts -y -acodec copy -qscale 5 %s %s',
				'180' => '-i %s %s  -vf \'hflip,vflip\' -copyts -y -acodec copy -qscale 5 %s %s',
				'270' => '-i %s %s  -vf \'transpose=2\' -copyts -y -acodec copy -qscale 5 %s %s'),
			array(
				'' => '-i %s -y %s -copyts -y -an -qscale 5 %s %s',
				'90' => '-i %s -y %s -vf \'transpose=1\' -copyts -y -an -qscale 5 %s %s',
				'180' => '-i %s -y %s -vf \'hflip,vflip\' -copyts -y -an -qscale 5 %s %s',
				'270' => '-i %s -y %s -vf \'transpose=2\' -copyts -y -an -qscale 5 %s %s')
		),
		// input_filename, ffmpeg_crop, ffmpeg_scale, output_filename
		'FLV' => '-i %s -y %s -copyts -y -an -qscale 5 %s %s',
		// input_filename, ffmpeg_crop, ffmeg_scale, output_filename
		'MPG' => array(
			'-i %s %s -copyts -y -acodec copy -qscale 5 %s %s',
			'-i %s -y %s -copyts -y -an -qscale 5 %s %s'
		),
		// input_filename, ffmpeg_crop, ffmpeg_scale output_format
		'MOD' => array(
			'-i %s -y -vcodec copy -an -ab 64 -qscale 1 $s -sameq %s'
		)
	);

	public function makeFlv(VideoInfo $videoInfo)
	{
		$crop = '';
		$scale = '';

		$srcFile = $videoInfo->getFilename();
		$fileInfo = pathinfo($videoInfo->getFilename());
		$extension = strtoupper($fileInfo['extension']);
		$filename = $fileInfo['filename'];
		$flvName = $fileInfo['dirname'] . '/' . $filename . '.flv';
		$videoFormat = $strtoupper($videoInfo->getFormat());

		$scale = $this->_scaleVideo($videoInfo);

		if($extension == 'AVI' && $videoFormat == 'MPG')
			$extension = $videoFormat;

		switch($extension) {
			case '3G2':
			case '3GP':
				$ffmpeg_cmd = $this->ffmpeg . ' ' . $ffmpeg_command['3GP'];
				$ffmpeg_cmd = sprintf($ffmpeg_cmd, $srcFile, $crop, $scale, $flvName);
				$this->executeCommand($ffmpeg_cmd);
				break;

			case 'GIF':
				$ffmpeg_cmd = $this->ffmpeg . ' ' . $ffmpeg_command['GIF'];
				$ffmpeg_cmd = sprintf($ffmpeg_cmd, $srcFile, $scale, $flvName);
				$this->executeCommand($ffmpeg_cmd);
				break;

			case 'M2TS':
			case 'MTS':
				$ffmpeg_cmd = $this->ffmpeg . ' ' . $ffmpeg_command['MTS'];
				$ffmpeg_cmd = sprintf($ffmpeg_cmd, $srcFile, $crop, $flvName);
				$this->executeCommand($ffmpeg_cmd);
				break;

			case 'WMV':
			case 'ASF':
				$ffmpeg_cmd = $this->ffmpeg . ' ' . $ffmpeg_command['ASF'];
				$ffmpeg_cmd = sprintf($ffmpeg_cmd, $srcFile, $crop, $scale, $flvName);
				$this->executeCommand($ffmpeg_cmd);
				break;

			case 'MOV':
			case 'AVI':
				$rotation = $videoInfo->getRotation();
				foreach($this->ffmpeg_command['AVI'] as $command) {
					$ffmpeg_cmd = $this->ffmpeg . ' ' . $command[$rotation];
					$ffmpeg_cmd = sprintf($ffmpeg_cmd, $srcFile, $crop, $scale, $flvName);
					if($this->checkConversion($videoInfo)) {
						break;
					}
				}
				break;

			case 'FLV':
				if(stripos($videoFormat, "264") === false) {
					$this->copyFile($srcFile, $flvName);
				}
				else { // H264 in an flv container, convert it to Sorenson Sparc
					$ffmpeg_cmd = $this->ffmpeg . ' ' . $this->ffmpeg_command['FLV'];
					$ffmpeg_cmd = sprintf($ffmpeg_cmd, $srcFile, $crop, $scale, $flvName);
				}
				break;

			case 'MP4':
				foreach($this->ffmpeg_command['MPG'] as $command) {
					$ffmpeg_cmd = $this->ffmpeg . ' ' . $command;
					$ffmpeg_cmd = sprintf($ffmpeg_cmd, $srcFile, $crop, $scale, $flvName);
					$this->executeCommand($ffmpeg_cmd);
					if($this->checkConversion($videoInfo)) {
						break;
					}
				}

				break;

			case 'MOD': //really MPG from JVC cameras
			case 'F4V': // Flash MPG4 - BATS system
			case 'M4V':
			case 'MPG':
			case 'MPEG':
			case 'VOB': //DVD files
				$ffmpeg_cmd = $this->ffmpeg . ' ' . $this->ffmpeg_command['MOD'];
				$ffmpeg_cmd = sprintf($ffmpeg_cmd, $srcFile, $scrop, $scale, $flvName);
				$this->executeCommand($ffmpeg_cmd);
				break;
			case 'MSWMM':  //windows movie maker
			case 'PNG':
			case 'JPG':
			case 'BMP':
			case '':
			default:
		}

		$flvInfo = new MediaInfoDriver($flvName);

		return $flvInfo->getVideoInfo();
	}

	public function makeFrameImageThumbnails(VideoInfo $videoInfo)
	{

	}

	public function makeFrameImages(VideoInfo $videoInfo)
	{

	}

	public function makeThumbnail(VideoInfo $videoInfo)
	{

	}

	protected function _scaleVideo(VideoInfo $videoInfo)
	{
		$scale = '';
		$vHeight = (int) floor($videoInfo->getHeight());
		$vWidth = (int) floor($videoInfo->getWidth());
		$vAspect = $vWidth / $vHeight;

		$rotation = (int) $videoInfo->getRotation();

		if($rotation == 90 || $rotation == 270) {
			$width = $vHeight;
			$vHeight = $vWidth;
			$vWidth = $width;
			$vAspect = 1 / $vAspect;
		}

		if($vHeight > 480) {
			$newHeight = 480;
			$newWidth = (int) floor($newHeight * $vAspect);
			if($newWidth % 2) {
				$newWidth++;
			}

			$scale = '-s ' . $newWidth . 'x' . $newHeight;
		}

		return $scale;
	}

}
