<?php
namespace QueueSystem;

/**
 * Pojo Class for Message.
 * 
 * @author sab
 *        
 */
class Message
{

    private $body = '';

    private $messageId = NULL;
    
    private $metaInfo = array();

    public function __construct($data = array())
    {
        if (! empty($data['body'])) {
            $this->setBody($data['body']);
        }
        if (! empty($data['messageId'])) {
            $this->getMessageId($data['messageId']);
        }
        
        if (!empty($data['meta'])) {
            $this->setMetaInfo($data['meta']);
        }
    }
    
    public function setMetaInfo($data = array()) {
        array_merge($this->metaInfo, $data);
    }
    
    public function getMetaInfo() {
        return $this->metaInfo;
    }

    public function setBody($body)
    {
        if (! is_string($body)) {
            $body = serialize($body);
        }
        $this->body = $body;
        return $this;
    }

    public function getBody()
    {
        return $this->body;
    }

    public function setMessageId($messageId)
    {
        $this->messageId = $messageId;
        return $this;
    }

    public function getMessageId()
    {
        return $this->messageId;
    }
}