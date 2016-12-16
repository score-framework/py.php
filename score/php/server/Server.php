<?php
# Copyright © 2015,2016 STRG.AT GmbH, Vienna, Austria
#
# This file is part of the The SCORE Framework.
#
# The SCORE Framework and all its parts are free software: you can redistribute
# them and/or modify them under the terms of the GNU Lesser General Public
# License version 3 as published by the Free Software Foundation which is in the
# file named COPYING.LESSER.txt.
#
# The SCORE Framework and all its parts are distributed without any WARRANTY;
# without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
# PARTICULAR PURPOSE. For more details see the GNU Lesser General Public
# License.
#
# If you have not received a copy of the GNU Lesser General Public License see
# http://www.gnu.org/licenses/.
#
# The License-Agreement realised between you as Licensee and STRG.AT GmbH as
# Licenser including the issue of its valid conclusion and its pre- and
# post-contractual effects is governed by the laws of Austria. Any disputes
# concerning this License-Agreement including the issue of its valid conclusion
# and its pre- and post-contractual effects are exclusively decided by the
# competent court, in whose district STRG.AT GmbH has its registered seat, at
# the discretion of STRG.AT GmbH also the competent court, in whose district the
# Licensee has his registered seat, an establishment or assets.


require __DIR__ . '/Connection.php';

class Server {

    public function __construct($host = '0.0.0.0', $port = 9152) {
        $this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if (!$this->socket) {
            throw new Exception('socket_create: ' . socket_strerror(socket_last_error()));
        }
        # Reuse port
        // socket_set_option($this->socket, SOL_SOCKET, SO_REUSEADDR, 1);
        if (!socket_bind($this->socket, $host, $port)) {
            throw new Exception('socket_bind: ' . socket_strerror(socket_last_error()));
        }
        if (!socket_listen($this->socket)) {
            throw new Exception('socket_listen: ' . socket_strerror(socket_last_error()));
        }
    }

    public function loop() {
        $this->children = [];
        while (true) {
            $connection = @socket_accept($this->socket);
            if (!$connection) {
                throw new strg_NetworkingException('socket_accept', socket_strerror(socket_last_error()));
            }
            $childPid = pcntl_fork();
            if ($childPid === -1) {
                throw new Exception('pcntl_fork');
            } else if ($childPid) {
                $this->children[] = $childPid;
            } else {
                // close listening socket in child process
                socket_close($this->socket);
                (new Connection($connection))->loop();
                exit();
            }
        }
    }

}
