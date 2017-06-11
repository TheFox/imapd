<?php

namespace TheFox\Imap;

use Exception;
use RuntimeException;
use InvalidArgumentException;
use DateTime;
use TheFox\Logger\Logger;
use Zend\Mail\Storage;
use Zend\Mail\Headers;
use Zend\Mail\Message;
use TheFox\Network\AbstractSocket;
use TheFox\Logic\CriteriaTree;
use TheFox\Logic\Obj;
use TheFox\Logic\Gate;
use TheFox\Logic\AndGate;
use TheFox\Logic\OrGate;
use TheFox\Logic\NotGate;

class Client
{
    const MSG_SEPARATOR = "\r\n";

    /**
     * @var int
     */
    private $id = 0;

    /**
     * @var array
     */
    private $status = [];

    /**
     * @var Server
     */
    private $server;

    /**
     * @var AbstractSocket
     */
    private $socket;

    /**
     * @var string
     */
    private $ip = '';

    /**
     * @var integer
     */
    private $port = 0;

    /**
     * @var string
     */
    private $recvBufferTmp = '';

    /**
     * @var array
     */
    private $expunge = [];

    /**
     * @var array
     */
    private $subscriptions = [];

    /**
     * Remember the selected mailbox for each client.
     *
     * @var string
     */
    private $selectedFolder = '';

    public function __construct()
    {
        $this->status['hasShutdown'] = false;
        $this->status['hasAuth'] = false;
        $this->status['authStep'] = 0;
        $this->status['authTag'] = '';
        $this->status['authMechanism'] = '';
        $this->status['appendStep'] = 0;
        $this->status['appendTag'] = '';
        $this->status['appendFolder'] = '';
        $this->status['appendFlags'] = [];
        $this->status['appendDate'] = ''; // @NOTICE NOT_IMPLEMENTED
        $this->status['appendLiteral'] = 0;
        $this->status['appendMsg'] = '';
    }

    /**
     * @param int $id
     */
    public function setId(int $id)
    {
        $this->id = $id;
    }

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @param string $name
     * @return mixed|null
     */
    public function getStatus(string $name)
    {
        if (array_key_exists($name, $this->status)) {
            return $this->status[$name];
        }

        return null;
    }

    public function setStatus(string $name, $value)
    {
        $this->status[$name] = $value;
    }

    /**
     * @param Server $server
     */
    public function setServer(Server $server)
    {
        $this->server = $server;
    }

    /**
     * @return Server|null
     */
    public function getServer()
    {
        return $this->server;
    }

    /**
     * @param AbstractSocket $socket
     */
    public function setSocket(AbstractSocket $socket)
    {
        $this->socket = $socket;
    }

    /**
     * @return AbstractSocket
     */
    public function getSocket()
    {
        return $this->socket;
    }

    /**
     * @param string $ip
     */
    public function setIp(string $ip)
    {
        $this->ip = $ip;
    }

    /**
     * @return string
     */
    public function getIp(): string
    {
        if (!$this->ip) {
            $this->setIpPort();
        }
        return $this->ip;
    }

    /**
     * @param int $port
     */
    public function setPort(int $port)
    {
        $this->port = $port;
    }

    /**
     * @return int
     */
    public function getPort(): int
    {
        if (!$this->port) {
            $this->setIpPort();
        }
        return $this->port;
    }

    /**
     * @param string $ip
     * @param int $port
     */
    public function setIpPort(string $ip = '', int $port = 0)
    {
        $socket = $this->getSocket();
        if ($socket) {
            $socket->getPeerName($ip, $port);
        }

        $this->setIp($ip);
        $this->setPort($port);
    }

    /**
     * @return string
     */
    public function getIpPort(): string
    {
        return $this->getIp() . ':' . $this->getPort();
    }

    /**
     * @todo rename to getLogger
     * @return null|Logger
     */
    private function getLog()
    {
        if ($this->getServer()) {
            return $this->getServer()->getLog();
        }
        return null;
    }

    /**
     * @param string $level
     * @param string $msg
     */
    private function log(string $level, string $msg)
    {
        if ($this->getLog()) {
            if (method_exists($this->getLog(), $level)) {
                $this->getLog()->$level($msg);
            }
        }
    }

    public function run()
    {
    }

    public function dataRecv()
    {
        $data = $this->getSocket()->read();

        do {
            $separatorPos = strpos($data, static::MSG_SEPARATOR);
            if ($separatorPos === false) {
                $this->recvBufferTmp .= $data;
                $data = '';

                $this->log('debug', 'client ' . $this->id . ': collect data');
            } else {
                $msg = $this->recvBufferTmp . substr($data, 0, $separatorPos);
                $this->recvBufferTmp = '';

                $this->msgHandle($msg);

                $data = substr($data, $separatorPos + strlen(static::MSG_SEPARATOR));
            }
        } while ($data);
    }

    /**
     * @todo rename to parseMsgString
     * @param string $msgRaw
     * @param int|null $argsMax
     * @return array
     */
    public function msgParseString(string $msgRaw, int $argsMax = null): array
    {
        $str = new StringParser($msgRaw, $argsMax);
        $args = $str->parse();
        return $args;
    }

    /**
     * @todo rename to getMessageArguments
     * @param string $msgRaw
     * @param int|null $argsMax
     * @return array
     */
    public function msgGetArgs(string $msgRaw, int $argsMax = null): array
    {
        $args = $this->msgParseString($msgRaw, $argsMax);

        $tag = array_shift($args);
        $command = array_shift($args);

        return [
            'tag' => $tag,
            'command' => $command,
            'args' => $args,
        ];
    }

    /**
     * @todo rename to getMessageParenthesizedList or getParenthesizedList
     * @param string $msgRaw
     * @param int $level
     * @return array
     */
    public function msgGetParenthesizedlist(string $msgRaw, int $level = 0): array
    {
        $rv = [];
        $rvc = 0;
        if ($msgRaw) {
            if ($msgRaw[0] == '(' && substr($msgRaw, -1) != ')' || $msgRaw[0] != '(' && substr($msgRaw, -1) == ')') {
                $msgRaw = '(' . $msgRaw . ')';
            }
            if ($msgRaw[0] == '(' || $msgRaw[0] == '[') {
                $msgRaw = substr($msgRaw, 1);
            }
            if (substr($msgRaw, -1) == ')' || substr($msgRaw, -1) == ']') {
                $msgRaw = substr($msgRaw, 0, -1);
            }

            $msgRawLen = strlen($msgRaw);
            while ($msgRawLen) {
                if ($msgRaw[0] == '(' || $msgRaw[0] == '[') {

                    $pair = ')';
                    if ($msgRaw[0] == '[') {
                        $pair = ']';
                    }

                    // Find ')'
                    $pos = strlen($msgRaw);
                    while ($pos > 0) {
                        if (substr($msgRaw, $pos, 1) == $pair) {
                            break;
                        }
                        $pos--;
                    }

                    $rvc++;
                    $rv[$rvc] = $this->msgGetParenthesizedlist(substr($msgRaw, 0, $pos + 1), $level + 1);
                    $msgRaw = substr($msgRaw, $pos + 1);
                    $rvc++;
                } else {
                    if (!isset($rv[$rvc])) {
                        $rv[$rvc] = '';
                    }
                    $rv[$rvc] .= $msgRaw[0];
                    $msgRaw = substr($msgRaw, 1);
                }

                $msgRawLen = strlen($msgRaw);
            }
        }

        $rv2 = [];
        foreach ($rv as $n => $item) {
            if (is_string($item)) {
                foreach ($this->msgParseString($item) as $j => $sitem) {
                    $rv2[] = $sitem;
                }
            } else {
                $rv2[] = $item;
            }
        }

        return $rv2;
    }

