<?php

require_once('vendor/autoload.php');
require_once('Mailbox.php');
include_once('mailbox.cfg');

$mb = new Mailbox();
$mb
    ->connect($server, $user, $pwd)
    ->messages($mb->unread())
    ->attachements()
    ->ofType('/\.(txt|csv)$/i')
    ->rename(array(
        $mb->company(),
        $mb->messageDate(),
        $mb->sender(),
    ))
    /*
    ->store('K:/temp/')
    */
;

dump($mb);

// messages -> header