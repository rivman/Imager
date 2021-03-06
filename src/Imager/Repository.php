<?php
/**
 * @package Imager
 * @author Ladislav Vondráček <lad.von@gmail.com>
 */

namespace Imager;

use Nette;
use Nette\Utils\FileSystem;
use Nette\Utils\Strings;

class Repository
{

  /** @var string */
  private $sourcesDirectory;

  /** @var null|string */
  private $thumbnailsDirectory;


  /**
   * @param string $sourcesDirectory
   * @param null|string $thumbnailsDirectory
   * @throws \Imager\NotExistsException
   * @throws \Imager\BadPermissionException
   */
  public function __construct($sourcesDirectory, $thumbnailsDirectory = null)
  {
    $this->setSourcesDirectory($sourcesDirectory);

    // if not thumbnails directory defined, then directory of sources is together thumbnails directory
    $thumbnailsDirectory = $thumbnailsDirectory ?: $sourcesDirectory;
    $this->setThumbnailsDirectory($thumbnailsDirectory);
  }


  /**
   * Returns instance of ImageInfo about fetched image
   *
   * @param string $name
   * @return \Imager\ImageInfo
   * @throws \Imager\InvalidArgumentException
   * @throws \Imager\NotExistsException
   */
  public function fetch($name)
  {
    if (!isset($name) || Strings::length($name) === 0) {
      throw new InvalidArgumentException('Name of fetched file cannot be empty.');
    }

    $source = $this->getSourcePath($name);

    if (!file_exists($source)) {
      $msg = sprintf('Source image "%s" not exists.', $source);
      throw new NotExistsException($msg);
    }

    return new ImageInfo($source);
  }


  /**
   * Returns instance of ImageInfo about fetched thumbnail
   *
   * @param string $name
   * @return \Imager\ImageInfo
   */
  public function fetchThumbnail($name)
  {
    if (!isset($name) || Strings::length($name) === 0) {
      throw new InvalidArgumentException('Name of fetched file cannot be empty.');
    }

    $thumbnail = $this->getThumbnailPath($name);

    if (!file_exists($thumbnail)) {
      $msg = sprintf('Thumbnail image "%s" not exists.', $thumbnail);
      throw new NotExistsException($msg);
    }

    $parts = Helpers::parseName($name);
    if (isset($parts['id'])) {
      $source = $this->fetch($parts['id']);
    }

    return new ImageInfo($thumbnail, $source);
  }


  /**
   * Save (copy and remove) image to new image
   *
   * @param \Imager\ImageInfo $image
   * @param null|string $name
   * @return \Imager\ImageInfo
   */
  public function save(ImageInfo $image, $name = null)
  {
    // thumbnail has source
    if ($image->hasSource()) {
      $imageInfo = $this->saveThumbnail($image, $name);
    } else {
      $imageInfo = $this->saveSource($image, $name);
    }

    return $imageInfo;
  }


  /**
   * Save (copy and remove) image to new image in source directory
   *
   * @param \Imager\ImageInfo $image
   * @param null|string $name
   * @return \Imager\ImageInfo
   */
  public function saveSource(ImageInfo $image, $name = null)
  {
    $name = $name ?: Helpers::createName($image);
    $target = $this->getSourcePath($name);

    return $this->moveImage($image, $target);
  }


  /**
   * Save (copy and remove) image to new image in target directory
   *
   * @param \Imager\ImageInfo $image
   * @param null|string $name
   * @return \Imager\ImageInfo
   */
  public function saveThumbnail(ImageInfo $image, $name = null)
  {
    $name = $name ?: Helpers::createName($image);
    $target = $this->getThumbnailPath($name);

    return $this->moveImage($image, $target);
  }


  /**
   * Sets directory with sources images
   *
   * @param string $sourcesDirectory
   * @throws \Imager\NotExistsException
   */
  private function setSourcesDirectory($sourcesDirectory)
  {
    $sourcesDirectory = Strings::trim($sourcesDirectory);
    $sourcesDirectory = rtrim($sourcesDirectory, DIRECTORY_SEPARATOR);

    if (!is_dir($sourcesDirectory)) {
      try {
        FileSystem::createDir($sourcesDirectory);
      } catch (Nette\IOException $e) {
        $msg = sprintf('Directory "%s" with sources not exists and cannot be create.', $sourcesDirectory);
        throw new NotExistsException($msg, 0, $e);
      }
    }

    if (!is_writable($sourcesDirectory)) {
      $msg = sprintf('Directory "%s" with sources is not writable.', $sourcesDirectory);
      throw new BadPermissionException($msg);
    }

    $this->sourcesDirectory = $sourcesDirectory . DIRECTORY_SEPARATOR;
  }


  /**
   * Sets directory with thumbnails
   *
   * @param string $thumbnailsDirectory
   * @throws \Imager\NotExistsException
   * @throws \Imager\BadPermissionException
   */
  private function setThumbnailsDirectory($thumbnailsDirectory)
  {
    $thumbnailsDirectory = Strings::trim($thumbnailsDirectory);
    $thumbnailsDirectory = rtrim($thumbnailsDirectory, DIRECTORY_SEPARATOR);

    if (!is_dir($thumbnailsDirectory)) {
      try {
        FileSystem::createDir($thumbnailsDirectory);
      } catch (Nette\IOException $e) {
        $msg = sprintf('Directory "%s" with thumbnails not exists and cannot be create.', $thumbnailsDirectory);
        throw new NotExistsException($msg, 0, $e);
      }
    }

    if (!is_writable($thumbnailsDirectory)) {
      $msg = sprintf('Directory "%s" with thumbnails is not writable.', $thumbnailsDirectory);
      throw new BadPermissionException($msg);
    }

    $this->thumbnailsDirectory = $thumbnailsDirectory . DIRECTORY_SEPARATOR;
  }


  /**
   * Moves image to target
   *
   * @param \Imager\ImageInfo $image
   * @param string $target
   * @return \Imager\ImageInfo
   */
  private function moveImage(ImageInfo $image, $target)
  {
    FileSystem::rename($image->getPathname(), $target);
    chmod($target, 0666);

    return new ImageInfo($target);
  }


  /**
   * Returns path for source image
   *
   * @param string $name
   * @return string
   */
  private function getSourcePath($name)
  {
    return $this->sourcesDirectory . Helpers::getSubPath($name) . $name;
  }


  /**
   * Returns path for thumbnail of image
   *
   * @param string $name
   * @return string
   */
  private function getThumbnailPath($name)
  {
    return $this->thumbnailsDirectory . Helpers::getSubPath($name) . $name;
  }

}