    /**
     * @param string $setStr
     * @param bool $isUid
     * @return array
     */
    public function createSequenceSet(string $setStr, $isUid = false): array
    {
        // Collect messages with sequence-sets.
        $setStr = trim($setStr);

        $msgSeqNums = [];
        foreach (preg_split('/,/', $setStr) as $seqItem) {
            $seqItem = trim($seqItem);

            $seqMin = 0;
            $seqMax = 0;
            //$seqLen = 0;
            $seqAll = false;

            $items = preg_split('/:/', $seqItem, 2);
            $items = array_map('trim', $items);

            $nums = [];
            $count = $this->getServer()->getCountMailsByFolder($this->selectedFolder);
            if (!$count) {
                return [];
            }

            // Check if it's a range.
            if (count($items) == 2) {
                $seqMin = (int)$items[0];
                if ($items[1] == '*') {
                    if ($isUid) {
                        // Search the last msg
                        for ($msgSeqNum = 1; $msgSeqNum <= $count; $msgSeqNum++) {
                            $uid = $this->getServer()->getMsgIdBySeq($msgSeqNum, $this->selectedFolder);

                            if ($uid > $seqMax) {
                                $seqMax = $uid;
                            }
                        }
                    } else {
                        $seqMax = $count;
                    }
                } else {
                    $seqMax = (int)$items[1];
                }
            } else {
                if ($isUid) {
                    if ($items[0] == '*') {
                        $seqAll = true;
                    } else {
                        $seqMin = $seqMax = (int)$items[0];
                    }
                } else {
                    if ($items[0] == '*') {
                        $seqMin = 1;
                        $seqMax = $count;
                    } else {
                        $seqMin = $seqMax = (int)$items[0];
                    }
                }
            }

            if ($seqMin > $seqMax) {
                $tmp = $seqMin;
                $seqMin = $seqMax;
                $seqMax = $tmp;
            }

            $seqLen = $seqMax + 1 - $seqMin;

            if ($isUid) {
                if ($seqLen >= 1) {
                    for ($msgSeqNum = 1; $msgSeqNum <= $count; $msgSeqNum++) {
                        $uid = $this->getServer()->getMsgIdBySeq($msgSeqNum, $this->selectedFolder);

                        if ($uid >= $seqMin && $uid <= $seqMax || $seqAll) {
                            $nums[] = $msgSeqNum;
                        }
                        if (count($nums) >= $seqLen && !$seqAll) {
                            break;
                        }
                    }
                }
                /*else{
                    throw new RuntimeException('Invalid minimum sequence length: "'.$seqLen.'" ('.$seqMin.'/'.$seqMax.')', 2);
                }*/
            } else {
                if ($seqLen == 1) {
                    if ($seqMin > 0 && $seqMin <= $count) {
                        $nums[] = $seqMin;
                    }
                } elseif ($seqLen >= 2) {
                    for ($msgSeqNum = 1; $msgSeqNum <= $count; $msgSeqNum++) {
                        if ($msgSeqNum >= $seqMin && $msgSeqNum <= $seqMax) {
                            $nums[] = $msgSeqNum;
                        }

                        if (count($nums) >= $seqLen) {
                            break;
                        }
                    }
                }
                /*else{
                    throw new RuntimeException('Invalid minimum sequence length: "'.$seqLen.'" ('.$seqMin.'/'.$seqMax.')', 1);
                }*/
            }

            $msgSeqNums = array_merge($msgSeqNums, $nums);
        }

        sort($msgSeqNums, SORT_NUMERIC);

        return $msgSeqNums;
    }

