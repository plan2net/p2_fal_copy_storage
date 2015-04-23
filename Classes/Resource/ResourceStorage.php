<?php

namespace Plan2net\P2FalCopyStorage\Resource;

use TYPO3\CMS\Core\Resource\Index\FileIndexRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\PathUtility;

/**
 * Class ResourceStorage
 *
 * @package Plan2net\P2FalCopyStorage\Resource
 */
class ResourceStorage extends \TYPO3\CMS\Core\Resource\ResourceStorage {

	/**
	 * @param \TYPO3\CMS\Core\Resource\FileInterface $file
	 * @param Folder                                 $targetFolder
	 * @param null                                   $targetFileName
	 * @param string                                 $conflictMode
	 *
	 * @return NULL|\TYPO3\CMS\Core\Resource\File|\TYPO3\CMS\Core\Resource\ProcessedFile
	 * @throws \TYPO3\CMS\Core\Resource\Exception
	 * @throws \TYPO3\CMS\Core\Resource\Exception\ExistingTargetFileNameException
	 * @throws \TYPO3\CMS\Core\Resource\Exception\IllegalFileExtensionException
	 * @throws \TYPO3\CMS\Core\Resource\Exception\InsufficientFileReadPermissionsException
	 * @throws \TYPO3\CMS\Core\Resource\Exception\InsufficientFolderWritePermissionsException
	 */
	public function copyFile(\TYPO3\CMS\Core\Resource\FileInterface $file, Folder $targetFolder, $targetFileName = NULL, $conflictMode = 'renameNewFile') {
		if ($targetFileName === NULL) {
			$targetFileName = $file->getName();
		}
		$sanitizedTargetFileName = $this->driver->sanitizeFileName($targetFileName);
		$this->assureFileCopyPermissions($file, $targetFolder, $sanitizedTargetFileName);
		$this->emitPreFileCopySignal($file, $targetFolder);
		// File exists and we should abort, let's abort
		if ($conflictMode === 'cancel' && $targetFolder->hasFile($sanitizedTargetFileName)) {
			throw new \TYPO3\CMS\Core\Resource\Exception\ExistingTargetFileNameException('The target file already exists.', 1320291064);
		}
		// File exists and we should find another name, let's find another one
		if ($conflictMode === 'renameNewFile' && $targetFolder->hasFile($sanitizedTargetFileName)) {
			$sanitizedTargetFileName = $this->getUniqueName($targetFolder, $sanitizedTargetFileName);
		}
		$sourceStorage = $file->getStorage();
		// Call driver method to create a new file from an existing file object,
		// and return the new file object
		if ($sourceStorage === $this) {
			$newIdentifier = $this->driver->copyFileWithinStorage($file->getIdentifier(), $targetFolder->getIdentifier(), $sanitizedTargetFileName);
		} else {
			$newIdentifier = $this->driver->addFile(
				$file->getForLocalProcessing(),
				$targetFolder->getIdentifier(),
				$sanitizedTargetFileName
			);
		}
		$newFileObject = \TYPO3\CMS\Core\Resource\ResourceFactory::getInstance()->getFileObjectByStorageAndIdentifier($this->getUid(), $newIdentifier);
		$this->emitPostFileCopySignal($file, $targetFolder);
		return $newFileObject;
	}

