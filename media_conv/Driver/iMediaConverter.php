<?php namespace PowerChalk\MediaConverter\Driver;

/**
 *
 * @author chris
 */
interface iMediaConverter
{
	public function makeFlv();
	public function makeMp4();
	public function makeThumbnail();
	public function makeFrameImages();
	public function makeFrameThumbnails();
	public function makeZip($start, $duration);
	public function makeEdit($newFile, $inframe, $outframe, $width, $height, $x, $y);
}
