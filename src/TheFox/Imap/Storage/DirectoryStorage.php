<?php

namespace TheFox\Imap\Storage;

use Symfony\Component\Finder\Finder;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\SplFileInfo;
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

    /**
     * @param string $baseFolder
     * @param string $searchFolder
     * @param bool $recursive
     * @return array
     */
    public function getFolders(string $baseFolder, string $searchFolder, bool $recursive = false): array
    {
        $path = $this->genFolderPath($baseFolder);

        $finder = new Finder();

        if ($recursive) {
            /** @var SplFileInfo[] $files */
            $files = $finder->in($path)->directories()->name($searchFolder)->sortByName();
        } else {
            /** @var SplFileInfo[] $files */
            $files = $finder->in($path)->directories()->depth(0)->name($searchFolder)->sortByName();
        }

        $folders = [];
        foreach ($files as $file) {
            $folderPath = $file->getPathname();
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
     * @return bool
     */
    public function folderExists(string $folder): bool
    {
        $path = $this->genFolderPath($folder);
        return file_exists($path) && is_dir($path);
    }

    /**
     * @param string $folder
     * @param array|null $flags
     * @return int
     */
    public function getMailsCountByFolder(string $folder, array $flags = null): int
    {
        $path = $this->genFolderPath($folder);
        
        $finder = new Finder();
        
        /** @var SplFileInfo[] $files */
        $files = $finder->in($path)->files()->depth(0)->name('*.eml')->sortByName();
        
        if ($flags === null) {
            return count($files);
        } else {
            if ($this->getDb()) {
                $rv = 0;
                foreach ($files as $fileId => $file) {
                    $msgId = $this->getDb()->getMsgIdByPath($file->getPathname());
                    if ($msgId) {
                        $msgFlags = $this->getDb()->getFlagsById($msgId);
                        foreach ($flags as $flag) {
                            if (in_array($flag, $msgFlags)) {
                                $rv++;
                                break;
                            }
                        }
                    }
                }
                return $rv;
            }
        }
        return 0;
    }

    /**
     * @param string $mailStr
     * @param string $folder
     * @param array $flags
     * @param bool $recent
     * @return int
     */
    public function addMail(string $mailStr, string $folder, array $flags = [], bool $recent = true): int
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

        if ($db) {
            $msg = $db->getMsgById($msgId);
            if ($msg && file_exists($msg['path'])) {
                $content = file_get_contents($msg['path']);
                if ($content !== false) {
                    return $content;
                }
            }
        }

        return '';
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

                    $finder = new Finder();
                    
                    /** @var SplFileInfo[] $files */
                    $files = $finder->in($pathinfo['dirname'])->files()->depth(0)->name('*.eml')->sortByName();

                    foreach ($files as $file) {
                        $seq++;

                        if ($file->getFilename() == $pathinfo['basename']) {
                            break;
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

            $finder = new Finder();
            
            /** @var SplFileInfo[] $files */
            $files = $finder->in($path)->files()->depth(0)->name('*.eml')->sortByName();
            
            foreach ($files as $file) {
                $seq++;

                if ($seq >= $seqNum) {
                    return $db->getMsgIdByPath($file->getPathname());
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
            
            $finder = new Finder();
            
            /** @var SplFileInfo[] $files */
            $files = $finder->in($path)->files()->depth(0)->name('*.eml')->sortByName();
            
            foreach ($files as $file) {
                $seq++;
                if ($seq >= $seqNum) {
                    $msgId = $db->getMsgIdByPath($file->getPathname());

                    return $this->getFlagsById($msgId);
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
