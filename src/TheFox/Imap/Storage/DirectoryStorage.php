<?php

namespace TheFox\Imap\Storage;

use SplFileInfo;
use Symfony\Component\Filesystem\Filesystem;
use TheFox\Imap\MsgDb;

class DirectoryStorage extends AbstractStorage
{
    /**
     * @return string
     */
    public function getDirectorySeperator(): string
    {
        return DIRECTORY_SEPARATOR;
    }

    /**
     * @param string $path
     */
    public function setPath(string $path)
    {
        parent::setPath($path);

        if (!file_exists($this->getPath())) {
            $filesystem = new Filesystem();
            $filesystem->mkdir($this->getPath(), 0755);
        }
    }

    /**
     * @param string $folder
     * @return bool
     */
    public function folderExists(string $folder): bool
    {
        $path = $this->genFolderPath($folder);
        return file_exists($path) && is_dir($path);
    }

    /**
     * @param string $folder
     * @return bool
     */
    public function createFolder(string $folder): bool
    {
        if (!$this->folderExists($folder)) {
            $path = $this->genFolderPath($folder);
            if (!file_exists($path)) {
                $filesystem = new Filesystem();
                $filesystem->mkdir($path, 0755);

                return file_exists($path);
            }
        }

        return false;
    }
    
    private function recursiveDirectorySearch(string $path, string $pattern, bool $recursive = false, int $level = 0){
        $folders = [];
        if (is_dir($path)) {
            if ($dirHandle = opendir($path)) {
                while (($fileName = readdir($dirHandle)) !== false) {
                    if ($fileName == '.' || $fileName == '..'){
                        continue;
                    }
                    
                    $dir = $path . DIRECTORY_SEPARATOR . $fileName;
                    
                    if (!is_dir($dir)){
                        continue;
                    }

                    if (fnmatch($pattern, $fileName)){
                        $folders[] = new SplFileInfo($dir);
                    }
                    
                    if ($recursive){
                        $recursiveFolders = $this->recursiveDirectorySearch($dir, $pattern, $recursive, $level + 1);
                        
                        // Append Folders.
                        $folders = array_merge($folders, $recursiveFolders);
                    }
                }
                closedir($dirHandle);
            }
        }
        return $folders;
    }

    public function getFolders(string $baseFolder, string $searchFolder, bool $recursive = false): array
    {
        $basePath = $this->genFolderPath($baseFolder);
        
        /** @var SplFileInfo[] $foundFolders */
        $foundFolders = $this->recursiveDirectorySearch($basePath, $searchFolder, $recursive);

        $folders = [];
        foreach ($foundFolders as $dir) {
            $folderPath = $dir->getPathname();
            $folderPath = substr($folderPath, $this->getPathLen());
            if ($folderPath[0] == '/') {
                $folderPath = substr($folderPath, 1);
            }
            
            $folders[] = $folderPath;
        }

        return $folders;
    }

    /**
     * @param string $folder
     * @param array $flags
     * @return int
     */
    public function getMailsCountByFolder(string $folder, array $flags = []): int
    {
        $path = $this->genFolderPath($folder);

        if ($flags) {
            /** @var MsgDb $db */
            $db = $this->getDb();

            if ($db) {
                $count = 0;

                if (is_dir($path)) {
                    if ($dirHandle = opendir($path)) {
                        while (($fileName = readdir($dirHandle)) !== false) {
                            $file = new SplFileInfo($path . DIRECTORY_SEPARATOR . $fileName);
                            if ($file->getExtension() == 'eml') {
                                $msgId = $db->getMsgIdByPath($file->getPathname());
                                if ($msgId) {
                                    $msgFlags = $db->getFlagsById($msgId);
                                    foreach ($flags as $flag) {
                                        if (in_array($flag, $msgFlags)) {
                                            $count++;
                                            break;
                                        }
                                    }
                                }
                            }
                        }
                        closedir($dirHandle);
                    }
                }

                return $count;
            }
        } else {
            $count = 0;

            if (is_dir($path)) {
                if ($dirHandle = opendir($path)) {
                    while (($fileName = readdir($dirHandle)) !== false) {
                        $file = new SplFileInfo($path . DIRECTORY_SEPARATOR . $fileName);
                        if ($file->getExtension() == 'eml') {
                            $count++;
                        }
                    }
                    closedir($dirHandle);
                }
            }

            return $count;
        }

        return 0;
    }

    /**
     * @param string $mailStr
     * @param string $folder
     * @param array|null $flags
     * @param bool $recent
     * @return int
     */
    public function addMail(string $mailStr, string $folder, array $flags = null, bool $recent = true): int
    {
        $msgId = 0;

        $path = $this->genFolderPath($folder);
        $fileName = 'mail_' . sprintf('%.32f', microtime(true)) . '_' . mt_rand(100000, 999999) . '.eml';
        $filePath = $path . '/' . $fileName;

        /** @var MsgDb $db */
        $db = $this->getDb();

        if ($db) {
            $msgId = $db->addMsg($filePath, $flags, $recent);

            $fileName = 'mail_' . sprintf('%032d', $msgId) . '.eml';
            $filePath = $path . '/' . $fileName;

            $db->setPathById($msgId, $filePath);
        }

        file_put_contents($filePath, $mailStr);

        return $msgId;
    }

    /**
     * @param int $msgId
     */
    public function removeMail(int $msgId)
    {
        /** @var MsgDb $db */
        $db = $this->getDb();

        if ($db) {
            $msg = $db->removeMsg($msgId);

            $filesystem = new Filesystem();
            $filesystem->remove($msg['path']);
        }
    }