    /**
     * @todo rename to handleRawPacket
     * @param string $msgRaw
     * @return string
     */
    public function msgHandle(string $msgRaw): string
    {
        $this->log('debug', 'client ' . $this->id . ' raw: /' . $msgRaw . '/');

        /** @var array $args */
        $args = $this->msgParseString($msgRaw, 3);

        // Get Tag, and remove Tag from Arguments.
        /** @var string $tag */
        $tag = array_shift($args);

        // Get Command, and remove Command from Arguments.
        /** @var string $command */
        $command = array_shift($args);
        $commandCmp = strtolower($command);

        // Get rest Arguments as String. Do not reuse $args here. Just let it as it is.
        /** @var string $restArgs */
        $restArgs = array_shift($args) ?? '';

        if ($commandCmp == 'capability') {
            return $this->sendCapability($tag);
        } elseif ($commandCmp == 'noop') {
            return $this->sendNoop($tag);
        } elseif ($commandCmp == 'logout') {
            $rv = $this->sendBye('IMAP4rev1 Server logging out');
            $rv .= $this->sendLogout($tag);

            $this->shutdown();

            return $rv;
        } elseif ($commandCmp == 'authenticate') {
            $commandArgs = $this->msgParseString($restArgs, 1);

            if (strtolower($commandArgs[0]) == 'plain') {
                $this->setStatus('authStep', 1);
                $this->setStatus('authTag', $tag);
                $this->setStatus('authMechanism', $commandArgs[0]);

                return $this->sendAuthenticate();
            } else {
                return $this->sendNo($commandArgs[0] . ' Unsupported authentication mechanism', $tag);
            }
        } elseif ($commandCmp == 'login') {
            $commandArgs = $this->msgParseString($restArgs, 2);

            if (isset($commandArgs[0]) && $commandArgs[0] && isset($commandArgs[1]) && $commandArgs[1]) {
                return $this->sendLogin($tag);
            } else {
                return $this->sendBad('Arguments invalid.', $tag);
            }
        } elseif ($commandCmp == 'select') {
            $commandArgs = $this->msgParseString($restArgs, 1);

            if ($this->getStatus('hasAuth')) {
                if (isset($commandArgs[0]) && $commandArgs[0]) {
                    return $this->sendSelect($tag, $commandArgs[0]);
                } else {
                    $this->selectedFolder = '';
                    return $this->sendBad('Arguments invalid.', $tag);
                }
            } else {
                $this->selectedFolder = '';
                return $this->sendNo($commandCmp . ' failure', $tag);
            }
        } elseif ($commandCmp == 'create') {
            $commandArgs = $this->msgParseString($restArgs, 1);

            #$this->log('debug', 'client '.$this->id.' create: '.$args[0]);

            if ($this->getStatus('hasAuth')) {
                if (isset($commandArgs[0]) && $commandArgs[0]) {
                    return $this->sendCreate($tag, $commandArgs[0]);
                } else {
                    return $this->sendBad('Arguments invalid.', $tag);
                }
            } else {
                return $this->sendNo($commandCmp . ' failure', $tag);
            }
        } elseif ($commandCmp == 'subscribe') {
            $commandArgs = $this->msgParseString($restArgs, 1);

            if ($this->getStatus('hasAuth')) {
                if (isset($commandArgs[0]) && $commandArgs[0]) {
                    return $this->sendSubscribe($tag, $commandArgs[0]);
                } else {
                    return $this->sendBad('Arguments invalid.', $tag);
                }
            } else {
                return $this->sendNo($commandCmp . ' failure', $tag);
            }
        } elseif ($commandCmp == 'unsubscribe') {
            $commandArgs = $this->msgParseString($restArgs, 1);

            if ($this->getStatus('hasAuth')) {
                if (isset($commandArgs[0]) && $commandArgs[0]) {
                    return $this->sendUnsubscribe($tag, $commandArgs[0]);
                } else {
                    return $this->sendBad('Arguments invalid.', $tag);
                }
            } else {
                return $this->sendNo($commandCmp . ' failure', $tag);
            }
        } elseif ($commandCmp == 'list') {
            $args = $this->msgParseString($restArgs, 2);

            if ($this->getStatus('hasAuth')) {
                if (isset($args[0]) && isset($args[1]) && $args[1]) {
                    $refName = $args[0];
                    $folder = $args[1];
                    return $this->sendList($tag, $refName, $folder);
                } else {
                    return $this->sendBad('Arguments invalid.', $tag);
                }
            } else {
                return $this->sendNo($commandCmp . ' failure', $tag);
            }
        } elseif ($commandCmp == 'lsub') {
            $commandArgs = $this->msgParseString($restArgs, 1);

            $this->log('debug', 'client ' . $this->id . ' lsub: ' . (isset($commandArgs[0]) ? $commandArgs[0] : 'N/A'));

            if ($this->getStatus('hasAuth')) {
                if (isset($commandArgs[0]) && $commandArgs[0]) {
                    return $this->sendLsub($tag);
                } else {
                    return $this->sendBad('Arguments invalid.', $tag);
                }
            } else {
                return $this->sendNo($commandCmp . ' failure', $tag);
            }
        } elseif ($commandCmp == 'append') {
            $commandArgs = $this->msgParseString($restArgs, 4);

            $this->log('debug', 'client ' . $this->id . ' append');

            if ($this->getStatus('hasAuth')) {
                if (isset($commandArgs[0]) && $commandArgs[0] && isset($commandArgs[1]) && $commandArgs[1]) {
                    $this->setStatus('appendFlags', []);
                    $this->setStatus('appendDate', '');
                    $this->setStatus('appendLiteral', 0);
                    $this->setStatus('appendMsg', '');

                    $flags = [];
                    $literal = 0;

                    if (!isset($commandArgs[2]) && !isset($commandArgs[3])) {
                        $this->log('debug', 'client ' . $this->id . ' append: 2 not set, 3 not set');
                        $literal = $commandArgs[1];
                    } elseif (isset($commandArgs[2]) && !isset($commandArgs[3])) {
                        $this->log('debug', 'client ' . $this->id . ' append: 2 set, 3 not set, A');

                        if ($commandArgs[1][0] == '(' && substr($commandArgs[1], -1) == ')') {
                            $this->log('debug', 'client ' . $this->id . ' append: 2 set, 3 not set, B');

                            $flags = $this->msgGetParenthesizedlist($commandArgs[1]);
                        } else {
                            $this->log('debug', 'client ' . $this->id . ' append: 2 set, 3 not set, C');

                            $this->setStatus('appendDate', $commandArgs[1]);
                        }
                        $literal = $commandArgs[2];
                    } elseif (isset($commandArgs[2]) && isset($commandArgs[3])) {
                        $this->log('debug', 'client ' . $this->id . ' append: 2 set, 3 set');

                        $flags = $this->msgGetParenthesizedlist($commandArgs[1]);
                        $this->setStatus('appendDate', $commandArgs[2]);
                        $literal = $commandArgs[3];
                    }

                    if ($flags) {
                        #$flags = array_combine($flags, $flags);
                        $flags = array_unique($flags);
                    }
                    $this->setStatus('appendFlags', $flags);

                    if ($literal[0] == '{' && substr($literal, -1) == '}') {
                        $literal = (int)substr(substr($literal, 1), 0, -1);
                    } else {
                        return $this->sendBad('Arguments invalid.', $tag);
                    }
                    $this->setStatus('appendLiteral', $literal);

                    $this->setStatus('appendStep', 1);
                    $this->setStatus('appendTag', $tag);
                    $this->setStatus('appendFolder', $commandArgs[0]);

                    return $this->sendAppend();
                } else {
                    return $this->sendBad('Arguments invalid.', $tag);
                }
            } else {
                return $this->sendNo($commandCmp . ' failure', $tag);
            }
        } elseif ($commandCmp == 'check') {
            if ($this->getStatus('hasAuth')) {
                return $this->sendCheck($tag);
            } else {
                return $this->sendNo($commandCmp . ' failure', $tag);
            }
        } elseif ($commandCmp == 'close') {
            $this->log('debug', 'client ' . $this->id . ' close');

            if ($this->getStatus('hasAuth')) {
                if ($this->selectedFolder) {
                    return $this->sendClose($tag);
                } else {
                    return $this->sendNo('No mailbox selected.', $tag);
                }
            } else {
                return $this->sendNo($commandCmp . ' failure', $tag);
            }
        } elseif ($commandCmp == 'expunge') {
            $this->log('debug', 'client ' . $this->id . ' expunge');

            if ($this->getStatus('hasAuth')) {
                if ($this->selectedFolder) {
                    return $this->sendExpunge($tag);
                } else {
                    return $this->sendNo('No mailbox selected.', $tag);
                }
            } else {
                return $this->sendNo($commandCmp . ' failure', $tag);
            }
        } elseif ($commandCmp == 'search') {
            $this->log('debug', 'client ' . $this->id . ' search');

            if ($this->getStatus('hasAuth')) {
                if (isset($args[0]) && $args[0]) {
                    if ($this->selectedFolder) {
                        $criteriaStr = $args[0];
                        return $this->sendSearch($tag, $criteriaStr);
                    } else {
                        return $this->sendNo('No mailbox selected.', $tag);
                    }
                } else {
                    return $this->sendBad('Arguments invalid.', $tag);
                }
            } else {
                return $this->sendNo($commandCmp . ' failure', $tag);
            }
        } elseif ($commandCmp == 'store') {
            $commandArgs = $this->msgParseString($restArgs, 3);

            $this->log('debug', 'client ' . $this->id . ' store: "' . $commandArgs[0] . '" "' . $commandArgs[1] . '" "' . $commandArgs[2] . '"');

            if ($this->getStatus('hasAuth')) {
                if (isset($commandArgs[0]) && $commandArgs[0] && isset($commandArgs[1]) && $commandArgs[1] && isset($commandArgs[2]) && $commandArgs[2]) {
                    if ($this->selectedFolder) {
                        $seq = $commandArgs[0];
                        $name = $commandArgs[1];
                        $flagsStr = $commandArgs[2];
                        $this->sendStore($tag, $seq, $name, $flagsStr);
                    } else {
                        $this->sendNo('No mailbox selected.', $tag);
                    }
                } else {
                    $this->sendBad('Arguments invalid.', $tag);
                }
            } else {
                $this->sendNo($commandCmp . ' failure', $tag);
            }
        } elseif ($commandCmp == 'copy') {
            $commandArgs = $this->msgParseString($restArgs, 2);

            if ($this->getStatus('hasAuth')) {
                if (isset($commandArgs[0]) && $commandArgs[0] && isset($commandArgs[1]) && $commandArgs[1]) {
                    if ($this->selectedFolder) {
                        $seq = $commandArgs[0];
                        $folder = $commandArgs[1];
                        return $this->sendCopy($tag, $seq, $folder);
                    } else {
                        return $this->sendNo('No mailbox selected.', $tag);
                    }
                } else {
                    return $this->sendBad('Arguments invalid.', $tag);
                }
            } else {
                return $this->sendNo($commandCmp . ' failure', $tag);
            }
        } elseif ($commandCmp == 'uid') {
            if ($this->getStatus('hasAuth')) {
                if ($this->selectedFolder) {
                    return $this->sendUid($tag, $restArgs);
                } else {
                    return $this->sendNo('No mailbox selected.', $tag);
                }
            } else {
                return $this->sendNo($commandCmp . ' failure', $tag);
            }
        } else {
            #$this->log('debug', 'client '.$this->id.' auth step:   '.$this->getStatus('authStep'));
            #$this->log('debug', 'client '.$this->id.' append step: '.$this->getStatus('appendStep'));

            if ($this->getStatus('authStep') == 1) {
                $this->setStatus('authStep', 2);
                return $this->sendAuthenticate();
            } elseif ($this->getStatus('appendStep') >= 1) {
                return $this->sendAppend($msgRaw);
            } else {
                $this->log('debug', 'client ' . $this->id . ' not implemented: "' . $tag . '" "' . $command . '" >"' . join(' ', $args) . '"<');
                return $this->sendBad('Not implemented: "' . $tag . '" "' . $command . '"', $tag);
            }
        }

        return '';
    }

