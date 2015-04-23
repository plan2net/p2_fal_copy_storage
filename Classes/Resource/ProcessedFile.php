<?php

namespace Plan2net\P2FalCopyStorage\Resource;

use TYPO3\CMS\Core\Utility\PathUtility;

/**
 * Class ProcessedFile
 *
 * @package Plan2net\P2FalCopyStorage\Resource
 */
class ProcessedFile extends \TYPO3\CMS\Core\Resource\ProcessedFile {

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
