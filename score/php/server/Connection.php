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


function _render_file($filename, $globals) {
    extract($globals);
    ob_start();
    require $filename;
    return ob_get_clean();
}

class ConnectionClosed extends Exception {

    private $connection;

    public function __construct($connection) {
        parent::__construct('Connection closed');
        $this->connection = $connection;
    }

}

class Connection {

    private $socket;

    public function __construct($socket) {
        $this->socket = $socket;
    }

    public function loop() {
        while (true) {
            try {
                $job = $this->receiveByte();
            } catch (ConnectionClosed $e) {
                break;
            }
            switch ($job) {
                case 1:  # render file
                    $this->handleFileRendering();
                    break;
            }
        }
    }

    protected function handleFileRendering() {
        $filename = $this->receiveString();
        $globals = [];  // $this->receiveVars();
        $result = _render_file($filename, $globals);
        $this->sendString($result);
    }

    private function receiveVars() {
        $vars = [];
        $varsCount = $this->receiveByte();
        for ($i = 0; $i < $varsCount; $i++) {
            $vars[$this->receiveString()] = $this->receiveByte();
        }
    }

    private function receiveByte() {
        return $this->receive(1, 'C');
    }

    private function receiveShort() {
        return $this->receive(2, 'n');
    }

    private function receiveString() {
        return $this->receive($this->receiveShort());
    }

    private function receive($length, $unpackChar=NULL) {
        $result = socket_recv($this->socket, $data, $length, MSG_WAITALL);
        if (!$result) {
            socket_close($this->socket);
            throw new ConnectionClosed($this);
        }
        if ($unpackChar) {
            $data = unpack("${unpackChar}d", $data)['d'];
        }
        return $data;
    }

    private function sendString($string) {
        $this->sendShort(strlen($string));
        $this->send($string);
    }

    private function sendShort($data) {
        $this->send($data, 'n');
    }

    private function send($data, $packChar=NULL) {
        if ($packChar) {
            $data = pack($packChar, $data);
        }
        socket_send($this->socket, $data, strlen($data), NULL);
    }

}