    /**
     * @todo rename to sendData
     * @param string $msg
     * @return string
     */
    public function dataSend(string $msg): string
    {
        $output = $msg . static::MSG_SEPARATOR;

        $tmp = $msg;
        $tmp = str_replace("\r", '', $tmp);
        $tmp = str_replace("\n", '\\n', $tmp);

        $socket = $this->getSocket();
        if ($socket) {
            $this->log('debug', 'client ' . $this->id . ' data send: "' . $tmp . '"');
            $socket->write($output);
        } else {
            $this->log('debug', 'client ' . $this->id . ' DEBUG data send: "' . $tmp . '"');
        }

        return $output;
    }

    public function sendHello()
    {
        $this->sendOk('IMAP4rev1 Service Ready');
    }

    /**
     * @param string $tag
     * @return string
     */
    private function sendCapability(string $tag)
    {
        $rv = $this->dataSend('* CAPABILITY IMAP4rev1 AUTH=PLAIN');
        $rv .= $this->sendOk('CAPABILITY completed', $tag);

        return $rv;
    }

    /**
     * @param string $tag
     * @return string
     */
    private function sendNoop(string $tag): string
    {
        if ($this->selectedFolder) {
            $this->sendSelectedFolderInfos();
        }
        return $this->sendOk('NOOP completed client ' . $this->getId() . ', "' . $this->selectedFolder . '"', $tag);
    }

    /**
     * @param string $tag
     * @return string
     */
    private function sendLogout(string $tag): string
    {
        return $this->sendOk('LOGOUT completed', $tag);
    }

    /**
     * @return string
     */
    private function sendAuthenticate(): string
    {
        if ($this->getStatus('authStep') == 1) {
            return $this->dataSend('+');
        } elseif ($this->getStatus('authStep') == 2) {
            $this->setStatus('hasAuth', true);
            $this->setStatus('authStep', 0);

            return $this->sendOk($this->getStatus('authMechanism') . ' authentication successful', $this->getStatus('authTag'));
        }

        return '';
    }

    /**
     * @param string $tag
     * @return string
     */
    private function sendLogin(string $tag): string
    {
        return $this->sendOk('LOGIN completed', $tag);
    }

    /**
     * @return string
     */
    private function sendSelectedFolderInfos(): string
    {
        $nextId = $this->getServer()->getNextMsgId();
        $count = $this->getServer()->getCountMailsByFolder($this->selectedFolder);
        $recent = $this->getServer()->getCountMailsByFolder($this->selectedFolder, [Storage::FLAG_RECENT]);

        $firstUnseen = 0;
        for ($msgSeqNum = 1; $msgSeqNum <= $count; $msgSeqNum++) {
            $flags = $this->getServer()->getFlagsBySeq($msgSeqNum, $this->selectedFolder);
            if (!in_array(Storage::FLAG_SEEN, $flags) && !$firstUnseen) {
                $firstUnseen = $msgSeqNum;
                break;
            }
        }

        $rv = '';
        foreach ($this->expunge as $msgSeqNum) {
            $rv .= $this->dataSend('* ' . $msgSeqNum . ' EXPUNGE');
        }

        $rv .= $this->dataSend('* ' . $count . ' EXISTS');
        $rv .= $this->dataSend('* ' . $recent . ' RECENT');
        $rv .= $this->sendOk('Message ' . $firstUnseen . ' is first unseen', null, 'UNSEEN ' . $firstUnseen);

        #$rv .= $this->dataSend('* OK [UIDVALIDITY 3857529045] UIDs valid');

        if ($nextId) {
            $rv .= $this->sendOk('Predicted next UID', null, 'UIDNEXT ' . $nextId);
        }
        $availableFlags = [Storage::FLAG_ANSWERED,
            Storage::FLAG_FLAGGED,
            Storage::FLAG_DELETED,
            Storage::FLAG_SEEN,
            Storage::FLAG_DRAFT];
        $rv .= $this->dataSend('* FLAGS (' . join(' ', $availableFlags) . ')');
        $rv .= $this->sendOk('Limited', null, 'PERMANENTFLAGS (' . Storage::FLAG_DELETED . ' ' . Storage::FLAG_SEEN . ' \*)');

        return $rv;
    }

    /**
     * @param string $tag
     * @param string $folder
     * @return string
     */
    private function sendSelect(string $tag, string $folder): string
    {
        if (strtolower($folder) == 'inbox' && $folder != 'INBOX') {
            // Set folder to INBOX if folder is not INBOX
            // e.g. Inbox, INbOx or something like this.
            $folder = 'INBOX';
        }

        if ($this->select($folder)) {
            $rv = $this->sendSelectedFolderInfos();
            $rv .= $this->sendOk('SELECT completed', $tag, 'READ-WRITE');
            return $rv;
        }

        return $this->sendNo('"' . $folder . '" no such mailbox', $tag);
    }

    /**
     * @param string $tag
     * @param string $folder
     * @return string
     */
    private function sendCreate(string $tag, string $folder): string
    {
        if (strpos($folder, '/') !== false) {
            $msg = 'invalid name';
            $msg .= ' - no directory separator allowed in folder name';
            return $this->sendNo('CREATE failure: ' . $msg, $tag);
        }

        if ($this->getServer()->addFolder($folder)) {
            return $this->sendOk('CREATE completed', $tag);
        }

        return $this->sendNo('CREATE failure: folder already exists', $tag);
    }

    /**
     * @param string $tag
     * @param string $folder
     * @return string
     */
    private function sendSubscribe(string $tag, string $folder): string
    {
        if ($this->getServer()->folderExists($folder)) {
            // @NOTICE NOT_IMPLEMENTED

            #fwrite(STDOUT, 'subsc: '.$folder."\n");

            #$folders = $this->getServer()->getFolders($folder);
            $this->subscriptions[] = $folder;

            return $this->sendOk('SUBSCRIBE completed', $tag);
        }

        return $this->sendNo('SUBSCRIBE failure: no subfolder named test_dir', $tag);
    }

    /**
     * @param string $tag
     * @param string $folder
     * @return string
     */
    private function sendUnsubscribe(string $tag, string $folder): string
    {
        if ($this->getServer()->folderExists($folder)) {
            // @NOTICE NOT_IMPLEMENTED

            #$folders = $this->getServer()->getFolders($folder);
            #unset($this->subscriptions[$folder]);

            return $this->sendOk('UNSUBSCRIBE completed', $tag);
        }

        return $this->sendNo('UNSUBSCRIBE failure: no subfolder named test_dir', $tag);
    }

