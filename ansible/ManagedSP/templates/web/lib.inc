<?php
define('SOCKET', '/opt/Socket/CAT_requests/queue');

function cat_socket($obj) {
   $sock = socket_create(AF_UNIX, SOCK_STREAM, 0);
   $conn = socket_connect($sock, SOCKET);
   if ( $conn ) {
     socket_write ($sock, $obj, strlen($obj));
     $out = socket_read ($sock, 2048);
     return $out;
   }
   return 'FAILURE';
}
