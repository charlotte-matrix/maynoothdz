<?php

namespace MapSVG;

class ImportLog extends Model
{
    /** @var string MD5 hash of schemaName + type + message */
    public $id;

    /** @var string Schema (collection) name this log belongs to */
    public $schemaName;

    /** @var string */
    public $createdAt;

    /** @var string */
    public $message;

    /** @var string 'error' | 'warning' | 'info' */
    public $type;

    /** @var int How many times this identical message was encountered */
    public $counter;

    public function setSchemaName($value) { $this->schemaName = (string) $value; }
    public function setCreatedAt($value)  { $this->createdAt  = (string) $value; }
    public function setMessage($value)    { $this->message    = (string) $value; }
    public function setType($value)       { $this->type       = (string) $value; }
    public function setCounter($value)    { $this->counter    = (int)    $value; }
}