    /**
     * @param int $msgId
     * @param string $folder
     */
    public function copyMailById(int $msgId, string $folder)
    {
        /** @var MsgDb $db */
        $db = $this->getDb();

        if ($db) {
            $msg = $db->getMsgById($msgId);
            if ($msg && file_exists($msg['path'])) {
                //$pathinfo = pathinfo($msg['path']);
                //$dstFolder = $this->genFolderPath($folder);
                //$dstFile = $dstFolder . '/' . $pathinfo['basename'];
                $mailStr = file_get_contents($msg['path']);
                $this->addMail($mailStr, $folder);
            }
        }
    }

    /**
     * @param int $seqNum
     * @param string $folder
     * @param string $dstFolder
     */
    public function copyMailBySequenceNum(int $seqNum, string $folder, string $dstFolder)
    {
        $msgId = $this->getMsgIdBySeq($seqNum, $folder);
        if ($msgId) {
            $this->copyMailById($msgId, $dstFolder);
        }
    }

    /**
     * @param int $msgId
     * @return string
     */
    public function getPlainMailById(int $msgId): string
    {
        /** @var MsgDb $db */
        $db = $this->getDb();

        if (!$db) {
            return '';
        }

        $msg = $db->getMsgById($msgId);
        if (!$msg) {
            return '';
        }

        if (!file_exists($msg['path'])) {
            return '';
        }

        try {
            $content = file_get_contents($msg['path']);
        } catch (\Error $e) {
            return '';
        } catch (\Exception $e) {
            return '';
        }

        if ($content === false) {
            return '';
        }

        return $content;
    }

    /**
     * @param int $msgId
     * @return int
     */
    public function getMsgSeqById(int $msgId): int
    {
        /** @var MsgDb $db */
        $db = $this->getDb();

        if ($db) {
            $msg = $db->getMsgById($msgId);
            if ($msg) {

                $pathinfo = pathinfo($msg['path']);
                if (isset($pathinfo['dirname']) && isset($pathinfo['basename'])) {
                    $seq = 0;

                    $path = $pathinfo['dirname'];
                    if (is_dir($path)) {
                        if ($dirHandle = opendir($path)) {
                            while (($fileName = readdir($dirHandle)) !== false) {
                                $file = new SplFileInfo($path . DIRECTORY_SEPARATOR . $fileName);
                                if ($file->getExtension() == 'eml') {
                                    $seq++;

                                    if ($file->getFilename() == $pathinfo['basename']) {
                                        break;
                                    }
                                }
                            }
                            closedir($dirHandle);
                        }
                    }

                    return $seq;
                }
            }
        }

        return 0;
    }

    /**
     * @param int $seqNum
     * @param string $folder
     * @return int
     */
    public function getMsgIdBySeq(int $seqNum, string $folder): int
    {
        /** @var MsgDb $db */
        $db = $this->getDb();

        if ($db) {
            $path = $this->genFolderPath($folder);

            $seq = 0;

            if (is_dir($path)) {
                if ($dirHandle = opendir($path)) {
                    while (($fileName = readdir($dirHandle)) !== false) {
                        $file = new SplFileInfo($path . DIRECTORY_SEPARATOR . $fileName);
                        if ($file->getExtension() == 'eml') {
                            $seq++;

                            if ($seq >= $seqNum) {
                                $finder = null;
                                $files = null;

                                return $db->getMsgIdByPath($file->getPathname());
                            }
                        }
                    }
                    closedir($dirHandle);
                }
            }
        }

        return 0;
    }

    /**
     * @param array $flags
     * @return array
     */
    public function getMsgsByFlags(array $flags): array
    {
        /** @var MsgDb $db */
        $db = $this->getDb();

        if ($db) {
            return $db->getMsgIdsByFlags($flags);
        }

        return [];
    }

    /**
     * @param int $msgId
     * @return array
     */
    public function getFlagsById(int $msgId): array
    {
        /** @var MsgDb $db */
        $db = $this->getDb();

        if ($db) {
            return $db->getFlagsById($msgId);
        }

        return [];
    }

    /**
     * @param int $msgId
     * @param array $flags
     */
    public function setFlagsById(int $msgId, array $flags)
    {
        /** @var MsgDb $db */
        $db = $this->getDb();

        if ($db) {
            $db->setFlagsById($msgId, $flags);
        }
    }

    /**
     * @param int $seqNum
     * @param string $folder
     * @return array
     */
    public function getFlagsBySeq(int $seqNum, string $folder): array
    {
        /** @var MsgDb $db */
        $db = $this->getDb();

        if ($db) {
            $path = $this->genFolderPath($folder);

            $seq = 0;

            if (is_dir($path)) {
                if ($dirHandle = opendir($path)) {
                    while (($fileName = readdir($dirHandle)) !== false) {
                        $file = new SplFileInfo($path . DIRECTORY_SEPARATOR . $fileName);
                        if ($file->getExtension() == 'eml') {
                            $seq++;

                            if ($seq >= $seqNum) {
                                $msgId = $db->getMsgIdByPath($file->getPathname());

                                return $this->getFlagsById($msgId);
                            }
                        }
                    }
                    closedir($dirHandle);
                }
            }
        }

        return [];
    }

    /**
     * @param int $seqNum
     * @param string $folder
     * @param array $flags
     */
    public function setFlagsBySeq(int $seqNum, string $folder, array $flags)
    {
        /** @var MsgDb $db */
        $db = $this->getDb();

        if ($db) {
            $msgId = $this->getMsgIdBySeq($seqNum, $folder);
            if ($msgId) {
                $db->setFlagsById($msgId, $flags);
            }
        }
    }

    /**
     * @return int
     */
    public function getNextMsgId(): int
    {
        /** @var MsgDb $db */
        $db = $this->getDb();

        if ($db) {
            return $db->getNextId();
        }

        return 0;
    }
}