    /**
     * @param string $tag
     * @param string $baseFolder
     * @param string $folder
     * @return string
     */
    private function sendList(string $tag, string $baseFolder, string $folder): string
    {
        $this->log('debug', 'client ' . $this->id . ' list: /' . $baseFolder . '/ /' . $folder . '/');

        $folder = str_replace('%', '*', $folder); // @NOTICE NOT_IMPLEMENTED

        $folders = $this->getServer()->getFolders($baseFolder, $folder, true);
        $rv = '';
        if (count($folders)) {
            foreach ($folders as $cfolder) {
                $rv .= $this->dataSend('* LIST () "." "' . $cfolder . '"');
            }
        } else {
            if ($this->getServer()->folderExists($folder)) {
                $rv .= $this->dataSend('* LIST () "." "' . $folder . '"');
            }
        }

        $rv .= $this->sendOk('LIST completed', $tag);

        return $rv;
    }

    /**
     * @param string $tag
     * @return string
     */
    private function sendLsub(string $tag): string
    {
        $rv = '';
        foreach ($this->subscriptions as $subscription) {
            $rv .= $this->dataSend('* LSUB () "." "' . $subscription . '"');
        }

        $rv .= $this->sendOk('LSUB completed', $tag);

        return $rv;
    }

    /**
     * @param string $data
     * @return string
     */
    private function sendAppend(string $data = ''): string
    {
        $appendMsgLen = strlen($this->getStatus('appendMsg'));

        if ($this->getStatus('appendStep') == 1) {
            $this->status['appendStep']++;

            return $this->dataSend('+ Ready for literal data');
        } elseif ($this->getStatus('appendStep') == 2) {
            if ($appendMsgLen < $this->getStatus('appendLiteral')) {
                $this->status['appendMsg'] .= $data . Headers::EOL;
                $appendMsgLen = strlen($this->getStatus('appendMsg'));
            }

            if ($appendMsgLen >= $this->getStatus('appendLiteral')) {
                $this->status['appendStep']++;
                $this->log('debug', 'client ' . $this->id . ' append len reached: ' . $appendMsgLen);

                $message = Message::fromString($this->getStatus('appendMsg'));

                try {
                    $this->getServer()->addMail($message, $this->getStatus('appendFolder'),
                        $this->getStatus('appendFlags'), false)
                    ;
                    $this->log('debug', 'client ' . $this->id . ' append completed: ' . $this->getStatus('appendStep'));
                    return $this->sendOk('APPEND completed', $this->getStatus('appendTag'));
                } catch (Exception $e) {
                    $noMsg = 'Can not get folder: ' . $this->getStatus('appendFolder');
                    return $this->sendNo($noMsg, $this->getStatus('appendTag'), 'TRYCREATE');
                }
            } else {
                $diff = $this->getStatus('appendLiteral') - $appendMsgLen;
                $this->log('debug', 'client ' . $this->id . ' append left: ' . $diff . ' (' . $appendMsgLen . ')');
            }
        }

        return '';
    }

    /**
     * @param string $tag
     * @return string
     */
    private function sendCheck(string $tag): string
    {
        if ($this->selectedFolder) {
            return $this->sendOk('CHECK completed', $tag);
        } else {
            return $this->sendNo('No mailbox selected.', $tag);
        }
    }

    /**
     * @param string $tag
     * @return string
     */
    private function sendClose(string $tag): string
    {
        $this->log('debug', 'client ' . $this->id . ' current folder: ' . $this->selectedFolder);

        $this->sendExpungeRaw();

        $this->selectedFolder = '';

        return $this->sendOk('CLOSE completed', $tag);
    }

    /**
     * @return array
     */
    private function sendExpungeRaw(): array
    {
        $this->log('debug', 'client ' . $this->id . ' sendExpungeRaw');

        $msgSeqNumsExpunge = [];
        $expungeDiff = 0;

        $msgSeqNums = $this->createSequenceSet('*');

        foreach ($msgSeqNums as $msgSeqNum) {
            $expungeSeqNum = $msgSeqNum - $expungeDiff;
            $this->log('debug', 'client ' . $this->id . ' check msg: ' . $msgSeqNum . ', ' . $expungeDiff . ', ' . $expungeSeqNum);

            $flags = $this->getServer()->getFlagsBySeq($expungeSeqNum, $this->selectedFolder);
            if (in_array(Storage::FLAG_DELETED, $flags)) {
                $this->log('debug', 'client ' . $this->id . '      del msg: ' . $expungeSeqNum);
                $this->getServer()->removeMailBySeq($expungeSeqNum, $this->selectedFolder);
                $msgSeqNumsExpunge[] = $expungeSeqNum;
                $expungeDiff++;
            }
        }

        return $msgSeqNumsExpunge;
    }

    /**
     * @param string $tag
     * @return string
     */
    private function sendExpunge(string $tag): string
    {
        $rv = '';

        $msgSeqNumsExpunge = $this->sendExpungeRaw();
        foreach ($msgSeqNumsExpunge as $msgSeqNum) {
            #$this->log('debug', 'client '.$this->id.' expunge: '.$msgSeqNum);
            $rv .= $this->dataSend('* ' . $msgSeqNum . ' EXPUNGE');
        }
        $rv .= $this->sendOk('EXPUNGE completed', $tag);

        $this->expunge = [];

        return $rv;
    }

    /**
     * @param array $list
     * @param int $posOffset
     * @param int $maxItems
     * @param bool $addAnd
     * @param int $level
     * @return array
     */
    public function parseSearchKeys(array $list, int &$posOffset = 0, int $maxItems = 0, bool $addAnd = true, int $level = 0): array
    {
        //$func = __FUNCTION__;
        $len = count($list);
        $rv = [];

        if ($len <= 1) {
            return $list;
        }

        $itemsC = 0;
        $pos = 0;
        for ($pos = 0; $pos < $len; $pos++) {
            $orgpos = $pos;
            $item = $list[$pos];
            $itemWithArgs = '';

            $and = true;
            $offset = 0;

            if (is_array($item)) {
                $subPosOffset = 0;
                $itemWithArgs = [$this->parseSearchKeys($item, $subPosOffset, 0, true, $level + 1)];
            } else {
                $itemcmp = strtolower($item);
                if (
                    $itemcmp == 'all'
                    || $itemcmp == 'answered'
                    || $itemcmp == 'deleted'
                    || $itemcmp == 'draft'
                    || $itemcmp == 'flagged'
                    || $itemcmp == 'new'
                    || $itemcmp == 'old'
                    || $itemcmp == 'recent'
                    || $itemcmp == 'seen'
                    || $itemcmp == 'unanswered'
                    || $itemcmp == 'undeleted'
                    || $itemcmp == 'undraft'
                    || $itemcmp == 'unflagged'
                    || $itemcmp == 'unseen'
                ) {
                    $itemWithArgs = $item;
                } elseif ($itemcmp == 'bcc'
                    || $itemcmp == 'before'
                    || $itemcmp == 'body'
                    || $itemcmp == 'cc'
                    || $itemcmp == 'from'
                    || $itemcmp == 'keyword'
                    || $itemcmp == 'larger'
                    || $itemcmp == 'on'
                    || $itemcmp == 'sentbefore'
                    || $itemcmp == 'senton'
                    || $itemcmp == 'sentsince'
                    || $itemcmp == 'since'
                    || $itemcmp == 'smaller'
                    || $itemcmp == 'subject'
                    || $itemcmp == 'text'
                    || $itemcmp == 'to'
                    || $itemcmp == 'uid'
                    || $itemcmp == 'unkeyword'
                ) {
                    $itemWithArgs = $item . ' ' . $list[$pos + 1];
                    $offset++;
                } elseif ($itemcmp == 'header') {
                    $itemWithArgs = $item . ' ' . $list[$pos + 1] . ' ' . $list[$pos + 2];
                    $offset += 2;
                } elseif ($itemcmp == 'or') {
                    $rest = array_slice($list, $pos + 1);
                    $subPosOffset = 0;
                    $sublist = $this->parseSearchKeys($rest, $subPosOffset, 2, false, $level + 1);
                    $itemWithArgs = [[$sublist[0], 'OR', $sublist[1]]];

                    $offset += $subPosOffset;
                } elseif ($itemcmp == 'and') {
                    $and = false;
                } elseif ($itemcmp == 'not') {
                    $rest = array_slice($list, $pos + 1);
                    $subPosOffset = 0;
                    $sublist = $this->parseSearchKeys($rest, $subPosOffset, 1, false, $level + 1);
                    $itemWithArgs = [$item, $sublist[0]];
                    $offset += $subPosOffset;
                } elseif (is_numeric($itemcmp)) {
                    $itemWithArgs = $item;
                }
            }

            if ($pos <= 0) {
                $and = false;
            }

            if ($addAnd && $and) {
                $rv[] = 'AND';
                //$and = false;
            }
            if ($itemWithArgs) {
                if (is_array($itemWithArgs)) {
                    $rv = array_merge($rv, $itemWithArgs);
                } else {
                    $rv[] = $itemWithArgs;
                }
            }

            $pos += $offset;
            $itemsC++;
            if ($maxItems && $itemsC >= $maxItems) {
                break;
            }
        }

        $posOffset = $pos + 1;

        return $rv;
    }

