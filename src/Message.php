<?php
namespace QueueSystem;

/**
 * Pojo class for message.
 * 
 * @author Sankalp Bhatt
 *
 */
class Message
{
    /**
     * Contains Body of the message.
     * @var string
     */
    private $body = '';

    /**
     * Unique Identifier for the message.
     * @var string
     */
    private $messageId = NULL;
    
    /**
     * Additional information related to message.
     * @var array
     */
    private $metaInfo = array();

    /**
     * COnstructor for new Message.
     * @param array $data
     *    - $data['body'] : body of the message.
     *    - $data['messageId'] : Id of the message.
     *    - $data['meta'] : Meta Info of message.
     */
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
    
    /**
     * Set Message meta info.
     * @param array $data
     */
    public function setMetaInfo($data = array()) {
        $this->metaInfo = array_merge($this->metaInfo, $data);
    }
    
    /**
     * Get meta info.
     */
    public function getMetaInfo() {
        return $this->metaInfo;
    }

    /**
     * Set message Body.
     * 
     * @param array $body
     * @return \QueueSystem\Message
     */
    public function setBody($body)
    {
        if (! is_string($body)) {
            $body = serialize($body);
        }
        $this->body = $body;
        return $this;
    }
    
    /**
     * Get message body
     */
    public function getBody()
    {
        return $this->body;
    }
    
    /**
     * Set Message Id.
     * 
     * @param string $messageId
     * @return \QueueSystem\Message
     */
    public function setMessageId($messageId)
    {
        $this->messageId = $messageId;
        return $this;
    }
    
    /**
     * Get messageId.
     */
    public function getMessageId()
    {
        return $this->messageId;
    }
}