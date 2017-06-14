<?php

/**
 * Main Server
 * Handles Sockets and Clients.
 */

namespace TheFox\Imap;

use Exception;
use RuntimeException;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use TheFox\Network\StreamSocket;
use Zend\Mail\Message as ZendMailMessage;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Monolog\Logger;
use TheFox\Imap\Storage\AbstractStorage;
use TheFox\Imap\Storage\DirectoryStorage;
use TheFox\Network\Socket;
use Zend\Mail\Message;

class Server extends Thread
{
    use LoggerAwareTrait;
    
    const LOOP_USLEEP = 10000;

    /**
     * @var Logger
     * @deprecated
     */
    private $log;

    /**
     * @var Socket
     */
    private $socket;

    /**
     * @var bool
     */
    private $isListening = false;

    /**
     * @var array
     */
    private $options;

    /**
     * @var string
     * @deprecated
     */
    private $ip;

    /**
     * @var int
     * @deprecated
     */
    private $port;

    /**
     * @var int
     */
    private $clientsId = 0;

    /**
     * @var Client[]
     */
    private $clients = [];

    /**
     * @var string
     */
    private $defaultStoragePath = 'maildata';

    /**
     * @var AbstractStorage|DirectoryStorage
     */
    private $defaultStorage;

    /**
     * @var array
     */
    private $storages = [];

    /**
     * @var int
     */
    private $eventsId = 0;

    /**
     * @var array
     */
    private $events = [];

    /**
     * Server constructor.
     * @param array $options
     */
    public function __construct(array $options = [])
    {
        $resolver = new OptionsResolver();
        $resolver->setDefaults([
            'ip' => '127.0.0.1',
            'port' => 20143,
            'logger' => new NullLogger(),
        ]);
        $this->options = $resolver->resolve($options);

        $this->logger = $this->options['logger'];
        
        $this->setIp($this->options['ip']);
        $this->setPort($this->options['port']);
    }

    public function setIp($ip)
    {
        $this->ip = $ip;
    }

    public function setPort($port)
    {
        $this->port = $port;
    }

    /**
     * @return bool
     */
    public function listen(): bool
    {
        if ($this->ip && $this->port) {
            $this->logger->notice('listen on ' . $this->ip . ':' . $this->port);

            // Create a new Socket object.
            $this->socket = new Socket();

            $bind = false;
            try {
                $bind = $this->socket->bind($this->ip, $this->port);

                if ($this->socket->listen()) {
                    $this->logger->notice('listen ok');
                    $this->isListening = true;

                    return true;
                }
            } catch (Exception $e) {
                $this->logger->error($e->getMessage());
            }
        }

        return false;
    }

