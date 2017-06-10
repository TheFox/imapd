<?php

namespace TheFox\Imap\Storage;

use Symfony\Component\Filesystem\Filesystem;

class TestStorage extends AbstractStorage
{
    /**
     * @return string
     */
    public function getDirectorySeperator(): string
    {
        return '_';
    }

    public function setPath(string $path)
    {
        parent::setPath($path);

        if (!file_exists($this->getPath())) {
            $filesystem = new Filesystem();
            $filesystem->mkdir($this->getPath(), 0755);
        }
    }

    public function createFolder(string $folder): bool
    {
        return false;
    }

    public function getFolders(string $baseFolder, string $searchFolder, bool $recursive = false): array
    {
        $folders = [];
        return $folders;
    }

    public function folderExists(string $folder): bool
    {
        return false;
    }

    public function getMailsCountByFolder(string $folder, array $flags = []): int
    {
        return 0;
    }

    public function addMail(string $mailStr, string $folder, array $flags, bool $recent): int
    {
        return 0;
    }

    public function removeMail(int $msgId)
    {
    }

    public function copyMailById(int $msgId, string $folder)
    {
    }

    public function copyMailBySequenceNum(int $seqNum, string $folder, string $dstFolder)
    {
    }

    public function getPlainMailById(int $msgId): string
    {
        return '';
    }

    public function getMsgSeqById(int $msgId): int
    {
        return 0;
    }

    public function getMsgIdBySeq(int $seqNum, string $folder): int
    {
        return 0;
    }

    public function getMsgsByFlags(array $flags): array
    {
        return [];
    }

    public function getFlagsById(int $msgId): array
    {
        return [];
    }

    public function setFlagsById(int $msgId, array $flags)
    {
    }

    public function getFlagsBySeq(int $seqNum, string $folder): array
    {
        return [];
    }

    public function setFlagsBySeq(int $seqNum, string $folder, array $flags)
    {
    }

    public function getNextMsgId(): int
    {
        return 0;
    }
}
