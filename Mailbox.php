<?php

class Mailbox
{

    private $mailbox;
    private $messages;
    private $attachements;

    /**
     * set up an imap connection to a specified mailbox.
     * @param $server
     * @param $user
     * @param $pwd
     * @return $this
     */
    public function connect($server, $user, $pwd)
    {
        $this->mailbox  = imap_open($server, $user, $pwd) or die('mailbox unreachable');

        return $this;
    }

    /**
     * @param string $criteria
     * @return $this
     */
    public function messages($criteria)
    {
        $this->messages = array_map(
            function($messageNumber) {
                return array(
                    'id' => $messageNumber,
                    'header' => imap_header($this->mailbox, $messageNumber),
                    'struct' => imap_fetchstructure($this->mailbox, $messageNumber)
                );
            },
            imap_search($this->mailbox, $criteria)
        );

        return $this;
    }

    public function attachements()
    {
        $this->attachements = array_reduce(
            $this->messages,
            function($attachments, $message) {
                if(! $message['struct']->parts) return $attachments;

                foreach((array)$message['struct']->parts as $partNumber => $partInfo) {
                    if(! $this->isAttachment($partInfo)) continue;

                    $attachment = array(
                        'id' => $message['id'],
                        'header' => $message['header'],
                        'filename' => $partInfo->dparameters[0]->value,
                        'partNumber' => $partNumber,
                        'encoding' => $partInfo->encoding
                    );
                    array_push($attachments, $attachment);
                }

                return $attachments;
            },
            array()
        );

        return $this;
    }

    public function ofType($attachementTypeRegex)
    {
       $this->attachements = array_filter(
           $this->attachements,
           function($attach) use ($attachementTypeRegex){
               return preg_match($attachementTypeRegex, $attach['filename']);
           }
       );

       return $this;
    }

    public function rename($fileNamePartCallables)
    {
        $this->attachements = array_map(
            function($attach) use ($fileNamePartCallables) {
                $initial = array_shift($fileNamePartCallables);
                $attach['filename'] = array_reduce(
                    $fileNamePartCallables,
                    function($name, $namepartCallable) use ($attach) {
                        return $name . '-' . $namepartCallable($attach);
                    },
                    $initial($attach)
                ).$this->extension($attach);

                return $attach;
            },
            $this->attachements
        );
    }

    public function messageDate()
    {
        return function($attach) { return $attach['header']->udate; };
    }

    public function company()
    {
        return function($attach) {
            $matches = array();
            preg_match('/(^\w*)\./', $attach['header']->to[0]->host, $matches);

            return isset($matches[1]) ? $matches[1] : reset($matches);
        };
    }

    public function sender()
    {
        return function($attach) { return preg_replace('/[.]/', '_', $attach['header']->from[0]->mailbox); };
    }

    public function extension($attach)
    {
        $matches = array();
        preg_match('/\.\w*\z/i', $attach['filename'], $matches);

        return reset($matches);
    }

    public function unread() { return 'UNSEEN'; }

    private function isAttachment($partInfo)
    {
        return $partInfo->ifdisposition
            && strtolower($partInfo->disposition) === "attachment";
    }


}