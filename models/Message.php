<?php
class Message
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    // (All methods moved to MessageThread.php for cohesion) 
}