	/**
	 * @param \TYPO3\CMS\Core\Resource\FileInterface $file
	 * @param \TYPO3\CMS\Core\Resource\Folder        $targetFolder
	 * @param null                                   $targetFileName
	 * @param string                                 $conflictMode
	 * @param bool                                   $removeOriginal
	 *
	 * @return \TYPO3\CMS\Core\Resource\FileInterface
	 * @throws \TYPO3\CMS\Core\Resource\Exception\ExistingTargetFileNameException
	 * @throws \TYPO3\CMS\Core\Resource\Exception\IllegalFileExtensionException
	 * @throws \TYPO3\CMS\Core\Resource\Exception\InsufficientFolderAccessPermissionsException
	 * @throws \TYPO3\CMS\Core\Resource\Exception\InsufficientUserPermissionsException
	 */
	public function moveFile($file, $targetFolder, $targetFileName = NULL, $conflictMode = 'renameNewFile', $removeOriginal = TRUE) {
		if ($targetFileName === NULL) {
			$targetFileName = $file->getName();
		}
		$originalFolder = $file->getParentFolder();
		$sanitizedTargetFileName = $this->driver->sanitizeFileName($targetFileName);
		$this->assureFileMovePermissions($file, $targetFolder, $sanitizedTargetFileName);
		if ($targetFolder->hasFile($sanitizedTargetFileName)) {
			// File exists and we should abort, let's abort
			if ($conflictMode === 'renameNewFile') {
				$sanitizedTargetFileName = $this->getUniqueName($targetFolder, $sanitizedTargetFileName);
			} elseif ($conflictMode === 'cancel') {
				throw new \TYPO3\CMS\Core\Resource\Exception\ExistingTargetFileNameException('The target file already exists', 1329850997);
			}
		}
		$this->emitPreFileMoveSignal($file, $targetFolder);
		$sourceStorage = $file->getStorage();
		// Call driver method to move the file and update the index entry
		if (!$file instanceof \TYPO3\CMS\Core\Resource\AbstractFile) {
			throw new \RuntimeException('The given file is not of type AbstractFile.', 1384209025);
		}
		if ($sourceStorage === $this) {
			if ($removeOriginal) {
				$newIdentifier = $this->driver->moveFileWithinStorage($file->getIdentifier(), $targetFolder->getIdentifier(), $sanitizedTargetFileName);
			} else {
				$newIdentifier = $this->driver->copyFileWithinStorage($file->getIdentifier(), $targetFolder->getIdentifier(), $sanitizedTargetFileName);
			}
		} else {
			$newIdentifier = $this->driver->addFile(
				$file->getForLocalProcessing(FALSE),
				$targetFolder->getIdentifier(),
				$sanitizedTargetFileName,
				FALSE
			);
			if ($removeOriginal && !($file->getStorage() === $this && $newIdentifier == $file->getIdentifier())) {
				$file->getStorage()->getDriver()->deleteFile($file->getIdentifier());
			}
		}
		$file->updateProperties(array('identifier' => $newIdentifier, 'storage' => $this->getUid()));
		$this->getIndexer()->updateIndexEntry($file);
		$this->emitPostFileMoveSignal($file, $targetFolder, $originalFolder);
		return $file;
	}

	/**
	 * Moves a folder. If you want to move a folder from this storage to another
	 * one, call this method on the target storage, otherwise you will get an exception.
	 *
	 * @param Folder $folderToMove The folder to move.
	 * @param Folder $targetParentFolder The target parent folder
	 * @param string $newFolderName
	 * @param string $conflictMode @see findTargetFolder()
	 * @param bool $removeOriginal Whether to remove the source folder "physically" (on the driver)
	 * @throws \Exception|\TYPO3\CMS\Core\Exception
	 * @throws \InvalidArgumentException
	 * @return Folder
	 */
	public function moveFolder(Folder $folderToMove, Folder $targetParentFolder, $newFolderName = NULL, $conflictMode = 'integrate', $removeOriginal = TRUE) {
		$this->assureFolderMovePermissions($folderToMove, $targetParentFolder);
		$targetFolder = $this->findTargetFolder($folderToMove, $targetParentFolder, $newFolderName, $conflictMode, !$removeOriginal);
		$this->emitPreFolderMoveSignal($folderToMove, $targetParentFolder, $targetFolder);
		if (is_string($targetFolder)) {
			$fileMappings = $this->driver->moveFolderWithinStorage($folderToMove->getIdentifier(), $targetParentFolder->getIdentifier(), $targetFolder);
			$resourceFactory = \TYPO3\CMS\Core\Resource\ResourceFactory::getInstance();
			$sourceStorageUid = $folderToMove->getStorage()->getUid();
			foreach ($fileMappings as $oldIdentifier => $newIdentifier) {
				try {
					$fileObject = $resourceFactory->getFileObjectByStorageAndIdentifier($sourceStorageUid, $oldIdentifier);
				} catch (\InvalidArgumentException $e) {
					// This is likely an old folder, that can not be found after it's moved
					// Could check if folder with $newIdentifier exists, but this would slow down
					// the process significantly
					continue;
				}
				$fileObject->updateProperties(array('storage' => $this->getUid(), 'identifier' => $newIdentifier));
				$this->getIndexer()->updateIndexEntry($fileObject);
			}
			$targetFolder = $this->getFolder($fileMappings[$folderToMove->getIdentifier()]);
		} else {
			$this->integrateFolder($folderToMove, $targetFolder, $conflictMode, 'move', array($removeOriginal));
			if ($removeOriginal) {
				$folderToMove->delete();
			}
		}
		$folderToMove->updateProperties(array(
			                                'identifier' => $targetFolder->getIdentifier(),
			                                'name' => $targetFolder->getName(),
			                                'storage' => $this
		                                ));
		$this->emitPostFolderMoveSignal($folderToMove, $targetParentFolder, $targetFolder->getName(), $targetFolder);
		return $targetFolder;
	}