    /**
     * @param Message $message
     * @param int $messageSeqNum
     * @param int $messageUid
     * @param string $searchKey
     * @return bool
     */
    public function searchMessageCondition(Message $message, int $messageSeqNum, int $messageUid, string $searchKey): bool
    {
        $items = preg_split('/ /', $searchKey, 3);
        $itemcmp = strtolower($items[0]);

        $flags = $this->getServer()->getFlagsById($messageUid);

        $rv = false;
        switch ($itemcmp) {
            case 'all':
                return true;

            case 'answered':
                return in_array(Storage::FLAG_ANSWERED, $flags);

            case 'bcc':
                $searchStr = strtolower($items[1]);
                $bccAddressList = $message->getBcc();
                if (count($bccAddressList)) {
                    foreach ($bccAddressList as $bcc) {
                        return strpos(strtolower($bcc->getEmail()), $searchStr) !== false;
                    }
                }
                break;

            case 'before':
                // @NOTICE NOT_IMPLEMENTED
                break;

            case 'body':
                $searchStr = strtolower($items[1]);
                return strpos(strtolower($message->getBody()), $searchStr) !== false;

            case 'cc':
                $searchStr = strtolower($items[1]);
                $ccAddressList = $message->getCc();
                if (count($ccAddressList)) {
                    foreach ($ccAddressList as $from) {
                        return strpos(strtolower($from->getEmail()), $searchStr) !== false;
                    }
                }
                break;

            case 'deleted':
                return in_array(Storage::FLAG_DELETED, $flags);

            case 'draft':
                return in_array(Storage::FLAG_DRAFT, $flags);

            case 'flagged':
                return in_array(Storage::FLAG_FLAGGED, $flags);

            case 'from':
                $searchStr = strtolower($items[1]);
                $fromAddressList = $message->getFrom();
                if (count($fromAddressList)) {
                    foreach ($fromAddressList as $from) {
                        return strpos(strtolower($from->getEmail()), $searchStr) !== false;
                    }
                }
                break;

            case 'header':
                $searchStr = strtolower($items[2]);
                $fieldName = $items[1];
                $header = $message->getHeaders()->get($fieldName);
                $val = $header->getFieldValue();
                return strpos(strtolower($val), $searchStr) !== false;

            case 'keyword':
                // @NOTICE NOT_IMPLEMENTED
                break;

            case 'larger':
                return strlen($message->getBody()) > (int)$items[1];

            case 'new':
                return in_array(Storage::FLAG_RECENT, $flags) && !in_array(Storage::FLAG_SEEN, $flags);

            case 'old':
                return !in_array(Storage::FLAG_RECENT, $flags);

            case 'on':
                $checkDate = new DateTime($items[1]);
                $messageDate = new DateTime($message->getHeaders()->get('Date')->getFieldValue());
                return $messageDate->format('Y-m-d') == $checkDate->format('Y-m-d');

            case 'recent':
                return in_array(Storage::FLAG_RECENT, $flags);

            case 'seen':
                return in_array(Storage::FLAG_SEEN, $flags);

            case 'sentbefore':
                $checkDate = new DateTime($items[1]);
                $messageDate = new DateTime($message->getHeaders()->get('Date')->getFieldValue());
                return $messageDate < $checkDate;

            case 'senton':
                $checkDate = new DateTime($items[1]);
                $messageDate = new DateTime($message->getHeaders()->get('Date')->getFieldValue());
                return $messageDate == $checkDate;

            case 'sentsince':
                $checkDate = new DateTime($items[1]);
                $messageDate = new DateTime($message->getHeaders()->get('Date')->getFieldValue());
                return $messageDate >= $checkDate;

            case 'since':
                // @NOTICE NOT_IMPLEMENTED
                break;

            case 'smaller':
                return strlen($message->getBody()) < (int)$items[1];

            case 'subject':
                if (isset($items[2])) {
                    $items[1] .= ' ' . $items[2];
                    unset($items[2]);
                }
                $searchStr = strtolower($items[1]);
                return strpos(strtolower($message->getSubject()), $searchStr) !== false;

            case 'text':
                $searchStr = strtolower($items[1]);
                return strpos(strtolower($message->getBody()), $searchStr) !== false;

            case 'to':
                $searchStr = strtolower($items[1]);
                $toAddressList = $message->getTo();
                if (count($toAddressList)) {
                    foreach ($toAddressList as $to) {
                        return strpos(strtolower($to->getEmail()), $searchStr) !== false;
                    }
                }
                break;

            case 'uid':
                $searchId = (int)$items[1];
                return $searchId == $messageUid;

            case 'unanswered':
                return !in_array(Storage::FLAG_ANSWERED, $flags);

            case 'undeleted':
                return !in_array(Storage::FLAG_DELETED, $flags);

            case 'undraft':
                return !in_array(Storage::FLAG_DRAFT, $flags);

            case 'unflagged':
                return !in_array(Storage::FLAG_FLAGGED, $flags);

            case 'unkeyword':
                // @NOTICE NOT_IMPLEMENTED
                break;

            case 'unseen':
                return !in_array(Storage::FLAG_SEEN, $flags);

            default:
                if (is_numeric($itemcmp)) {
                    $searchId = (int)$itemcmp;
                    return $searchId == $messageSeqNum;
                }
        }

        return false;
    }