    /**
     * Main Function
     * Handles everything, keeps everything up-to-date.
     */
    public function run()
    {
        if (!$this->socket) {
            throw new RuntimeException('Socket not initialized. You need to execute listen().', 1);
        }

        $readHandles = [];
        $writeHandles = [];
        $exceptHandles = [];

        if ($this->isListening) {
            $readHandles[] = $this->socket->getHandle();
        }
        foreach ($this->clients as $clientId => $client) {
            $socket = $client->getSocket();

            // Collect client handles.
            $readHandles[] = $socket->getHandle();

            // Run client.
            $client->run();
        }

        $handlesChanged = $this->socket->select($readHandles, $writeHandles, $exceptHandles);
        if ($handlesChanged) {
            foreach ($readHandles as $readableHandle) {
                if ($this->isListening && $readableHandle == $this->socket->getHandle()) {
                    // Server
                    $socket = $this->socket->accept();
                    if ($socket) {
                        $client = $this->newClient($socket);
                        $client->sendHello();
                    }
                } else {
                    // Client
                    $client = $this->getClientByHandle($readableHandle);
                    if ($client) {
                        //$socket = $client->getSocket();

                        if (feof($client->getSocket()->getHandle())) {
                            $this->removeClient($client);
                        } else {
                            $client->dataRecv();
                            if ($client->getStatus('hasShutdown')) {
                                $this->removeClient($client);
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * Main Loop
     */
    public function loop()
    {
        while (!$this->getExit()) {
            $this->run();
            usleep(static::LOOP_USLEEP);
        }

        $this->shutdown();
    }

    /**
     * Shutdown the server.
     * Should be executed before your application exits.
     */
    public function shutdown()
    {
        // Notify all clients.
        foreach ($this->clients as $clientId => $client) {
            $client->sendBye('Server shutdown');
            $this->removeClient($client);
        }

        // Remove all temp files and save dbs.
        $this->shutdownStorages();
    }

    /**
     * @param StreamSocket $socket
     * @return Client
     */
    public function newClient(StreamSocket $socket): Client
    {
        $this->clientsId++;

        $options = [
            'logger' => $this->logger,
        ];
        $client = new Client($options);
        $client->setSocket($socket);
        $client->setId($this->clientsId);
        $client->setServer($this);

        $this->clients[$this->clientsId] = $client;

        return $client;
    }

    /**
     * @param resource $handle
     * @return Client|null
     */
    public function getClientByHandle($handle)
    {
        foreach ($this->clients as $clientId => $client) {
            if ($client->getSocket()->getHandle() == $handle) {
                return $client;
            }
        }
    }

    /**
     * @param Client $client
     */
    public function removeClient(Client $client)
    {
        $this->logger->debug('client remove: ' . $client->getId());

        $client->shutdown();

        $clientsId = $client->getId();
        unset($this->clients[$clientsId]);
    }

    /**
     * @return DirectoryStorage
     */
    public function getDefaultStorage()
    {
        if (!$this->defaultStorage) {
            $storage = new DirectoryStorage();
            $storage->setPath($this->defaultStoragePath);

            $this->addStorage($storage);
        }
        return $this->defaultStorage;
    }

    /**
     * @param AbstractStorage $storage
     */
    public function addStorage(AbstractStorage $storage)
    {
        if (!$this->defaultStorage) {
            $this->defaultStorage = $storage;

            $dbPath = $storage->getPath();
            if (substr($dbPath, -1) == '/') {
                $dbPath = substr($dbPath, 0, -1);
            }

            $dbPath .= '.yml';
            $storage->setDbPath($dbPath);

            $db = new MessageDatabase($dbPath);
            $db->load();

            $storage->setDb($db);
        } else {
            $this->storages[] = $storage;
        }
    }

    public function shutdownStorages()
    {
        $filesystem = new Filesystem();

        $this->getDefaultStorage()->save();

        foreach ($this->storages as $storageId => $storage) {
            if ($storage->getType() == 'temp') {
                $filesystem->remove($storage->getPath());

                if ($storage->getDbPath()) {
                    $filesystem->remove($storage->getDbPath());
                }
            } elseif ($storage->getType() == 'normal') {
                $storage->save();
            }
        }
    }

    /**
     * @param string $path
     * @return bool
     */
    public function addFolder(string $path): bool
    {
        $storage = $this->getDefaultStorage();
        $successful = $storage->createFolder($path);

        foreach ($this->storages as $storageId => $storage) {
            $storage->createFolder($path);
        }

        return $successful;
    }

    public function getFolders(string $baseFolder, string $searchFolder, bool $recursive = false, int $level = 0): array
    {
        $this->logger->debug('getFolders' . $level . ': /' . $baseFolder . '/ /' . $searchFolder . '/ ' . (int)$recursive . ', ' . $level);

        if ($level >= 100) {
            return []; // @todo throw exception instead
        }

        if ($baseFolder == '' && $searchFolder == 'INBOX') {
            return $this->getFolders('INBOX', '*', true, $level + 1);
        }

        $storage = $this->getDefaultStorage();
        $foundFolders = $storage->getFolders($baseFolder, $searchFolder, $recursive);
        
        $folders = [];
        foreach ($foundFolders as $folder) {
            $folder = str_replace('/', '.', $folder);
            $folders[] = $folder;
        }
        
        usort($folders, function(string $a, string $b){
            return $a <=> $b;
        });
        
        return $folders;
    }

    /**
     * @param string $folder
     * @return bool
     */
    public function folderExists(string $folder): bool
    {
        $storage = $this->getDefaultStorage();
        return $storage->folderExists($folder);
    }

    /**
     * @return int
     */
    public function getNextMsgId(): int
    {
        $storage = $this->getDefaultStorage();
        return $storage->getNextMsgId();
    }

    /**
     * @param int $msgId
     * @return int
     */
    public function getMsgSeqById(int $msgId): int
    {
        $storage = $this->getDefaultStorage();
        return $storage->getMsgSeqById($msgId);
    }

    /**
     * @param int $seqNum
     * @param string $folder
     * @return int
     */
    public function getMsgIdBySeq(int $seqNum, string $folder): int
    {
        $storage = $this->getDefaultStorage();
        return $storage->getMsgIdBySeq($seqNum, $folder);
    }

    /**
     * @param int $msgId
     * @return array
     */
    public function getFlagsById(int $msgId): array
    {
        $storage = $this->getDefaultStorage();
        return $storage->getFlagsById($msgId);
    }

    /**
     * @param int $msgId
     * @param array $flags
     */
    public function setFlagsById(int $msgId, array $flags)
    {
        $storage = $this->getDefaultStorage();
        $storage->setFlagsById($msgId, $flags);
    }

    /**
     * @param int $seqNum
     * @param string $folder
     * @return array
     */
    public function getFlagsBySeq(int $seqNum, string $folder): array
    {
        $storage = $this->getDefaultStorage();
        return $storage->getFlagsBySeq($seqNum, $folder);
    }

    /**
     * @param int $seqNum
     * @param string $folder
     * @param array $flags
     */
    public function setFlagsBySeq(int $seqNum, string $folder, array $flags)
    {
        $storage = $this->getDefaultStorage();
        $storage->setFlagsBySeq($seqNum, $folder, $flags);
    }

    /**
     * @param string $folder
     * @param array|null $flags
     * @return int
     */
    public function getCountMailsByFolder(string $folder, array $flags = []): int
    {
        /** @var DirectoryStorage $storage */
        $storage = $this->getDefaultStorage();
        return $storage->getMailsCountByFolder($folder, $flags);
    }

    /**
     * @param ZendMailMessage $mail
     * @param string|null $folder
     * @param array|null $flags
     * @param bool $recent
     * @return int
     */
    public function addMail(ZendMailMessage $mail, string $folder = null, array $flags = null, bool $recent = true): int
    {
        if (!$folder) {
            $folder = '';
        }
        
        $this->executeEvent(Event::TRIGGER_MAIL_ADD_PRE);

        $storage = $this->getDefaultStorage();
        $mailStr = $mail->toString();

        $msgId = $storage->addMail($mailStr, $folder, $flags, $recent);
        $storage->save();

        foreach ($this->storages as $storageId => $storage) {
            $storage->addMail($mailStr, $folder, $flags, $recent);
            $storage->save();
        }

        $this->executeEvent(Event::TRIGGER_MAIL_ADD, [$mail]);

        $this->executeEvent(Event::TRIGGER_MAIL_ADD_POST, [$msgId]);

        return $msgId;
    }

    /**
     * @param int $msgId
     */
    public function removeMailById(int $msgId)
    {
        $storage = $this->getDefaultStorage();
        $this->logger->debug('remove msgId: /' . $msgId . '/');
        $storage->removeMail($msgId);

        foreach ($this->storages as $storageId => $storage) {
            $storage->removeMail($msgId);
        }
    }

    /**
     * @param int $seqNum
     * @param string $folder
     */
    public function removeMailBySeq(int $seqNum, string $folder)
    {
        $this->logger->debug('remove seq: /' . $seqNum . '/');

        $msgId = $this->getMsgIdBySeq($seqNum, $folder);
        if ($msgId) {
            $this->removeMailById($msgId);
        }
    }

    /**
     * @param int $msgId
     * @param string $dstFolder
     */
    public function copyMailById(int $msgId, string $dstFolder)
    {
        $storage = $this->getDefaultStorage();
        $this->logger->debug('copy msgId: /' . $msgId . '/');
        $storage->copyMailById($msgId, $dstFolder);

        foreach ($this->storages as $storageId => $storage) {
            $storage->copyMailById($msgId, $dstFolder);
        }
    }

    /**
     * @param int $seqNum
     * @param string $folder
     * @param string $dstFolder
     */
    public function copyMailBySequenceNum(int $seqNum, string $folder, string $dstFolder)
    {
        $storage = $this->getDefaultStorage();
        $this->logger->debug('copy seq: /' . $seqNum . '/');
        $storage->copyMailBySequenceNum($seqNum, $folder, $dstFolder);

        foreach ($this->storages as $storageId => $storage) {
            $storage->copyMailBySequenceNum($seqNum, $folder, $dstFolder);
        }
    }

    /**
     * @param int $msgId
     * @return ZendMailMessage|null
     */
    public function getMailById(int $msgId)
    {
        /** @var DirectoryStorage $storage */
        $storage = $this->getDefaultStorage();
        
        $mailStr = $storage->getPlainMailById($msgId);
        if (!$mailStr){
            return null;
        }
        
        try{
            $mail = ZendMailMessage::fromString($mailStr);
            return $mail;
        }
        catch (\Error $e){
            print 'ZendMailMessage::fromString ERROR: '.$e."\n";
        }

        return null;
    }

    /**
     * @param int $seqNum
     * @param string $folder
     * @return null|Message
     */
    public function getMailBySeq(int $seqNum, string $folder)
    {
        $msgId = $this->getMsgIdBySeq($seqNum, $folder);
        if ($msgId) {
            return $this->getMailById($msgId);
        }

        return null;
    }

    /**
     * @param array $flags
     * @return array
     */
    public function getMailIdsByFlags(array $flags): array
    {
        $storage = $this->getDefaultStorage();

        $msgsIds = $storage->getMsgsByFlags($flags);

        return $msgsIds;
    }

    /**
     * @param Event $event
     */
    public function addEvent(Event $event)
    {
        $this->eventsId++;
        $this->events[$this->eventsId] = $event;
    }

    /**
     * @param int $trigger
     * @param array $args
     */
    private function executeEvent(int $trigger, array $args = [])
    {
        foreach ($this->events as $eventId => $event) {
            if ($event->getTrigger() != $trigger) {
                continue;
            }

            $event->execute($args);
        }
    }
}