	/**
	 * @param \TYPO3\CMS\Core\Resource\FolderInterface $folderToCopy
	 * @param \TYPO3\CMS\Core\Resource\FolderInterface $targetParentFolder
	 * @param null                                     $newFolderName
	 * @param string                                   $conflictMode
	 *
	 * @return string|\TYPO3\CMS\Core\Resource\Folder
	 * @throws \Exception
	 * @throws \TYPO3\CMS\Core\Resource\Exception
	 * @throws \TYPO3\CMS\Core\Resource\Exception\ExistingTargetFolderException
	 * @throws \TYPO3\CMS\Core\Resource\Exception\InsufficientFileReadPermissionsException
	 * @throws \TYPO3\CMS\Core\Resource\Exception\InsufficientFolderAccessPermissionsException
	 * @throws \TYPO3\CMS\Core\Resource\Exception\InsufficientFolderWritePermissionsException
	 * @throws \TYPO3\CMS\Core\Resource\Exception\InvalidFolderException
	 */
	public function copyFolder(\TYPO3\CMS\Core\Resource\FolderInterface $folderToCopy, \TYPO3\CMS\Core\Resource\FolderInterface $targetParentFolder, $newFolderName = NULL, $conflictMode = 'integrate') {
		$this->assureFolderCopyPermissions($folderToCopy, $targetParentFolder);
		$targetFolder = $this->findTargetFolder($folderToCopy, $targetParentFolder, $newFolderName, $conflictMode);
		$this->emitPreFolderCopySignal($folderToCopy, $targetParentFolder, $targetFolder);
		if (is_string($targetFolder)) {
			$this->driver->copyFolderWithinStorage($folderToCopy->getIdentifier(), $targetParentFolder->getIdentifier(), $targetFolder);
			$targetFolder = $this->getFolder($targetParentFolder->getSubfolder($targetFolder)->getIdentifier());
		} else {
			$this->integrateFolder($folderToCopy, $targetFolder, $conflictMode, 'copy');
		}
		$this->emitPostFolderCopySignal($folderToCopy, $targetParentFolder, $targetFolder->getName());
		return $targetFolder;
	}