    /**
     * @param Message $message
     * @param int $messageSeqNum
     * @param int $messageUid
     * @param bool $isUid
     * @param Gate|Obj $gate
     * @param int $level
     * @return bool
     */
    public function parseSearchMessage(Message $message, int $messageSeqNum, int $messageUid, bool $isUid, $gate, int $level = 1): bool
    {
        /** @var Obj[]|int[]|string[] $subgates */
        $subgates = [];

        if ($gate instanceof Gate) {
            if ($gate->getObj1()) {
                $subgates[] = $gate->getObj1();
            }
            if ($gate->getObj2()) {
                $subgates[] = $gate->getObj2();
            }
        } elseif ($gate instanceof Obj) {
            $val = $this->searchMessageCondition($message, $messageSeqNum, $messageUid, $gate->getValue());
            $gate->setValue($val);
        }

        foreach ($subgates as $subgate) {
            if ($subgate instanceof AndGate) {
                $this->parseSearchMessage($message, $messageSeqNum, $messageUid, $isUid, $subgate, $level + 1);
            } elseif ($subgate instanceof OrGate) {
                $this->parseSearchMessage($message, $messageSeqNum, $messageUid, $isUid, $subgate, $level + 1);
            } elseif ($subgate instanceof NotGate) {
                $this->parseSearchMessage($message, $messageSeqNum, $messageUid, $isUid, $subgate, $level + 1);
            } elseif ($subgate instanceof Obj) {
                $val = $this->searchMessageCondition($message, $messageSeqNum, $messageUid, $subgate->getValue());
                $subgate->setValue($val);
            }
        }

        return $gate->getBool();
    }

    /**
     * @param string $criteriaStr
     * @param bool $isUid
     * @return string
     */
    private function sendSearchRaw(string $criteriaStr, bool $isUid = false): string
    {
        $criteria = $this->msgGetParenthesizedlist($criteriaStr);
        $criteria = $this->parseSearchKeys($criteria);

        $tree = new CriteriaTree($criteria);
        $tree->build();

        if (!$tree->getRootGate()) {
            return '';
        }

        $server = $this->getServer();

        $ids = [];
        $msgSeqNums = $this->createSequenceSet('*');
        foreach ($msgSeqNums as $msgSeqNum) {
            $this->log('debug', 'client ' . $this->id . ' check msg: ' . $msgSeqNum);

            $message = $server->getMailBySeq($msgSeqNum, $this->selectedFolder);

            if ($message) {
                /** @var Gate $rootGate */
                $rootGate = clone $tree->getRootGate();

                $uid = $server->getMsgIdBySeq($msgSeqNum, $this->selectedFolder);

                $add = $this->parseSearchMessage($message, $msgSeqNum, $uid, $isUid, $rootGate);
                if ($add) {
                    if ($isUid) {
                        $ids[] = $uid;
                    } else {
                        // @NOTICE NOT_IMPLEMENTED
                        $ids[] = $msgSeqNum;
                    }
                }
            }
        }

        sort($ids);

        $rv = '';
        while ($ids) {
            $sendIds = array_slice($ids, 0, 30);
            $ids = array_slice($ids, 30);

            $rv .= $this->dataSend('* SEARCH ' . join(' ', $sendIds) . '');
        }
        return $rv;
    }

    /**
     * @param string $tag
     * @param string $criteriaStr
     * @return string
     */
    private function sendSearch(string $tag, string $criteriaStr): string
    {
        $this->log('debug', 'client ' . $this->id . ' current folder: ' . $this->selectedFolder);

        $rv = $this->sendSearchRaw($criteriaStr, false);
        $rv .= $this->sendOk('SEARCH completed', $tag);

        return $rv;
    }

    /**
     * @param string $tag
     * @param string $seq
     * @param string $name
     * @param bool $isUid
     * @return string
     */
    private function sendFetchRaw(string $tag, string $seq, string $name, bool $isUid = false): string
    {
        $msgItems = [];
        if ($isUid) {
            $msgItems['uid'] = '';
        }
        if (isset($name)) {
            $wanted = $this->msgGetParenthesizedlist($name);
            foreach ($wanted as $n => $item) {
                if (is_string($item)) {
                    $itemcmp = strtolower($item);
                    if ($itemcmp == 'body.peek') {
                        $next = $wanted[$n + 1];
                        $nextr = [];
                        if (is_array($next)) {
                            $keys = [];
                            $vals = [];
                            foreach ($next as $x => $val) {
                                if ($x % 2 == 0) {
                                    $keys[] = strtolower($val);
                                } else {
                                    $vals[] = $val;
                                }
                            }
                            $nextr = array_combine($keys, $vals);
                        }
                        $msgItems[$itemcmp] = $nextr;
                    } else {
                        $msgItems[$itemcmp] = '';
                    }
                }
            }
        }

        $rv = '';
        $msgSeqNums = $this->createSequenceSet($seq, $isUid);

        // Process collected msgs.
        foreach ($msgSeqNums as $msgSeqNum) {
            $msgId = $this->getServer()->getMsgIdBySeq($msgSeqNum, $this->selectedFolder);
            if (!$msgId) {
                $this->log('error', 'Can not get ID for seq num ' . $msgSeqNum . ' from root storage.');
                continue;
            }

            $message = $this->getServer()->getMailById($msgId);
            if (!$message){
                continue;
            }
            
            $flags = $this->getServer()->getFlagsById($msgId);

            $output = [];
            $outputHasFlag = false;
            $outputBody = '';
            foreach ($msgItems as $item => $val) {
                if ($item == 'flags') {
                    $outputHasFlag = true;
                } elseif ($item == 'body' || $item == 'body.peek') {
                    $peek = $item == 'body.peek';
                    $section = '';

                    $msgStr = $message->toString();
                    if (isset($val['header'])) {
                        $section = 'HEADER';
                        $msgStr = $message->getHeaders()->toString();
                    } elseif (isset($val['header.fields'])) {
                        $section = 'HEADER';
                        $msgStr = '';

                        $headers = $message->getHeaders();

                        $headerStrs = [];
                        foreach ($val['header.fields'] as $fieldNum => $field) {
                            $fieldHeader = $headers->get($field);
                            if ($fieldHeader !== false) {
                                $msgStr .= $fieldHeader->toString() . Headers::EOL;
                            }
                        }
                    }

                    $msgStr .= Headers::EOL;
                    $msgStrLen = strlen($msgStr);
                    #$output[] = 'BODY['.$section.'] {'.$msgStrLen.'}'.Headers::EOL.$msgStr.Headers::EOL;
                    $outputBody = 'BODY[' . $section . '] {' . $msgStrLen . '}' . Headers::EOL . $msgStr;
                } elseif ($item == 'rfc822.size') {
                    $size = strlen($message->toString());
                    $output[] = 'RFC822.SIZE ' . $size;
                } elseif ($item == 'uid') {
                    $output[] = 'UID ' . $msgId;
                }
            }

            if ($outputHasFlag) {
                $output[] = 'FLAGS (' . join(' ', $flags) . ')';
            }
            if ($outputBody) {
                $output[] = $outputBody;
            }

            $rv .= $this->dataSend('* ' . $msgSeqNum . ' FETCH (' . join(' ', $output) . ')');

            unset($flags[Storage::FLAG_RECENT]);
            $this->getServer()->setFlagsById($msgId, $flags);
        }

        return $rv;
    }

    /*private function sendFetch($tag, $seq, $name){
        #$this->select();
        $this->log('debug', 'client '.$this->id.' current folder: '.$this->selectedFolder);
        
        $this->sendFetchRaw($tag, $seq, $name, false);
        $this->sendOk('FETCH completed', $tag);
    }*/

