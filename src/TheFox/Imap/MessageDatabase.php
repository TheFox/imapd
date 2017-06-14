<?php

/**
 * Message Database
 */

namespace TheFox\Imap;

use Zend\Mail\Message;
use Zend\Mail\Storage;
use TheFox\Storage\YamlStorage;

class MessageDatabase extends YamlStorage
{
    /**
     * @var array
     */
    private $msgsByPath = [];

    /**
     * MsgDb constructor.
     * @param string|null $filePath
     */
    public function __construct(string $filePath = null)
    {
        parent::__construct($filePath);

        $this->data['msgsId'] = 100000;
        $this->data['msgs'] = [];
        $this->data['timeCreated'] = time();
    }

    /*public function save(){
        $rv = parent::save();
        if($rv){
            $path = $this->getFilePath();
            $path = str_replace('.yml', '_2.yml', $path);
            $data = $this->data;
            foreach($data['msgs'] as $msgId => $msg){
                unset($data['msgs'][$msgId]['path']);
            }
            $rv = file_put_contents($path, Yaml::dump($data));
        }
        
        return $rv;
    }*/

    /**
     * @return bool
     */
    public function load(): bool
    {
        if (parent::load()) {

            if (array_key_exists('msgs', $this->data) && $this->data['msgs']) {
                foreach ($this->data['msgs'] as $msgId => $msgAr) {
                    $this->msgsByPath[$msgAr['path']] = $msgAr;
                }
            }

            return true;
        }

        return false;
    }

    /**
     * @param string $path
     * @param array|null $flags
     * @param bool $recent
     * @return int
     */
    public function addMsg(string $path, array $flags = null, bool $recent = true): int
    {
        if ($flags === null) {
            $flags = [Storage::FLAG_SEEN];
        }

        $this->data['msgsId']++;
        $msg = [
            'id' => $this->data['msgsId'],
            'path' => $path,
            'flags' => $flags,
            'recent' => $recent,
        ];

        $this->data['msgs'][$this->data['msgsId']] = $msg;
        $this->msgsByPath[$path] = $msg;
        $this->setDataChanged(true);

        return $this->data['msgsId'];
    }

    /**
     * @param int $msgId
     * @return array
     */
    public function removeMsg(int $msgId): array
    {
        $msg = $this->data['msgs'][$msgId];
        unset($this->data['msgs'][$msgId]);
        unset($this->msgsByPath[$msg['path']]);

        $this->setDataChanged(true);

        return $msg;
    }

    /**
     * @param string $path
     * @return int
     */
    public function getMsgIdByPath(string $path): int
    {
        if (isset($this->msgsByPath[$path])) {
            $id = $this->msgsByPath[$path]['id'];
            return $id;
        }

        return 0;
    }

    /**
     * @param int $msgId
     * @return null|Message
     */
    public function getMsgById(int $msgId)
    {
        if (isset($this->data['msgs'][$msgId])) {
            return $this->data['msgs'][$msgId];
        }

        return null;
    }

    /**
     * @param array $flags
     * @return array
     */
    public function getMsgIdsByFlags(array $flags): array
    {
        $rv = [];

        /**
         * @var int $msgId
         * @var array $msg
         */
        foreach ($this->data['msgs'] as $msgId => $msg) {
            /** @var bool $recent */
            $recent = $msg['recent'];

            /** @var array $msgFlags */
            $msgFlags = $msg['flags'];

            foreach ($flags as $flag) {
                if (in_array($flag, $msgFlags)
                    || $flag == Storage::FLAG_RECENT && $recent
                ) {
                    $rv[] = (int)$msg['id'];
                    break;
                }
            }
        }

        return $rv;
    }

    /**
     * @param int $msgId
     * @return array
     */
    public function getFlagsById(int $msgId): array
    {
        if (isset($this->data['msgs'][$msgId])) {
            /** @var array $msg */
            $msg = $this->data['msgs'][$msgId];

            /** @var array $flags */
            $flags = $msg['flags'];

            /** @var bool $recent */
            $recent = $msg['recent'];

            if ($recent) {
                $flags[] = Storage::FLAG_RECENT;
            }
            return $flags;
        }

        return [];
    }

    /**
     * @param int $msgId
     * @param array $flags
     */
    public function setFlagsById(int $msgId, array $flags)
    {
        $flags = array_unique($flags);
        if (($key = array_search(Storage::FLAG_RECENT, $flags)) !== false) {
            unset($flags[$key]);
        }
        $flags = array_values($flags);

        if (isset($this->data['msgs'][$msgId])) {
            $this->data['msgs'][$msgId]['flags'] = $flags;
            $this->data['msgs'][$msgId]['recent'] = false;

            $this->msgsByPath[$this->data['msgs'][$msgId]['path']] = $this->data['msgs'][$msgId];

            $this->setDataChanged(true);
        }
    }

    /**
     * @param int $msgId
     * @param string $path
     */
    public function setPathById(int $msgId, string $path)
    {
        if (isset($this->data['msgs'][$msgId])) {
            unset($this->msgsByPath[$this->data['msgs'][$msgId]['path']]);

            $this->data['msgs'][$msgId]['path'] = $path;
            $this->msgsByPath[$this->data['msgs'][$msgId]['path']] = $this->data['msgs'][$msgId];

            $this->setDataChanged(true);
        }
    }

    /**
     * @return int
     */
    public function getNextId(): int
    {
        return $this->data['msgsId'] + 1;
    }
}