	/**
	 * @param Folder $sourceFolder
	 * @param Folder $targetParentFolder
	 * @param        $newFolderName
	 * @param        $conflictMode
	 * @param bool   $forceOutsideStorage
	 *
	 * @return string|\TYPO3\CMS\Core\Resource\Folder
	 * @throws \TYPO3\CMS\Core\Resource\Exception\ExistingTargetFolderException
	 * @throws \TYPO3\CMS\Core\Resource\Exception\InvalidFolderException
	 */
	protected function findTargetFolder(Folder $sourceFolder, Folder $targetParentFolder, $newFolderName, $conflictMode, $forceOutsideStorage = FALSE) {
		$newFolderName = $newFolderName ?: $sourceFolder->getName();
		if (!$newFolderName) {
			if ($conflictMode == 'renameNewFile' || $conflictMode == 'integrate') {
				if ($sourceFolder->getStorage() === $this && $sourceFolder->getIdentifier() == $targetParentFolder->getIdentifier()) {
					throw new \TYPO3\CMS\Core\Resource\Exception\InvalidFolderException('Source and target folder are identical', 1415783598);
				}
				return $targetParentFolder;
			} else {
				throw new \RuntimeException('Invalid source folder given (empty name)', 1415720396);
			}
		}
		$sanitizedFolderName = $this->driver->sanitizeFileName($newFolderName);
		$targetFolderExists = $targetParentFolder->hasFolder($sanitizedFolderName);
		$handleInsideStorage = !$forceOutsideStorage && $sourceFolder->getStorage() === $this;
		if ($targetFolderExists) {
			switch ($conflictMode) {
				case 'renameNewFolder':
					$sanitizedFolderName = $this->getUniqueName($targetParentFolder, $sanitizedFolderName);
					$targetFolderExists = FALSE;
					break;
				case 'renameNewFile':
				case 'integrate':
					$handleInsideStorage = FALSE;
					break;
				case 'cancel':
					throw new \TYPO3\CMS\Core\Resource\Exception\ExistingTargetFolderException('The target folder already exists', 1414587050);
				default:
					throw new \RuntimeException('Unsupported conflict mode', 1414587048);
			}
		}
		if ($handleInsideStorage) {
			$targetFolder = $sanitizedFolderName;
		} elseif ($targetFolderExists) {
			$targetFolder = $targetParentFolder->getSubfolder($sanitizedFolderName);
		} else {
			$targetFolder = $targetParentFolder->createFolder($sanitizedFolderName);
		}
		return $targetFolder;
	}
	/**
	 * Create all subfolders of source folder in target folder and either move or
	 * copy the files
	 *
	 * @param Folder $sourceFolder
	 * @param Folder $targetFolder
	 * @param string $fileConflictMode @see moveFile() and copyFile()
	 * @param string $fileAction What to do with the files ('copy' or 'move')
	 * @param array $fileActionArgs Additional arguments for the file action function
	 */
	protected function integrateFolder(Folder $sourceFolder, Folder $targetFolder, $fileConflictMode, $fileAction, array $fileActionArgs = array()) {
		$sourceFolderQueue = array($sourceFolder);
		$targetFolderQueue = array($targetFolder);
		while (!empty($sourceFolderQueue)) {
			$sourceFolder = array_shift($sourceFolderQueue); /* @var $sourceFolder Folder */
			$targetFolder = array_shift($targetFolderQueue); /* @var $targetFolder Folder */
			foreach ($sourceFolder->getSubfolders() as $subFolder) {
				$sourceFolderQueue[] = $subFolder;
				if ($targetFolder->hasFolder($subFolder->getName())) {
					$targetFolderQueue[] = $targetFolder->getSubfolder($subFolder->getName());
				} else {
					$targetFolderQueue[] = $targetFolder->createFolder($subFolder->getName());
				}
			}
			foreach ($sourceFolder->getFiles() as $file) {
				call_user_func_array(
					array($this, $fileAction . 'File'),
					array_merge(array($file, $targetFolder, null, $fileConflictMode), $fileActionArgs)
				);
			}
		}
	}