    /**
     * @param string $tag
     * @param string $seq
     * @param string $name
     * @param string $flagsStr
     * @param bool $isUid
     * @return string
     */
    private function sendStoreRaw(string $tag, string $seq, string $name, string $flagsStr, bool $isUid = false): string
    {
        $flags = $this->msgGetParenthesizedlist($flagsStr);
        unset($flags[Storage::FLAG_RECENT]);
        $flags = array_unique($flags);

        $add = false;
        $rem = false;
        $silent = false;
        switch (strtolower($name)) {
            case '+flags.silent':
                $silent = true;
            case '+flags':
                $add = true;
                break;

            case '-flags.silent':
                $silent = true;
            case '-flags':
                $rem = true;
                break;
        }

        $server = $this->getServer();
        
        /** @var int[] $msgSeqNums */
        $msgSeqNums = $this->createSequenceSet($seq, $isUid);
        
        $response = '';

        // Process collected msgs.
        foreach ($msgSeqNums as $msgSeqNum) {
            $messageFlags = $server->getFlagsBySeq($msgSeqNum, $this->selectedFolder);

            $messageFlags = array_unique($messageFlags);

            if (!$add && !$rem) {
                $messageFlags = $flags;
            } elseif ($add) {
                $messageFlags = array_merge($messageFlags, $flags);
            } elseif ($rem) {
                foreach ($flags as $flag) {
                    if (($key = array_search($flag, $messageFlags)) !== false) {
                        unset($messageFlags[$key]);
                    }
                    $flags = array_values($flags);
                }
            }

            $messageFlags = array_values($messageFlags);
            $server->setFlagsBySeq($msgSeqNum, $this->selectedFolder, $messageFlags);
            $messageFlags = $server->getFlagsBySeq($msgSeqNum, $this->selectedFolder);

            if (!$silent) {
                $response .= $this->dataSend('* ' . $msgSeqNum . ' FETCH (FLAGS (' . join(' ', $messageFlags) . '))');
            }
        }

        return $response;
    }

    /**
     * @param string $tag
     * @param string $seq
     * @param string $name
     * @param string $flagsStr
     */
    private function sendStore(string $tag, string $seq, string $name, string $flagsStr)
    {
        $this->log('debug', 'client ' . $this->id . ' current folder: ' . $this->selectedFolder);

        $this->sendStoreRaw($tag, $seq, $name, $flagsStr, false);
        $this->sendOk('STORE completed', $tag);
    }

    /**
     * @param string $tag
     * @param string $seq
     * @param string $folder
     * @param bool $isUid
     * @return string
     */
    private function sendCopy(string $tag, string $seq, string $folder, bool $isUid = false): string
    {
        $server = $this->getServer();

        if ($server->getCountMailsByFolder($this->selectedFolder) == 0) {
            return $this->sendBad('No messages in selected mailbox.', $tag);
        }

        if (!$server->folderExists($folder)) {
            return $this->sendNo('Can not get folder: no subfolder named ' . $folder, $tag, 'TRYCREATE');
        }

        $msgSeqNums = $this->createSequenceSet($seq, $isUid);
        foreach ($msgSeqNums as $msgSeqNum) {
            $server->copyMailBySequenceNum($msgSeqNum, $this->selectedFolder, $folder);
        }

        return $this->sendOk('COPY completed', $tag);
    }

    /**
     * @param string $tag
     * @param string $argsStr
     * @return string
     */
    private function sendUid(string $tag, string $argsStr): string
    {
        $this->log('debug', 'client ' . $this->id . ' sendUid: "' . $argsStr . '"');

        $args = $this->msgParseString($argsStr, 2);
        $command = $args[0];
        $commandcmp = strtolower($command);
        if (isset($args[1])) {
            $args = $args[1];
        } else {
            return $this->sendBad('Arguments invalid.', $tag);
        }

        if ($commandcmp == 'copy') {
            $args = $this->msgParseString($args, 2);
            $seq = $args[0];
            if (!isset($args[1])) {
                return $this->sendBad('Arguments invalid.', $tag);
            }
            $folder = $args[1];

            return $this->sendCopy($tag, $seq, $folder, true);
        } elseif ($commandcmp == 'fetch') {
            $args = $this->msgParseString($args, 2);
            $seq = $args[0];
            $name = $args[1];

            $rv = $this->sendFetchRaw($tag, $seq, $name, true);
            $rv .= $this->sendOk('UID FETCH completed', $tag);

            return $rv;
        } elseif ($commandcmp == 'store') {
            $args = $this->msgParseString($args, 3);
            $seq = $args[0];
            $name = $args[1];
            $flagsStr = $args[2];

            $rv = $this->sendStoreRaw($tag, $seq, $name, $flagsStr, true);
            $rv .= $this->sendOk('UID STORE completed', $tag);

            return $rv;
        } elseif ($commandcmp == 'search') {
            $criteriaStr = $args;

            $rv = $this->sendSearchRaw($criteriaStr, true);
            $rv .= $this->sendOk('UID SEARCH completed', $tag);

            return $rv;
        }

        return $this->sendBad('Arguments invalid.', $tag);
    }

    /**
     * @param string $text
     * @param string|null $tag
     * @param string|null $code
     * @return string
     */
    public function sendOk(string $text, string $tag = null, string $code = null): string
    {
        if ($tag === null) {
            $tag = '*';
        }
        return $this->dataSend($tag . ' OK' . ($code ? ' [' . $code . ']' : '') . ' ' . $text);
    }

    /**
     * @param string $text
     * @param string|null $tag
     * @param string|null $code
     * @return string
     */
    public function sendNo(string $text, string $tag = null, string $code = null): string
    {
        if ($tag === null) {
            $tag = '*';
        }
        return $this->dataSend($tag . ' NO' . ($code ? ' [' . $code . ']' : '') . ' ' . $text);
    }

    /**
     * @param string $text
     * @param string|null $tag
     * @param string|null $code
     * @return string
     */
    public function sendBad(string $text, string $tag = null, string $code = null): string
    {
        if ($tag === null) {
            $tag = '*';
        }
        return $this->dataSend($tag . ' BAD' . ($code ? ' [' . $code . ']' : '') . ' ' . $text);
    }

    /**
     * @param string $text
     * @param string|null $code
     * @return string
     */
    public function sendPreauth(string $text, string $code = null): string
    {
        return $this->dataSend('* PREAUTH' . ($code ? ' [' . $code . ']' : '') . ' ' . $text);
    }

    /**
     * @param string $text
     * @param string|null $code
     * @return string
     */
    public function sendBye(string $text, string $code = null): string
    {
        return $this->dataSend('* BYE' . ($code ? ' [' . $code . ']' : '') . ' ' . $text);
    }

    public function shutdown()
    {
        if (!$this->getStatus('hasShutdown')) {
            $this->setStatus('hasShutdown', true);

            if ($this->getSocket()) {
                $this->getSocket()->shutdown();
                $this->getSocket()->close();
            }
        }
    }

    /**
     * @param string $folder
     * @return bool
     */
    public function select(string $folder): bool
    {
        if ($this->getServer()->folderExists($folder)) {
            $this->log('debug', 'client ' . $this->id . ' old folder: "' . $this->selectedFolder . '"');
            $this->selectedFolder = $folder;
            $this->log('debug', 'client ' . $this->id . ' new folder: "' . $this->selectedFolder . '"');

            return true;
        }

        $this->selectedFolder = '';

        return false;
    }

    /**
     * @return string
     */
    public function getSelectedFolder(): string
    {
        return $this->selectedFolder;
    }
}
