<?php namespace PowerChalk\MediaConverter\VideoInfo\Driver;

/**
 *
 * @author chris
 */
interface iVideoInfoDriver
{
	public function __construct($filename);
	public function getVideoInfo();
}
