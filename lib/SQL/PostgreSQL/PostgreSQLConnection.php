<?php
/**
 * BSD 3 Clause License
 * Copyright (c) 2017, John Dunlap<john.david.dunlap@gmail.com>
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without modification,
 * are permitted provided that the following conditions are met:
 *    - Redistributions of source code must retain the above copyright notice, this
 *      list of conditions and the following disclaimer.
 *    - Redistributions in binary form must reproduce the above copyright notice,
 *      this list of conditions and the following disclaimer in the documentation
 *      and/or other materials provided with the distribution.
 *    - Neither the name of the copyright holder nor the names of its contributors may
 *      be used to endorse or promote products derived from this software without 
 *      specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND ANY 
 * EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES 
 * OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT 
 * SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT 
 * OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
 * HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR 
 * TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE,
 * EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */

namespace lib\SQL\PostgreSQL;

use lib\SQL\Connection;
use lib\SQL\DataSource;


class PostgreSQLConnection implements Connection {
    private $dbConnection;

    public function __construct(DataSource $dataSource) {
        $this->dbConnection = new \PDO("pgsql:user='".$dataSource->getUsername()."' host='".$dataSource->getHost()."' dbname='".$dataSource->getDatabase()."' password='".$dataSource->getPassword()."' port='".$dataSource->getPort()."'");
        $this->dbConnection->setAttribute(\PDO::ATTR_EMULATE_PREPARES, false);
    }

    public function begin(){
        $this->dbConnection->beginTransaction();

        $currentUsername = $this->fetchField("select current_setting('intranet.current_username');");

        if ($currentUsername == 'anonymous' && isset($_SESSION["LOGGED_IN_AS"])) {
            $this->execute("set intranet.current_username='" . $_SESSION["LOGGED_IN_AS"] . "'");
        } else {
            $this->execute("set intranet.current_username='anonymous'");
        }
    }
    public function commit() {
        $this->execute("set intranet.current_username='anonymous'");
        $this->dbConnection->commit();
    }
    public function rollback() {
        if( $this->dbConnection->inTransaction() === true ) {
            $this->dbConnection->rollBack();
        }
        $this->execute("set intranet.current_username='anonymous'");
    }

    public function execute(string $sql,$args=Array() ) {
        if( !isset($sql) || is_string($sql)  === false) {
            throw new \Exception("Expecting string for 1st parameter, got [".gettype($sql)."]");
        }
        $PDOStatement = $this->dbConnection->prepare($sql);
        $PDOStatement->execute($args);
        if( $PDOStatement->errorCode() != "00000") {
            $errorInfo = $PDOStatement->errorInfo();
            $errorMessage = "[".$PDOStatement->errorCode()."] ".$errorInfo[2] . $PDOStatement->errorCode();
            throw new \Exception($errorMessage);
        }
        return $PDOStatement;
    }

    public function fetchAll(string $sql,$args=Array()) {
        $prep = $this->execute($sql,$args);
        return $prep->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function fetchRow(string $sql,$args=Array()) {
        $prep = $this->execute($sql,$args);
        return $prep->fetch(\PDO::FETCH_ASSOC);
    }

    public function fetchField(string $sql,$args=Array()) {

        /*
         * In case a single parameter is passed that is not an array
         */
        if( !empty( $args ) && !is_array( $args ) ) {
            $args = array($args);
        }

        $prep = $this->execute($sql,$args);

        $a = $prep->fetch(\PDO::FETCH_ASSOC);

        /*
         * The returned data should be a single value.
         */
        $keys       = array_keys( $a );
        $key_count  = sizeof( $keys );

        if( $key_count === 0 || $key_count > 1) {
            throw new \Exception("Expecting a single returned field, got [$key_count]");
        }
        return reset($a);
    }

    public function lastError() {
        return $this->dbConnection->errorInfo();
    }

    public function lastId($sequence) {
       return $this->dbConnection->lastInsertId($sequence);
    }

}
