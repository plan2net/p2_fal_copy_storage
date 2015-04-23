<?php

namespace Plan2net\P2FalCopyStorage\Resource;

use TYPO3\CMS\Core\Utility\PathUtility;

/**
 * Class File
 *
 * @package Plan2net\P2FalCopyStorage\Resource
 */
class File extends \TYPO3\CMS\Core\Resource\File {

	/**
	 * @param \TYPO3\CMS\Core\Resource\Folder $targetFolder
	 * @param null                            $targetFileName
	 * @param string                          $conflictMode
	 * @param bool                            $removeOriginal
	 *
	 * @return \TYPO3\CMS\Core\Resource\FileInterface
	 */
	public function moveTo(\TYPO3\CMS\Core\Resource\Folder $targetFolder, $targetFileName = NULL, $conflictMode = 'renameNewFile', $removeOriginal = TRUE) {
		if ($this->deleted) {
			throw new \RuntimeException('File has been deleted.', 1329821484);
		}
		return $targetFolder->getStorage()->moveFile($this, $targetFolder, $targetFileName, $conflictMode, $removeOriginal);
	}

}

