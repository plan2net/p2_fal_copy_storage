<?php

namespace Plan2net\P2FalCopyStorage\Resource;

use TYPO3\CMS\Core\Utility\PathUtility;

/**
 * Class Folder
 *
 * @package Plan2net\P2FalCopyStorage\Resource
 */
class Folder extends \TYPO3\CMS\Core\Resource\Folder {

	/**
	 * @param Folder $targetFolder
	 * @param null   $targetFolderName
	 * @param string $conflictMode
	 * @param bool   $removeOriginal
	 *
	 * @return \TYPO3\CMS\Core\Resource\Folder
	 */
	public function moveTo(Folder $targetFolder, $targetFolderName = NULL, $conflictMode = 'renameNewFile', $removeOriginal = TRUE) {
		return $targetFolder->getStorage()->moveFolder($this, $targetFolder, $targetFolderName, $conflictMode, $removeOriginal);
	}

}