	/**
	 * @param \TYPO3\CMS\Core\Resource\Folder $folderObject
	 * @param string                          $newName
	 *
	 * @return \TYPO3\CMS\Core\Resource\Folder
	 * @throws \TYPO3\CMS\Core\Resource\Exception\InsufficientUserPermissionsException
	 * @throws \Exception
	 * @throws \TYPO3\CMS\Core\Resource\Exception\InsufficientFolderAccessPermissionsException
	 */
	public function renameFolder($folderObject, $newName) {
		// Renaming the folder should check if the parent folder is writable
		// We cannot do this however because we cannot extract the parent folder from a folder currently
		if (!$this->checkFolderActionPermission('rename', $folderObject)) {
			throw new \TYPO3\CMS\Core\Resource\Exception\InsufficientUserPermissionsException('You are not allowed to rename the folder "' . $folderObject->getIdentifier() . '\'', 1357811441);
		}
		$sanitizedNewName = $this->driver->sanitizeFileName($newName);
		if ($this->driver->folderExistsInFolder($sanitizedNewName, $folderObject->getIdentifier())) {
			throw new \InvalidArgumentException('The folder ' . $sanitizedNewName . ' already exists in folder ' . $folderObject->getIdentifier(), 1325418870);
		}
		$this->emitPreFolderRenameSignal($folderObject, $sanitizedNewName);
		$fileObjects = $this->getAllFileObjectsInFolder($folderObject);
		$fileMappings = $this->driver->renameFolder($folderObject->getIdentifier(), $sanitizedNewName);
		// Update the identifier of all file objects
		foreach ($fileObjects as $oldIdentifier => $fileObject) {
			$newIdentifier = $fileMappings[$oldIdentifier];
			$fileObject->updateProperties(array('identifier' => $newIdentifier));
			$this->getIndexer()->updateIndexEntry($fileObject);
		}
		$returnObject = $this->getFolder($fileMappings[$folderObject->getIdentifier()]);
		$this->emitPostFolderRenameSignal($folderObject, $returnObject->getName());
		return $returnObject;
	}

	/**
	 * @param Folder $folder
	 * @param string $theFile
	 * @param bool   $dontCheckForUnique
	 *
	 * @return string
	 */
	protected function getUniqueName(Folder $folder, $theFile, $dontCheckForUnique = FALSE) {
		static $maxNumber = 99, $uniqueNamePrefix = '';
		// Fetches info about path, name, extention of $theFile
		$origFileInfo = GeneralUtility::split_fileref($theFile);
		// Adds prefix
		if ($uniqueNamePrefix) {
			$origFileInfo['file'] = $uniqueNamePrefix . $origFileInfo['file'];
			$origFileInfo['filebody'] = $uniqueNamePrefix . $origFileInfo['filebody'];
		}
		// Check if the file exists and if not - return the fileName...
		$fileInfo = $origFileInfo;
		// The destinations file
		$theDestFile = $fileInfo['file'];
		// If the file does NOT exist we return this fileName
		if ($dontCheckForUnique ||
			(!$this->driver->fileExistsInFolder($theDestFile, $folder->getIdentifier()) &&
				!$this->driver->folderExistsInFolder($theDestFile, $folder->getIdentifier()))
		) {
			return $theDestFile;
		}
		// Well the fileName in its pure form existed. Now we try to append
		// numbers / unique-strings and see if we can find an available fileName
		// This removes _xx if appended to the file
		$theTempFileBody = preg_replace('/_[0-9][0-9]$/', '', $origFileInfo['filebody']);
		$theOrigExt = $origFileInfo['realFileext'] ? '.' . $origFileInfo['realFileext'] : '';
		for ($a = 1; $a <= $maxNumber + 1; $a++) {
			// First we try to append numbers
			if ($a <= $maxNumber) {
				$insert = '_' . sprintf('%02d', $a);
			} else {
				$insert = '_' . substr(md5(uniqid('', TRUE)), 0, 6);
			}
			$theTestFile = $theTempFileBody . $insert . $theOrigExt;
			// The destinations file
			$theDestFile = $theTestFile;
			// If the file does NOT exist we return this fileName
			if (!$this->driver->fileExistsInFolder($theDestFile, $folder->getIdentifier())) {
				return $theDestFile;
			}
		}
		throw new \RuntimeException('Last possible name "' . $theDestFile . '" is already taken.', 1325194291);
	}
}

